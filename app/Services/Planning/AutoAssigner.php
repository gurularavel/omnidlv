<?php

namespace App\Services\Planning;

use App\Enums\AssignmentType;
use App\Enums\Channel;
use App\Enums\StatusCategory;
use App\Exceptions\DomainException;
use App\Models\Task;
use App\Models\User;
use App\Services\AssignmentService;
use Illuminate\Support\Collection;

/**
 * The capacity_aware and capacity_unaware strategies: pick one courier for one task as soon
 * as it arrives. Candidates come from the task's assignment_options; "balanced" distribution
 * prefers the least loaded courier, "adaptive" the closest one that still has min_capacity
 * free. A task nobody can take stays in Created and is retried by `tasks:assign-pending`.
 */
class AutoAssigner
{
    public function __construct(
        private readonly CourierPool $pool,
        private readonly AssignmentService $assignments,
    ) {}

    public static function handles(AssignmentType $type): bool
    {
        return in_array($type, [AssignmentType::CapacityAware, AssignmentType::CapacityUnaware], true);
    }

    public function assign(Task $task, Channel $channel = Channel::System): ?User
    {
        if (! self::handles($task->assignment_type) || $task->courier_id !== null
            || $task->status_category_slug !== StatusCategory::Created) {
            return null;
        }

        $task->loadMissing('hub');
        $options = (array) $task->assignment_options;
        $aware = $task->assignment_type === AssignmentType::CapacityAware;

        $candidates = $this->pool->find($task->hub, [
            'team_id' => $task->team_id ?? ($options['team_id'] ?? null),
            'shift' => $options['courier_shift_status'] ?? 'working',
            'online' => $options['courier_online_status'] ?? 'all',
            'duty' => match ($options['courier_duty_status'] ?? 'all') {
                'idle' => 'idle', 'on_duty' => 'on_duty', default => 'all',
            },
            'min_capacity' => $options['min_capacity'] ?? null,
        ]);

        foreach ($this->rank($candidates, $task, $options['distribution'] ?? 'balanced') as $courier) {
            if ($aware && ! $this->hasRoom($courier, $task)) {
                continue;
            }

            try {
                $this->assignments->assign($task, $courier, $channel, ignoreCapacity: ! $aware);

                return $courier;
            } catch (DomainException) {
                // Lost a race for this courier's last slot; try the next one.
                $task->refresh();
            }
        }

        return null;
    }

    private function hasRoom(User $courier, Task $task): bool
    {
        $units = $task->unit_quantity > 0;
        $free = $this->pool->remaining($courier, $units);

        return $free === null || $free >= ($units ? $task->unit_quantity : 1);
    }

    /** @return Collection<int, User> */
    private function rank($candidates, Task $task, string $distribution): Collection
    {
        // Where the courier collects the parcel: the hub, the sender, or the customer for a pickup.
        $target = $task->originPoint() ?? $task->hub?->point();

        $distance = function (User $c) use ($target) {
            $at = $c->courierLocation?->point();

            return $at && $target ? $at->distanceTo($target) : PHP_FLOAT_MAX;
        };

        return collect($candidates)->sortBy(
            $distribution === 'adaptive'
                ? [fn ($a, $b) => $distance($a) <=> $distance($b), fn ($a, $b) => $a->active_tasks_count <=> $b->active_tasks_count]
                : [fn ($a, $b) => $a->active_tasks_count <=> $b->active_tasks_count, fn ($a, $b) => $distance($a) <=> $distance($b)]
        )->values();
    }
}
