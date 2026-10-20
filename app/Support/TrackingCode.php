<?php

namespace App\Support;

use App\Models\Task;

final class TrackingCode
{
    // No 0/O/1/I/L so codes survive being read out over the phone.
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function generate(int $length = 12): string
    {
        do {
            $code = 'OD';
            for ($i = 0; $i < $length; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (Task::withoutGlobalScopes()->where('tracking_code', $code)->exists());

        return $code;
    }
}
