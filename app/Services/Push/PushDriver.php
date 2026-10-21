<?php

namespace App\Services\Push;

use App\Models\CourierDevice;

interface PushDriver
{
    /** @return bool false when the token is no longer valid */
    public function send(CourierDevice $device, string $title, string $body, array $data): bool;
}
