<?php

namespace App\Listeners;

use App\Enums\Channel;
use App\Enums\StatusCategory;
use App\Events\TaskStatusChanged;
use App\Services\Push\PushService;

/**
 * Tells the courier's phone when dispatch gives them a task or takes one away. Changes the
 * courier made themselves need no push.
 */
class NotifyCourierOfTask
{
    public function __construct(private readonly PushService $push) {}

    public function handle(TaskStatusChanged $event): void
    {
        $task = $event->task;

        if ($task->courier_id === null || $event->channel === Channel::Courier) {
            return;
        }

        $courier = $task->courier;
        $locale = $courier->locale;
        $data = ['task_id' => (string) $task->id, 'tracking_code' => $task->tracking_code];

        match ($task->status_category_slug) {
            StatusCategory::Assigned => $this->push->send($courier,
                __('courier.push.assigned_title', [], $locale),
                __('courier.push.assigned_body', ['address' => (string) $task->destination_address], $locale),
                [...$data, 'type' => 'task_assigned']),
            StatusCategory::Cancelled => $this->push->send($courier,
                __('courier.push.cancelled_title', [], $locale),
                __('courier.push.cancelled_body', ['code' => $task->tracking_code], $locale),
                [...$data, 'type' => 'task_cancelled']),
            default => null,
        };
    }
}
