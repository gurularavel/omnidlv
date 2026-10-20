<?php

namespace App\Services;

use App\Enums\Channel;
use App\Enums\Role;
use App\Enums\StatusCategory;
use App\Exceptions\DomainException;
use App\Models\CourierDetail;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Manual and claim-based courier assignment. Automatic strategies (capacity_aware, slot …)
 * build on assign(), which is the single place that enforces capacity and atomicity.
 */
class AssignmentService
{
    public function __construct(private readonly StatusMachine $statuses) {}

    public function assign(Task $task, User $courier, Channel $channel, ?User $actor = null, bool $ignoreCapacity = false): Task
    {
        if ($courier->roleIn($task->company_id) !== Role::Courier) {
            throw new DomainException(__('tasks.errors.not_a_courier'), 'not_a_courier');
        }

        return DB::transaction(function () use ($task, $courier, $channel, $actor, $ignoreCapacity) {
            // Lock the courier row: concurrent assignments to one courier queue up here, so the
            // capacity check below cannot be passed by two transactions at once.
            User::query()->whereKey($courier->id)->lockForUpdate()->first();

            if (! $ignoreCapacity) {
                $this->guardCapacity($task, $courier);
            }

            $task->forceFill([
                'courier_id' => $courier->id,
                'claimed_at' => now(),
                'team_id' => $task->team_id ?? $courier->teams()->value('teams.id'),
            ])->save();

            return $this->statuses->toCategory($task, StatusCategory::Assigned, $channel, $actor);
        });
    }

    /**
     * Pool claim by the courier. The conditional UPDATE is the lock: only one courier can
     * move a task from courier_id NULL to themselves.
     */
    public function claim(Task $task, User $courier): Task
    {
        return DB::transaction(function () use ($task, $courier) {
            User::query()->whereKey($courier->id)->lockForUpdate()->first();
            $this->guardCapacity($task, $courier, pool: true);

            $claimed = Task::query()
                ->whereKey($task->id)
                ->whereNull('courier_id')
                ->where('status_category_slug', StatusCategory::Pool->value)
                ->update(['courier_id' => $courier->id, 'claimed_at' => now()]);

            if ($claimed === 0) {
                throw new DomainException(__('tasks.errors.already_claimed'), 'already_claimed');
            }

            $task->refresh();

            return $this->statuses->toCategory($task, StatusCategory::Assigned, Channel::Courier, $courier);
        });
    }

    public function unassign(Task $task, Channel $channel, ?User $actor = null, bool $toPool = false): Task
    {
        return $this->statuses->toCategory(
            $task, $toPool ? StatusCategory::Pool : StatusCategory::Created, $channel, $actor,
        );
    }

    private function guardCapacity(Task $task, User $courier, bool $pool = false): void
    {
        $detail = CourierDetail::query()->where('user_id', $courier->id)->first();
        $limit = $pool ? ($detail?->pool_capacity ?? $detail?->capacity) : $detail?->capacity;

        if ($limit === null || $limit === 0) {
            return; // null/0 = unlimited, as in QDelivery
        }

        $useUnits = $task->unit_quantity > 0;
        $load = Task::query()
            ->where('courier_id', $courier->id)
            ->whereKeyNot($task->id)
            ->active()
            ->sum($useUnits ? 'unit_quantity' : DB::raw('1'));

        $needed = $useUnits ? $task->unit_quantity : 1;

        if ($load + $needed > $limit) {
            throw new DomainException(__('tasks.errors.capacity_exceeded', ['limit' => $limit]), 'capacity_exceeded');
        }
    }
}
