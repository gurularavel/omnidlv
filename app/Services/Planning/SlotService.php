<?php

namespace App\Services\Planning;

use App\Enums\Channel;
use App\Enums\StatusCategory;
use App\Exceptions\DomainException;
use App\Models\Hub;
use App\Models\Task;
use App\Models\TaskPlan;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\Routing\RoutingService;
use App\Services\StatusMachine;
use App\Support\CurrentCompany;
use App\Support\GeoPoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Delivery slots (QDelivery "task slots"): tasks that share a hub and a time window, e.g.
 * slot 2026102112001400 = 12:00–14:00. Closing a slot plans it: the open tasks are routed
 * across the eligible couriers, each courier gets a TaskPlan with stops in order and an ETA
 * per stop, and the tasks are assigned.
 *
 * Slot codes are YYYYMMDDHHmmHHmm in the company's timezone.
 */
class SlotService
{
    public const CLOSE_DEFAULTS = [
        'shift' => 'working',
        'team_id' => null,
        'online' => 'all',
        'duty' => 'all',
        'tour' => true,
        'dispatch_early' => false,
        'from_hub' => true,
    ];

    /** Head start a courier gets to reach the first stop when dispatch_early is on. */
    private const EARLY_LEAD = 3600;

    /** Extra time after the slot to drive back to the hub when tour is on. */
    private const RETURN_GRACE = 1800;

    public function __construct(
        private readonly CourierPool $pool,
        private readonly RoutingService $routing,
        private readonly AssignmentService $assignments,
        private readonly StatusMachine $statuses,
        private readonly CurrentCompany $tenant,
    ) {}

    /** Open slots: tasks with a slot that are not planned or assigned yet. */
    public function open(?int $hubId = null): Collection
    {
        return $this->openTasks()
            ->when($hubId, fn ($q) => $q->where('hub_id', $hubId))
            ->selectRaw('hub_id, slot, min(slot_starts_at) as starts_at, max(slot_ends_at) as ends_at, count(*) as tasks_count')
            ->groupBy('hub_id', 'slot')
            ->orderBy('starts_at')
            ->get()
            ->map(function ($row) {
                $row->setAttribute('starts_at', Carbon::parse($row->getRawOriginal('starts_at')));
                $row->setAttribute('ends_at', Carbon::parse($row->getRawOriginal('ends_at')));

                return $row;
            });
    }

    /**
     * @return array{plans: list<int>, assigned: int, unassigned: list<int>, solver: string, router: ?string}
     */
    public function close(string $slot, Hub $hub, array $options = [], Channel $channel = Channel::Api, ?User $actor = null): array
    {
        $options = array_merge(self::CLOSE_DEFAULTS, array_filter($options, fn ($v) => $v !== null));
        $lock = Cache::lock("slot-close:{$hub->company_id}:{$hub->id}:{$slot}", 300);

        if (! $lock->get()) {
            throw new DomainException(__('planning.errors.closing'), 'slot_closing');
        }

        try {
            return DB::transaction(fn () => $this->plan($slot, $hub, $options, $channel, $actor));
        } finally {
            $lock->release();
        }
    }

    /**
     * Moves tasks to another slot (QDelivery POST /task-slots/shift). Only tasks nobody has
     * started can move; a planned task leaves its plan and goes back to Created.
     *
     * @param  list<int>  $taskIds
     */
    public function shift(array $taskIds, string $slot, Carbon $startsAt, Carbon $endsAt, ?Carbon $slotStartsAt, ?Carbon $slotEndsAt, Channel $channel, ?User $actor = null): int
    {
        return DB::transaction(function () use ($taskIds, $slot, $startsAt, $endsAt, $slotStartsAt, $slotEndsAt, $channel, $actor) {
            $tasks = Task::query()->whereKey($taskIds)->lockForUpdate()->get();

            $missing = array_diff($taskIds, $tasks->modelKeys());
            if ($missing) {
                throw new DomainException(__('planning.errors.unknown_tasks', ['ids' => implode(', ', $missing)]), 'unknown_tasks');
            }

            $movable = [StatusCategory::Created, StatusCategory::Pool, StatusCategory::Assigned];
            $stuck = $tasks->reject(fn (Task $t) => in_array($t->status_category_slug, $movable, true));
            if ($stuck->isNotEmpty()) {
                throw new DomainException(__('planning.errors.not_movable', ['ids' => implode(', ', $stuck->modelKeys())]), 'not_movable');
            }

            foreach ($tasks as $task) {
                $planId = $task->plan_id;
                $task->forceFill([
                    'slot' => $slot,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'slot_starts_at' => $slotStartsAt ?? $startsAt,
                    'slot_ends_at' => $slotEndsAt ?? $endsAt,
                    'plan_id' => null,
                    'route_order' => 0,
                    'eta_at' => null,
                ])->save();

                if ($planId !== null && $task->status_category_slug === StatusCategory::Assigned) {
                    $this->assignments->unassign($task, $channel, $actor);
                }

                if ($planId !== null) {
                    $this->refreshPlan(TaskPlan::query()->find($planId));
                }
            }

            return $tasks->count();
        });
    }

    /** Recomputes a plan's totals, stop order and ETAs after its tasks changed. */
    public function refreshPlan(?TaskPlan $plan): void
    {
        if ($plan === null) {
            return;
        }

        $tasks = $plan->tasks()->get();

        if ($tasks->isEmpty()) {
            $plan->forceFill(['status_slug' => TaskPlan::CANCELLED, 'quantity' => 0])->save();

            return;
        }

        $this->retime($plan, $tasks);
    }

    /**
     * Puts the plan's stops in the given order and recomputes ETAs along it.
     *
     * @param  list<int>  $taskIds
     */
    public function reorder(TaskPlan $plan, array $taskIds): void
    {
        DB::transaction(function () use ($plan, $taskIds) {
            $tasks = $plan->tasks()->lockForUpdate()->get()->keyBy('id');

            if (array_diff($taskIds, $tasks->keys()->all()) || count($taskIds) !== $tasks->count()) {
                throw new DomainException(__('planning.errors.reorder_mismatch'), 'reorder_mismatch');
            }

            foreach (array_values($taskIds) as $i => $id) {
                $tasks[$id]->forceFill(['route_order' => $i + 1])->save();
            }

            $plan->forceFill(['solver' => 'manual'])->save();
            $this->retime($plan, $plan->tasks()->get());
        });
    }

    private function plan(string $slot, Hub $hub, array $options, Channel $channel, ?User $actor): array
    {
        $tasks = $this->openTasks()->where('hub_id', $hub->id)->where('slot', $slot)->lockForUpdate()->get();

        if ($tasks->isEmpty()) {
            throw new DomainException(__('planning.errors.empty_slot'), 'empty_slot');
        }

        $couriers = $this->pool->find($hub, [
            'team_id' => $options['team_id'],
            'shift' => $options['shift'],
            'online' => $options['online'],
            'duty' => $options['duty'],
        ]);

        if ($couriers->isEmpty()) {
            throw new DomainException(__('planning.errors.no_couriers'), 'no_couriers');
        }

        $startsAt = $tasks->min('slot_starts_at') ?? $tasks->min('starts_at');
        $endsAt = $tasks->max('slot_ends_at') ?? $tasks->max('ends_at');
        $routable = $tasks->filter(fn (Task $t) => $t->targetPoint() !== null);
        $useUnits = $routable->contains(fn (Task $t) => $t->unit_quantity > 0);

        $problem = [
            'vehicles' => $couriers->map(fn (User $c) => $this->vehicle($c, $hub, $startsAt, $endsAt, $options, $useUnits))->values()->all(),
            'jobs' => $routable->map(fn (Task $t) => $this->job($t, $useUnits))->values()->all(),
        ];

        $solution = $routable->isEmpty()
            ? ['routes' => [], 'unassigned' => [], 'solver' => 'none']
            : $this->routing->solve($problem);

        $byId = $tasks->keyBy('id');
        $plans = [];
        $assigned = 0;

        foreach ($solution['routes'] as $route) {
            $courier = $couriers->firstWhere('id', $route['vehicle']);
            $stops = collect($route['steps'])->where('type', 'job')->values();

            $plan = TaskPlan::query()->create([
                'hub_id' => $hub->id,
                'courier_id' => $courier->id,
                'slot' => $slot,
                'status_slug' => TaskPlan::PLANNED,
                'starts_at' => Carbon::createFromTimestamp($route['steps'][0]['arrival']),
                'ends_at' => Carbon::createFromTimestamp(end($route['steps'])['arrival']),
                'quantity' => $stops->count(),
                'distance' => (int) ($route['distance'] ?? 0),
                'duration' => (int) $route['duration'],
                'start' => $this->latLng($route['steps'][0]['location'] ?? null),
                'end' => $options['tour'] ? $this->latLng(end($route['steps'])['location'] ?? null) : null,
                'solver' => $solution['solver'] ?? 'vroom',
            ]);

            $previous = ['duration' => 0, 'distance' => 0];
            foreach ($stops as $i => $step) {
                $task = $byId[$step['id'] ?? $step['job']];
                $task->forceFill([
                    'plan_id' => $plan->id,
                    'route_order' => $i + 1,
                    'eta_at' => Carbon::createFromTimestamp($step['arrival'] + ($step['waiting_time'] ?? 0)),
                    'duration' => max(0, (int) $step['duration'] - $previous['duration']),
                    'distance' => max(0, (int) ($step['distance'] ?? 0) - $previous['distance']),
                ])->save();
                $previous = ['duration' => (int) $step['duration'], 'distance' => (int) ($step['distance'] ?? 0)];

                // The solver already respected capacity; current load was subtracted up front.
                $this->assignments->assign($task, $courier, $channel, $actor, ignoreCapacity: true);
                $assigned++;
            }

            $this->drawGeometry($plan);
            $plans[] = $plan;
        }

        $unassignedIds = collect($solution['unassigned'] ?? [])->pluck('id')
            ->merge($tasks->reject(fn (Task $t) => $t->targetPoint() !== null)->modelKeys())
            ->unique()->values()->all();

        $leftover = $this->handleUnassigned($tasks->only($unassignedIds), $plans, $channel, $actor);

        return [
            'plans' => array_map(fn (TaskPlan $p) => $p->id, $plans),
            'assigned' => $assigned + $leftover['assigned'],
            'unassigned' => $leftover['unassigned'],
            'solver' => $solution['solver'] ?? 'vroom',
            'router' => $plans[0]->router ?? null,
        ];
    }

    /**
     * What happens to stops no route could fit, by the tasks' slot_options.unassigned_strategy:
     * nearest_plan (default) appends them to the plan that ends closest, pool puts them in the
     * pool for couriers to claim, anything else leaves them for the dispatcher.
     */
    private function handleUnassigned(Collection $tasks, array $plans, Channel $channel, ?User $actor): array
    {
        $assigned = 0;
        $left = [];

        foreach ($tasks as $task) {
            $strategy = $task->slot_options['unassigned_strategy'] ?? 'nearest_plan';
            $point = $task->targetPoint();

            if ($strategy === 'nearest_plan' && $plans !== [] && $point !== null) {
                $plan = collect($plans)->sortBy(function (TaskPlan $p) use ($point) {
                    $last = $p->tasks()->get()->last()?->targetPoint();

                    return $last ? $last->distanceTo($point) : PHP_FLOAT_MAX;
                })->first();

                $task->forceFill(['plan_id' => $plan->id, 'route_order' => $plan->tasks()->max('route_order') + 1])->save();
                $this->assignments->assign($task, $plan->courier, $channel, $actor, ignoreCapacity: true);
                $this->retime($plan, $plan->tasks()->get());
                $assigned++;

                continue;
            }

            if ($strategy === 'pool' && $task->status_category_slug === StatusCategory::Created) {
                $this->statuses->toCategory($task, StatusCategory::Pool, $channel, $actor);
            }

            $left[] = $task->id;
        }

        return ['assigned' => $assigned, 'unassigned' => $left];
    }

    private function vehicle(User $courier, Hub $hub, Carbon $startsAt, Carbon $endsAt, array $options, bool $units): array
    {
        $from = $options['from_hub'] ? $hub->point() : ($courier->courierLocation?->point() ?? $hub->point());
        $open = $startsAt->getTimestamp() - ($options['dispatch_early'] ? self::EARLY_LEAD : 0);
        $close = $endsAt->getTimestamp() + ($options['tour'] ? self::RETURN_GRACE : 0);
        $vehicle = [
            'id' => $courier->id,
            'profile' => $courier->courierDetail?->vehicle_type_slug?->routingProfile() ?? 'car',
            'description' => $courier->name,
            'start' => $from->toLngLat(),
            'time_window' => [$open, $close],
        ];

        if ($options['tour']) {
            $vehicle['end'] = $hub->point()->toLngLat();
        }

        $free = $this->pool->remaining($courier, $units);
        if ($free !== null) {
            $vehicle['capacity'] = [$free];
        }

        return $vehicle;
    }

    private function job(Task $task, bool $units): array
    {
        $from = max($task->starts_at?->getTimestamp() ?? 0, $task->slot_starts_at?->getTimestamp() ?? 0);
        $to = $task->ends_at?->getTimestamp() ?? $task->slot_ends_at?->getTimestamp();

        return array_filter([
            'id' => $task->id,
            'description' => $task->tracking_code,
            'location' => $task->targetPoint()->toLngLat(),
            'service' => $task->service_time * 60,
            'delivery' => [$units ? max(1, $task->unit_quantity) : 1],
            'priority' => max(0, min(100, $task->priority)),
            'time_windows' => $to ? [[$from, max($from, $to)]] : null,
        ], fn ($v) => $v !== null);
    }

    /** Recomputes ETAs and totals along the plan's current stop order. */
    private function retime(TaskPlan $plan, Collection $tasks): void
    {
        $start = $plan->start ? new GeoPoint($plan->start[0], $plan->start[1]) : null;
        $profile = $plan->courier?->courierDetail?->vehicle_type_slug?->routingProfile() ?? 'car';

        $clock = ($plan->starts_at ?? now())->copy();
        $distance = $duration = 0;
        $legsFrom = $start;

        foreach ($tasks as $i => $task) {
            $to = $task->targetPoint();
            if ($to === null) {
                continue;
            }

            $leg = $legsFrom ? $this->routing->route([$legsFrom, $to], $profile) : ['distance' => 0, 'duration' => 0];
            $clock->addSeconds($leg['duration']);
            if ($task->starts_at && $clock->lt($task->starts_at)) {
                $clock = $task->starts_at->copy();
            }

            $task->forceFill(['route_order' => $i + 1, 'eta_at' => $clock->copy(), 'distance' => $leg['distance'], 'duration' => $leg['duration']])->save();
            $clock->addMinutes($task->service_time);
            $distance += $leg['distance'];
            $duration += $leg['duration'];
            $legsFrom = $to;
        }

        $plan->forceFill([
            'quantity' => $tasks->count(),
            'distance' => $distance,
            'duration' => $duration,
            'ends_at' => $clock,
        ])->save();

        $this->drawGeometry($plan);
    }

    /** Road geometry for the map; skipped quietly when the router cannot draw it. */
    private function drawGeometry(TaskPlan $plan): void
    {
        $points = $plan->tasks()->get()->map(fn (Task $t) => $t->targetPoint())->filter()->values()->all();

        if ($plan->start) {
            array_unshift($points, new GeoPoint($plan->start[0], $plan->start[1]));
        }
        if ($plan->end) {
            $points[] = new GeoPoint($plan->end[0], $plan->end[1]);
        }

        if (count($points) < 2 || count($points) > 100) {
            return;
        }

        try {
            $route = $this->routing->route($points);
            $plan->forceFill(['geometry' => $route['geometry'], 'router' => $route['source']])->save();
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function latLng(?array $lngLat): ?array
    {
        return $lngLat ? [round($lngLat[1], 6), round($lngLat[0], 6)] : null;
    }

    private function openTasks()
    {
        return Task::query()
            ->whereNotNull('slot')
            ->whereNull('plan_id')
            ->whereNull('courier_id')
            ->inCategory(StatusCategory::Created, StatusCategory::Pool);
    }
}
