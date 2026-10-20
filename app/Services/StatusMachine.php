<?php

namespace App\Services;

use App\Enums\Channel;
use App\Enums\StatusCategory;
use App\Events\TaskStatusChanged;
use App\Exceptions\DomainException;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Support\GeoPoint;
use Illuminate\Support\Facades\DB;

/**
 * The only place a task's status changes. Validates the transition, keeps the denormalised
 * status columns in sync, writes the log row and fires TaskStatusChanged after commit.
 */
class StatusMachine
{
    public function transition(
        Task $task,
        Status $status,
        Channel $channel,
        ?User $actor = null,
        ?string $notes = null,
        ?GeoPoint $at = null,
        bool $force = false,
    ): Task {
        return DB::transaction(function () use ($task, $status, $channel, $actor, $notes, $at, $force) {
            // Re-read under a row lock so two concurrent updates cannot both pass validation.
            $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            $from = $locked->status_category_slug;
            $to = $status->category();

            if (! $force) {
                $this->guard($locked, $from, $to, $channel);
            }

            $previousStatusId = $locked->status_id;

            $locked->forceFill([
                'status_id' => $status->id,
                'status_category_slug' => $to,
                'status_at' => now(),
            ]);

            $this->applySideEffects($locked, $from, $to);
            $locked->save();

            $locked->statusLogs()->create([
                'status_id' => $status->id,
                'status_category_slug' => $to,
                'user_id' => $actor?->id,
                'courier_id' => $locked->courier_id,
                'role_slug' => $actor?->roleIn($locked->company_id)?->value ?? ($channel === Channel::Api ? 'integration' : null),
                'channel' => $channel,
                'notes' => $notes !== null ? mb_substr($notes, 0, 255) : null,
                'lat' => $at?->lat,
                'lng' => $at?->lng,
            ]);

            $task->setRawAttributes($locked->getAttributes(), true);
            $task->unsetRelation('status')->unsetRelation('statusLogs');

            if ($previousStatusId !== $status->id) {
                DB::afterCommit(fn () => TaskStatusChanged::dispatch($locked->fresh(), $from, $channel));
            }

            return $task;
        });
    }

    public function toCategory(Task $task, StatusCategory $category, Channel $channel, ?User $actor = null, ?string $notes = null, ?GeoPoint $at = null): Task
    {
        return $this->transition($task, Status::defaultFor($category), $channel, $actor, $notes, $at);
    }

    /** Admin-only escape hatch out of a final status. Always logged. */
    public function reopen(Task $task, User $actor, string $notes): Task
    {
        return $this->transition($task, Status::defaultFor(StatusCategory::Created), Channel::Panel, $actor, $notes, force: true);
    }

    private function guard(Task $task, StatusCategory $from, StatusCategory $to, Channel $channel): void
    {
        if ($from->isFinal() && $from !== $to) {
            throw new DomainException(__('tasks.errors.final_status'), 'task_final');
        }

        if ($from !== $to && ! $from->canMoveTo($to)) {
            throw new DomainException(__('tasks.errors.invalid_transition', [
                'from' => $from->label(), 'to' => $to->label(),
            ]), 'invalid_transition');
        }

        if ($task->is_locked && $channel === Channel::Courier) {
            throw new DomainException(__('tasks.errors.locked'), 'task_locked');
        }

        if (in_array($to, StatusCategory::active(), true) && $task->courier_id === null) {
            throw new DomainException(__('tasks.errors.courier_required'), 'courier_required');
        }
    }

    private function applySideEffects(Task $task, StatusCategory $from, StatusCategory $to): void
    {
        if (in_array($to, [StatusCategory::Created, StatusCategory::Pool], true)) {
            $task->courier_id = null;
            $task->claimed_at = null;
        }

        if ($to === StatusCategory::InProgress && $task->started_at === null) {
            $task->started_at = now();
        }

        if ($to->isFinal()) {
            $task->ended_at = now();
            $task->was_delayed = $task->ends_at !== null && now()->greaterThan($task->ends_at);
            $task->was_finalized_duly = $to === StatusCategory::Completed && ! $task->was_delayed;
        }
    }
}
