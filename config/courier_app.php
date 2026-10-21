<?php

/*
 | Native courier app (Flutter, repo gurularavel/omnidlv-courier). Served to the app by
 | GET /api/courier/v1/app-config, so these change without shipping a new build.
 */
return [
    // Builds below min_version are told to update before they can work.
    'min_version' => env('COURIER_APP_MIN_VERSION', '1.0.0'),
    'latest_version' => env('COURIER_APP_LATEST_VERSION', '1.0.0'),

    'store_urls' => [
        'android' => env('COURIER_APP_PLAY_URL'),
        'ios' => env('COURIER_APP_APPSTORE_URL'),
    ],

    // Sign-in tokens last this long; the courier signs in again after that.
    'token_days' => (int) env('COURIER_APP_TOKEN_DAYS', 180),

    // GPS cadence the app applies (seconds / meters).
    'location' => [
        'on_task' => ['interval' => 15, 'distance' => 30],
        'idle' => ['interval' => 60, 'distance' => 100],
        'batch_size' => 50,
    ],

    /*
     | App Store / Google Play reviewers cannot receive our SMS. This one number signs in with
     | this fixed code and gets no SMS. Point it at a courier in a demo company only, and leave
     | both empty when no review is running.
     */
    'review' => [
        'phone' => env('COURIER_REVIEW_PHONE'),
        'code' => env('COURIER_REVIEW_CODE'),
    ],

    'privacy_url' => env('COURIER_APP_PRIVACY_URL'),
    'support_phone' => env('COURIER_APP_SUPPORT_PHONE'),
];
