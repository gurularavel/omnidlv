<?php

namespace App\Services\Push;

use App\Models\CourierDevice;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Push messages to the courier's phones. The "log" driver only writes them to the log, like
 * the demo SMS driver; FCM is added as another PushDriver once the Firebase project exists.
 */
class PushService
{
    public function __construct(private readonly PushDriver $driver) {}

    /** @param  array<string, string>  $data  delivered to the app with the message (e.g. task_id) */
    public function send(User $user, string $title, string $body, array $data = []): int
    {
        $devices = CourierDevice::query()->where('user_id', $user->id)->whereNotNull('push_token')->get();
        $sent = 0;

        foreach ($devices as $device) {
            try {
                if ($this->driver->send($device, $title, $body, $data)) {
                    $sent++;
                } else {
                    // The provider says the token is dead (app removed): forget it.
                    $device->forceFill(['push_token' => null])->save();
                }
            } catch (Throwable $e) {
                Log::warning('Push failed', ['device' => $device->id, 'error' => $e->getMessage()]);
            }
        }

        return $sent;
    }
}
