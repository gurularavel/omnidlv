<?php

namespace App\Services\Push;

use App\Models\CourierDevice;
use Illuminate\Support\Facades\Log;

/** Until Firebase is set up: pushes are written to the log instead of sent. */
class LogPushDriver implements PushDriver
{
    public function send(CourierDevice $device, string $title, string $body, array $data): bool
    {
        Log::info("[push:log] user {$device->user_id} ({$device->platform}): {$title} — {$body}", $data);

        return true;
    }
}
