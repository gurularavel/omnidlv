<?php

namespace App\Support;

use App\Models\Plan;
use Illuminate\Support\Facades\Cache;

/**
 * SaaS plans (Start / Scale / Enterprise by default, priced per courier). They live in the
 * plans table and are edited in the platform admin; reads go through a shared cache that is
 * cleared whenever a plan changes, so queue workers see edits too.
 */
final class Plans
{
    public const TRIAL_DAYS = 14;

    /** Features the code knows how to gate. A plan lists the ones it includes. */
    public const FEATURES = [
        'route_optimization', 'live_tracking', 'status_notifications', 'spreadsheet_import', 'api', 'webhooks',
        'pod', 'barcode', 'eta_notifications', 'completion_rules', 'order_batching', 'customer_feedback',
        'advanced_optimization', 'multi_brand', 'white_label_tracking', 'sso',
    ];

    private const CACHE_KEY = 'plans:v1';

    /** @return array<string, array> keyed by slug, in display order */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Plan::query()
            ->orderBy('sort')->orderBy('id')->get()
            ->mapWithKeys(fn (Plan $plan) => [$plan->slug => [
                'slug' => $plan->slug,
                'name' => $plan->name,
                'price_monthly' => $plan->price_monthly,
                'price_yearly' => $plan->price_yearly,
                'history_days' => $plan->history_days,
                'max_couriers' => $plan->max_couriers,
                'features' => array_values($plan->features ?? []),
                'is_active' => $plan->is_active,
            ]])
            ->all());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function slugs(): array
    {
        return array_keys(self::all());
    }

    /** Unknown slugs fall back to the first plan, so a company never ends up without limits. */
    public static function get(string $slug): array
    {
        $plans = self::all();
        $plan = $plans[$slug] ?? reset($plans) ?: [
            'slug' => $slug, 'name' => ucfirst($slug), 'price_monthly' => null, 'price_yearly' => null,
            'history_days' => null, 'max_couriers' => null, 'features' => [], 'is_active' => false,
        ];
        $plan['all_features'] = $plan['features'];

        return $plan;
    }

    /** @return list<string> */
    public static function features(string $slug): array
    {
        return self::get($slug)['features'];
    }

    public static function allows(string $slug, string $feature): bool
    {
        return in_array($feature, self::features($slug), true);
    }
}
