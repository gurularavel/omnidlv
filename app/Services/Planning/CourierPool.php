<?php

namespace App\Services\Planning;

use App\Models\Hub;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Couriers eligible for automatic assignment or slot planning, filtered the way QDelivery's
 * assignment_options and slot close parameters describe. Each courier comes with
 * `active_tasks_count` and `active_units` (their current load).
 */
class CourierPool
{
    /**
     * Filters: team_id, shift (working|resting|all), online (online|offline|all),
     * duty (on_duty|idle|all), min_capacity.
     *
     * @return Collection<int, User>
     */
    public function find(?Hub $hub, array $filters = []): Collection
    {
        $shift = $filters['shift'] ?? 'working';
        $online = $filters['online'] ?? 'all';
        $duty = $filters['duty'] ?? 'all';

        $couriers = User::query()
            ->couriers()
            ->with(['courierDetail', 'courierLocation'])
            ->withCount(['courierTasks as active_tasks_count' => fn ($q) => $q->active()])
            ->withSum(['courierTasks as active_units' => fn ($q) => $q->active()], 'unit_quantity')
            ->when($filters['team_id'] ?? null, fn ($q, $teamId) => $q->whereHas('teams', fn ($t) => $t->whereKey($teamId)))
            ->when(! ($filters['team_id'] ?? null) && $hub, fn ($q) => $q->whereHas('teams', fn ($t) => $t->where('hub_id', $hub->id)))
            ->when($shift !== 'all', fn ($q) => $shift === 'working'
                ? $q->whereHas('courierLocation', fn ($l) => $l->where('is_working', true))
                : $q->whereDoesntHave('courierLocation', fn ($l) => $l->where('is_working', true)))
            ->when($online !== 'all', fn ($q) => $online === 'online'
                ? $q->whereHas('courierLocation', fn ($l) => $l->where('is_online', true))
                : $q->whereDoesntHave('courierLocation', fn ($l) => $l->where('is_online', true)))
            ->orderBy('id')
            ->get();

        // Duty is derived from the load, which is fresher than the on_duty flag set by pings.
        if ($duty !== 'all') {
            $couriers = $couriers->filter(fn (User $c) => ($c->active_tasks_count > 0) === ($duty === 'on_duty'))->values();
        }

        if (($min = $filters['min_capacity'] ?? null) !== null) {
            $couriers = $couriers->filter(fn (User $c) => ($this->remaining($c) ?? PHP_INT_MAX) >= $min)->values();
        }

        return $couriers;
    }

    /** Free capacity in the courier's own unit (tasks, or units when tasks carry unit_quantity); null = unlimited. */
    public function remaining(User $courier, bool $units = false): ?int
    {
        $capacity = $courier->courierDetail?->capacity;

        if (! $capacity) {
            return null;
        }

        $load = $units ? (int) $courier->active_units : (int) $courier->active_tasks_count;

        return max(0, $capacity - $load);
    }
}
