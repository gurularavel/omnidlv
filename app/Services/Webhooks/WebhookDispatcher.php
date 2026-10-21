<?php

namespace App\Services\Webhooks;

use App\Enums\WebhookEvent;
use App\Jobs\SendWebhook;
use App\Models\Company;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\CurrentCompany;
use Closure;
use Illuminate\Support\Str;

/**
 * Queues one delivery per subscribed endpoint. The payload is built once, only when someone
 * listens, and every endpoint gets the same payload_id so a receiver can drop duplicates.
 */
class WebhookDispatcher
{
    public function __construct(private readonly CurrentCompany $tenant) {}

    /** @param  Closure(): array  $build */
    public function dispatch(Company|int $company, WebhookEvent $event, Closure $build): int
    {
        $company = $company instanceof Company ? $company : Company::query()->find($company);

        if ($company === null) {
            return 0;
        }

        $endpoints = WebhookEndpoint::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->listeningTo($event)
            ->get();

        if ($endpoints->isEmpty()) {
            return 0;
        }

        $payload = [
            'event' => $event->value,
            'payload_id' => (string) Str::uuid(),
            ...$this->tenant->run($company, $build),
        ];

        foreach ($endpoints as $endpoint) {
            $this->queue($endpoint, $payload);
        }

        return $endpoints->count();
    }

    public function queue(WebhookEndpoint $endpoint, array $payload): WebhookDelivery
    {
        $delivery = WebhookDelivery::withoutGlobalScopes()->forceCreate([
            'company_id' => $endpoint->company_id,
            'webhook_endpoint_id' => $endpoint->id,
            'event' => $payload['event'],
            'payload_id' => $payload['payload_id'],
            'payload' => $payload,
        ]);

        SendWebhook::dispatch($delivery->id)->afterCommit();

        return $delivery;
    }

    /** Sends a past delivery again with the same payload_id, as a new log row. */
    public function redeliver(WebhookDelivery $delivery): WebhookDelivery
    {
        return $this->queue($delivery->endpoint, $delivery->payload);
    }

    public function test(WebhookEndpoint $endpoint, WebhookPayloads $payloads): WebhookDelivery
    {
        return $this->queue($endpoint, [
            'event' => WebhookEvent::Test->value,
            'payload_id' => (string) Str::uuid(),
            ...$payloads->test(),
        ]);
    }
}
