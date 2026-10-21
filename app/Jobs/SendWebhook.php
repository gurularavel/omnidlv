<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Retries are decided by WebhookSender (it knows what is retryable) and scheduled as a new
 * delayed job, so a slow receiver never holds a worker between attempts.
 */
class SendWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(WebhookSender $sender): void
    {
        $delivery = WebhookDelivery::withoutGlobalScopes()->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        $retryIn = $sender->attempt($delivery);

        if ($retryIn !== null) {
            self::dispatch($this->deliveryId)->delay($retryIn);
        }
    }
}
