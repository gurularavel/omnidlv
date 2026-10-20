<?php

namespace App\Support;

/**
 * SaaS plans mirrored from QDelivery pricing (Start / Scale / Enterprise, priced per courier).
 * Each plan includes every feature of the plans before it.
 */
final class Plans
{
    public const TRIAL_DAYS = 14;

    private const PLANS = [
        'start' => [
            'price_monthly' => 35,
            'price_yearly' => 30,
            'history_days' => 90,
            'features' => [
                'route_optimization', 'live_tracking', 'status_notifications',
                'spreadsheet_import', 'api', 'webhooks',
            ],
        ],
        'scale' => [
            'price_monthly' => 45,
            'price_yearly' => 39,
            'history_days' => 365,
            'features' => [
                'pod', 'barcode', 'eta_notifications', 'completion_rules',
                'order_batching', 'customer_feedback',
            ],
        ],
        'enterprise' => [
            'price_monthly' => null,
            'price_yearly' => null,
            'history_days' => null,
            'features' => [
                'advanced_optimization', 'multi_brand', 'white_label_tracking', 'sso',
            ],
        ],
    ];

    public static function slugs(): array
    {
        return array_keys(self::PLANS);
    }

    public static function get(string $slug): array
    {
        $plan = self::PLANS[$slug] ?? self::PLANS['start'];
        $plan['slug'] = array_key_exists($slug, self::PLANS) ? $slug : 'start';
        $plan['all_features'] = self::features($plan['slug']);

        return $plan;
    }

    /** @return list<string> */
    public static function features(string $slug): array
    {
        $features = [];

        foreach (self::PLANS as $planSlug => $plan) {
            $features = [...$features, ...$plan['features']];

            if ($planSlug === $slug) {
                return $features;
            }
        }

        return self::PLANS['start']['features'];
    }

    public static function allows(string $slug, string $feature): bool
    {
        return in_array($feature, self::features($slug), true);
    }
}
