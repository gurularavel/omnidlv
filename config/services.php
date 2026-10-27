<?php

use App\Services\Push\LogPushDriver;
use App\Services\Sms\DemoSmsDriver;

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
            'demo' => DemoSmsDriver::class,
        ],
        'sender' => env('SMS_SENDER', 'OmniDLV'),
    ],

    // Self-hosted OSRM (one URL per profile) and vroom-express. Empty = built-in straight-line
    // estimate and insertion solver (see App\Services\Routing).
    'routing' => [
        'osrm' => [
            'car' => env('OSRM_CAR_URL'),
            'truck' => env('OSRM_TRUCK_URL'),
        ],
        'vroom_url' => env('VROOM_URL'),
        'timeout' => (int) env('OSRM_TIMEOUT', 10),
        'solve_timeout' => (int) env('VROOM_TIMEOUT', 60),
    ],

    // Push to the native courier app. "log" only writes to the log; FCM comes with Firebase.
    'push' => [
        'driver' => env('PUSH_DRIVER', 'log'),
        'drivers' => [
            'log' => LogPushDriver::class,
        ],
    ],

    'webhooks' => [
        // Local development only: lets a webhook hit http:// and LAN/loopback receivers.
        'allow_http' => (bool) env('WEBHOOKS_ALLOW_HTTP', false),
        'allow_private_networks' => (bool) env('WEBHOOKS_ALLOW_PRIVATE_NETWORKS', false),
        'timeout' => (int) env('WEBHOOKS_TIMEOUT', 10),
        // An endpoint is switched off after this many deliveries in a row gave up.
        'disable_after' => (int) env('WEBHOOKS_DISABLE_AFTER', 15),
        // Minimum seconds between courier_location_updated webhooks for one courier.
        'location_interval' => (int) env('WEBHOOKS_LOCATION_INTERVAL', 30),
    ],

    'geocoder' => [
        'driver' => env('GEOCODER', 'nominatim'),
        'nominatim_url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'OmniDLV/1.0 (+'.env('APP_URL').')'),
        'cache_days' => (int) env('GEOCODER_CACHE_DAYS', 30),
    ],

    // Shown on the FAQ and legal pages when set.
    'support_email' => env('SUPPORT_EMAIL'),

];
