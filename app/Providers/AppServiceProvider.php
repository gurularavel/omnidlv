<?php

namespace App\Providers;

use App\Services\Geocoding\FakeGeocoder;
use App\Services\Geocoding\Geocoder;
use App\Services\Geocoding\NominatimGeocoder;
use App\Support\CurrentCompany;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentCompany::class);

        $this->app->singleton(Geocoder::class, function () {
            $config = config('services.geocoder');

            return match ($config['driver']) {
                'fake' => new FakeGeocoder,
                default => new NominatimGeocoder($config['nominatim_url'], $config['user_agent'], $config['cache_days']),
            };
        });
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        JsonResource::withoutWrapping();

        RateLimiter::for('integration', function (Request $request) {
            return Limit::perMinute(600)->by('company:'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('tracking', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinute(3)->by('otp:'.$request->input('phone')),
            Limit::perHour(20)->by('otp-ip:'.$request->ip()),
        ]);
    }
}
