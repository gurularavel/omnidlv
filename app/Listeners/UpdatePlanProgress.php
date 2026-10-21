<?php

namespace App\Listeners;

use App\Enums\StatusCategory;
use App\Events\TaskStatusChanged;
use App\Models\Task;
use App\Models\TaskPlan;

/** A plan is in progress from its first started stop and completed when every stop is final. */
class UpdatePlanProgress
{
    public function handle(TaskStatusChanged $event): void
    {
        $task = $event->task;

        if ($task->plan_id === null) {
            return;
        }

        $plan = TaskPlan::withoutGlobalScopes()->find($task->plan_id);

        if ($plan === null || $plan->status_slug === TaskPlan::CANCELLED) {
            return;
        }

        $stops = Task::withoutGlobalScopes()->where('plan_id', $plan->id)->pluck('status_category_slug');

        $status = match (true) {
            $stops->every(fn (StatusCategory $c) => $c->isFinal()) => TaskPlan::COMPLETED,
            $stops->contains(fn (StatusCategory $c) => in_array($c, [StatusCategory::InProgress, StatusCategory::OnDelivery], true) || $c->isFinal()) => TaskPlan::IN_PROGRESS,
            default => TaskPlan::PLANNED,
        };

        if ($status !== $plan->status_slug) {
            $plan->forceFill(['status_slug' => $status])->save();
        }
    }
}
