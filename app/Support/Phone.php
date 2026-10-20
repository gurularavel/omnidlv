<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

final class Phone
{
    /** Returns the number in E.164 (+994501234567), or null when it is not a valid number. */
    public static function normalize(?string $phone, ?string $country = 'AZ'): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $parsed = $util->parse($phone, strtoupper($country ?: 'AZ'));
        } catch (NumberParseException) {
            return null;
        }

        return $util->isValidNumber($parsed) ? $util->format($parsed, PhoneNumberFormat::E164) : null;
    }

    public static function regionOf(string $e164): ?string
    {
        $util = PhoneNumberUtil::getInstance();

        try {
            return $util->getRegionCodeForNumber($util->parse($e164));
        } catch (NumberParseException) {
            return null;
        }
    }

    /** +994501234567 → +99450***4567, for public pages and archives. */
    public static function mask(?string $phone): ?string
    {
        if ($phone === null || strlen($phone) < 10) {
            return $phone;
        }

        return substr($phone, 0, 6).str_repeat('*', strlen($phone) - 10).substr($phone, -4);
    }
}
