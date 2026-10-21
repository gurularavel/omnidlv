<?php

namespace App\Listeners;

use App\Enums\WebhookEvent;
use App\Events\CourierLocationUpdated;
use App\Events\CourierWorkingStatusChanged;
use App\Events\TaskStatusChanged;
use App\Models\User;
use App\Services\Webhooks\WebhookDispatcher;
use App\Services\Webhooks\WebhookPayloads;
use Illuminate\Support\Facades\Cache;

/** Turns domain events into QDelivery-format webhooks. Event discovery maps each handle* method by its type hint. */
class SendWebhooks
{
    public function __construct(
        private readonly WebhookDispatcher $webhooks,
        private readonly WebhookPayloads $payloads,
    ) {}

    public function handleTaskStatus(TaskStatusChanged $event): void
    {
        $this->webhooks->dispatch(
            $event->task->company_id,
            WebhookEvent::TaskStatusChanged,
            fn () => $this->payloads->taskStatusChanged($event->task, $event->from->value),
        );
    }

    /**
     * Location pings arrive every few seconds; a receiver gets at most one per courier per
     * interval (30 s by default), which is plenty to draw a moving marker.
     */
    public function handleLocation(CourierLocationUpdated $event): void
    {
        $interval = (int) config('services.webhooks.location_interval', 30);

        if (! Cache::add("webhook-location:{$event->courierId}", 1, $interval)) {
            return;
        }

        $this->webhooks->dispatch(
            $event->companyId,
            WebhookEvent::CourierLocationUpdated,
            fn () => $this->payloads->courierLocationUpdated(User::query()->findOrFail($event->courierId)),
        );
    }

    public function handleWorkingStatus(CourierWorkingStatusChanged $event): void
    {
        $this->webhooks->dispatch(
            $event->companyId,
            WebhookEvent::CourierWorkingStatusUpdated,
            fn () => $this->payloads->courierWorkingStatusUpdated(User::query()->findOrFail($event->courierId)),
        );
    }
}
