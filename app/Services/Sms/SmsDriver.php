<?php

namespace App\Services\Sms;

use App\Models\SmsMessage;

/**
 * One SMS provider. Implementations send $message and return the provider's message id,
 * or throw SmsException. Adding a provider (e.g. Postgöyərçini) means one class here and
 * one entry in config/services.php 'sms.drivers'.
 */
interface SmsDriver
{
    public function name(): string;

    /**
     * $body is the real text. $message->body may be a redacted copy (sign-in codes are never
     * stored readable), so drivers must send $body, not $message->body.
     *
     * @throws SmsException
     */
    public function send(SmsMessage $message, string $body): ?string;
}
