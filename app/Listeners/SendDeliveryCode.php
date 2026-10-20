<?php

namespace App\Listeners;

use App\Enums\StatusCategory;
use App\Events\TaskStatusChanged;
use App\Models\Task;
use App\Services\Sms\SmsService;
use App\Support\CurrentCompany;

/**
 * When a task that needs an SMS delivery code goes out for delivery, the recipient gets the
 * code and the tracking link, once.
 */
class SendDeliveryCode
{
    public function __construct(private readonly SmsService $sms) {}

    public function handle(TaskStatusChanged $event): void
    {
        $task = $event->task;

        if ($task->status_category_slug !== StatusCategory::OnDelivery
            || ! $task->requires_delivery_code
            || $task->delivery_code === null
            || $task->delivery_code_sent_at !== null
            || $task->destination_phone === null) {
            return;
        }

        $company = $task->company;

        app(CurrentCompany::class)->run($company, function () use ($task, $company) {
            // Claim the send first so a duplicate event cannot text the code twice.
            $claimed = Task::query()->whereKey($task->id)->whereNull('delivery_code_sent_at')->update(['delivery_code_sent_at' => now()]);

            if ($claimed === 0) {
                return;
            }

            $this->sms->send(
                $task->destination_phone,
                __('courier.delivery_code_sms', [
                    'company' => $company->short_name ?? $company->name,
                    'code' => $task->delivery_code,
                    'url' => route('tracking.show', $task->tracking_code),
                ], $company->locale),
                'delivery_code',
                $company,
                $task,
            );
        });
    }
}
