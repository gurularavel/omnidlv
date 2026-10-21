<?php

namespace App\Services\Webhooks;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * One HTTP attempt for a delivery, and the bookkeeping around it.
 *
 * Signatures: `x-qdelivery-signature` is HMAC-SHA256 of the payload_id (hex), exactly as
 * QDelivery signs, for receivers ported from it. It does not cover the body, so new receivers
 * should check `X-OmniDLV-Signature: v1=<hex>` = HMAC-SHA256("<timestamp>.<body>"), with
 * the timestamp from `X-OmniDLV-Timestamp`, and reject old timestamps to stop replays.
 */
class WebhookSender
{
    /** Seconds to wait before attempt 2, 3, … Six attempts over roughly 3 hours. */
    public const BACKOFF = [30, 120, 600, 1800, 7200];

    public const MAX_ATTEMPTS = 6;

    public function __construct(private readonly WebhookUrlGuard $guard) {}

    /** @return int|null seconds until the next attempt, or null when the delivery is settled */
    public function attempt(WebhookDelivery $delivery): ?int
    {
        if ($delivery->state !== WebhookDelivery::PENDING) {
            return null;
        }

        $endpoint = WebhookEndpoint::withoutGlobalScopes()->find($delivery->webhook_endpoint_id);

        if ($endpoint === null || ! $endpoint->is_active) {
            $this->settle($delivery, WebhookDelivery::FAILED, ['error' => 'Endpoint is disabled.']);

            return null;
        }

        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $delivery->increment('attempts');
        $started = hrtime(true);

        try {
            $target = $this->guard->check($endpoint->url);

            $response = Http::withHeaders($this->headers($endpoint, $delivery, $body))
                ->withBody($body, 'application/json')
                ->timeout(config('services.webhooks.timeout', 10))
                ->connectTimeout(5)
                ->withoutRedirecting()
                ->withOptions($this->pin($target))
                ->post($endpoint->url);

            $status = $response->status();
            $fields = [
                'response_status' => $status,
                'response_body' => Str::limit((string) $response->body(), 2000),
                'duration_ms' => $this->elapsed($started),
                'error' => null,
            ];

            if ($response->successful()) {
                $this->settle($delivery, WebhookDelivery::SUCCESS, $fields + ['delivered_at' => now()]);
                $endpoint->forceFill(['failure_streak' => 0, 'last_success_at' => now()])->save();

                return null;
            }

            // A 4xx other than timeout / rate limit means the receiver rejected the payload;
            // sending the same bytes again will not change its mind.
            $retryable = $status >= 500 || in_array($status, [408, 409, 425, 429], true);

            return $this->failed($delivery, $endpoint, $fields, $retryable);
        } catch (UnsafeWebhookUrl $e) {
            return $this->failed($delivery, $endpoint, ['error' => $e->getMessage(), 'duration_ms' => 0], false);
        } catch (ConnectionException $e) {
            return $this->failed($delivery, $endpoint, ['error' => Str::limit($e->getMessage(), 480), 'duration_ms' => $this->elapsed($started)], true);
        } catch (Throwable $e) {
            report($e);

            return $this->failed($delivery, $endpoint, ['error' => Str::limit($e->getMessage(), 480), 'duration_ms' => $this->elapsed($started)], true);
        }
    }

    public function headers(WebhookEndpoint $endpoint, WebhookDelivery $delivery, string $body): array
    {
        $timestamp = (string) now()->getTimestamp();

        return [
            'User-Agent' => 'OmniDLV-Webhooks/1.0',
            'x-qdelivery-signature' => hash_hmac('sha256', $delivery->payload_id, $endpoint->secret),
            'X-OmniDLV-Event' => $delivery->event,
            'X-OmniDLV-Delivery' => $delivery->payload_id,
            'X-OmniDLV-Timestamp' => $timestamp,
            'X-OmniDLV-Signature' => 'v1='.hash_hmac('sha256', $timestamp.'.'.$body, $endpoint->secret),
        ];
    }

    private function failed(WebhookDelivery $delivery, WebhookEndpoint $endpoint, array $fields, bool $retryable): ?int
    {
        $attempts = $delivery->attempts;

        if ($retryable && $attempts < self::MAX_ATTEMPTS) {
            $delay = self::BACKOFF[$attempts - 1] ?? self::BACKOFF[array_key_last(self::BACKOFF)];
            $delivery->forceFill($fields + ['next_retry_at' => now()->addSeconds($delay)])->save();

            return $delay;
        }

        $this->settle($delivery, WebhookDelivery::FAILED, $fields);

        $endpoint->forceFill(['failure_streak' => $endpoint->failure_streak + 1, 'last_failure_at' => now()]);

        if ($endpoint->failure_streak >= config('services.webhooks.disable_after', 15)) {
            $endpoint->forceFill([
                'is_active' => false,
                'disabled_at' => now(),
                'disabled_reason' => __('webhooks.disabled_reason', ['count' => $endpoint->failure_streak]),
            ]);
        }

        $endpoint->save();

        return null;
    }

    private function settle(WebhookDelivery $delivery, string $state, array $fields): void
    {
        $delivery->forceFill($fields + ['state' => $state, 'next_retry_at' => null])->save();
    }

    /** Connect to the address the guard approved, so DNS cannot change between check and send. */
    private function pin(array $target): array
    {
        if ($target['ips'] === [] || filter_var($target['host'], FILTER_VALIDATE_IP)) {
            return [];
        }

        $ip = $target['ips'][0];
        $ip = str_contains($ip, ':') ? "[{$ip}]" : $ip;

        return ['curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$ip}"]]];
    }

    private function elapsed(int $started): int
    {
        return (int) ((hrtime(true) - $started) / 1_000_000);
    }
}
