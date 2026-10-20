<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // OSM's public tiles suit development and light use only; production traffic should point
    // MAP_TILES at a self-hosted or paid tile server (see docs/DEVOPS-TAPSIRIQLAR.md).
    'map' => [
        'tiles' => env('MAP_TILES', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('MAP_ATTRIBUTION', '&copy; OpenStreetMap contributors'),
    ],

    // SMS: "demo" sends nothing and only logs (panel → SMS log). A real provider such as
    // Postgöyərçini is added as a class implementing App\Services\Sms\SmsDriver and listed here.
    'sms' => [
        'driver' => env('SMS_DRIVER', 'demo'),
        'drivers' => [
            'demo' => App\Services\Sms\DemoSmsDriver::class,
        ],
        'sender' => env('SMS_SENDER', 'OmniDLV'),
    ],

    'geocoder' => [
        'driver' => env('GEOCODER', 'nominatim'),
        'nominatim_url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'OmniDLV/1.0 (+'.env('APP_URL').')'),
        'cache_days' => (int) env('GEOCODER_CACHE_DAYS', 30),
    ],

];
