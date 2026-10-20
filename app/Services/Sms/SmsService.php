<?php

namespace App\Services\Sms;

use App\Models\Company;
use App\Models\SmsMessage;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

/**
 * Every outgoing SMS goes through here: it is stored first, then handed to the configured
 * driver, so the log is complete even when a provider fails.
 */
class SmsService
{
    public function __construct(private readonly SmsDriver $driver) {}

    /**
     * @param  string|null  $secret  a value inside $body (e.g. a sign-in code) that the log must
     *                               not keep; it is masked in the stored copy only.
     */
    public function send(string $to, string $body, string $purpose, ?Company $company = null, ?Task $task = null, ?string $secret = null): SmsMessage
    {
        $message = SmsMessage::query()->create([
            'company_id' => $company?->id ?? $task?->company_id,
            'task_id' => $task?->id,
            'to' => $to,
            'purpose' => $purpose,
            'body' => $secret !== null ? str_replace($secret, str_repeat('•', mb_strlen($secret)), $body) : $body,
            'driver' => $this->driver->name(),
            'status' => 'queued',
        ]);

        try {
            $message->forceFill([
                'provider_id' => $this->driver->send($message, $body),
                'status' => 'sent',
                'sent_at' => now(),
            ])->save();
        } catch (SmsException $e) {
            $message->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
            Log::warning('SMS failed', ['id' => $message->id, 'error' => $e->getMessage()]);
        }

        return $message;
    }

    public function isDemo(): bool
    {
        return $this->driver instanceof DemoSmsDriver;
    }
}
