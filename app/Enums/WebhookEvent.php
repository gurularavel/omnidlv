<?php

namespace App\Enums;

/** Event names match QDelivery's webhooks; `test` is ours, sent from the panel's "Send test". */
enum WebhookEvent: string
{
    case TaskStatusChanged = 'task_status_changed';
    case CourierLocationUpdated = 'courier_location_updated';
    case CourierWorkingStatusUpdated = 'courier_working_status_updated';
    case Test = 'test';

    /** Events a company can subscribe to. */
    public static function subscribable(): array
    {
        return [self::TaskStatusChanged, self::CourierLocationUpdated, self::CourierWorkingStatusUpdated];
    }

    public function label(): string
    {
        return __('webhooks.events.'.$this->value);
    }

    public function description(): string
    {
        return __('webhooks.event_help.'.$this->value);
    }
}
