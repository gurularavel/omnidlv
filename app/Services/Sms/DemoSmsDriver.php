<?php

namespace App\Services\Sms;

use App\Models\SmsMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sends nothing. Every message is kept in sms_messages (visible in the panel's SMS log) and
 * written to the application log, so flows that need an SMS can be tested end to end.
 */
class DemoSmsDriver implements SmsDriver
{
    public function name(): string
    {
        return 'demo';
    }

    public function send(SmsMessage $message, string $body): ?string
    {
        // Logs the stored (possibly redacted) text, never a live sign-in code.
        Log::info('[sms:demo] '.$message->to.' — '.$message->body);

        return 'demo-'.Str::lower(Str::random(10));
    }
}
