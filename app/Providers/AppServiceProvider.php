<?php

namespace App\Providers;

use App\Services\Geocoding\FakeGeocoder;
use App\Services\Geocoding\Geocoder;
use App\Services\Geocoding\NominatimGeocoder;
use App\Services\Routing\FallbackRouter;
use App\Services\Routing\HaversineRouter;
use App\Services\Routing\OsrmRouter;
use App\Services\Routing\RoutingService;
use App\Services\Routing\VroomSolver;
use App\Services\Sms\SmsDriver;
use App\Support\CurrentCompany;
use App\View\Components\Icon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentCompany::class);

        $this->app->singleton(SmsDriver::class, function ($app) {
            $driver = config('services.sms.driver');
            $class = config("services.sms.drivers.{$driver}") ?? throw new \InvalidArgumentException("Unknown SMS driver [{$driver}].");

            return $app->make($class);
        });

        $this->app->singleton(Geocoder::class, function () {
            $config = config('services.geocoder');

            return match ($config['driver']) {
                'fake' => new FakeGeocoder,
                default => new NominatimGeocoder($config['nominatim_url'], $config['user_agent'], $config['cache_days']),
            };
        });

        // Routing servers are optional: with no URL configured the built-in estimate and solver run.
        $this->app->singleton(FallbackRouter::class, function () {
            $osrm = array_filter(config('services.routing.osrm', []));

            return new FallbackRouter($osrm ? new OsrmRouter($osrm, config('services.routing.timeout')) : null, new HaversineRouter);
        });

        $this->app->singleton(RoutingService::class, function ($app) {
            $vroom = config('services.routing.vroom_url');

            return new RoutingService($app->make(FallbackRouter::class), $vroom ? new VroomSolver($vroom, config('services.routing.solve_timeout')) : null);
        });
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        JsonResource::withoutWrapping();

        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');
        Blade::component('icon', Icon::class);

        RateLimiter::for('integration', function (Request $request) {
            return Limit::perMinute(600)->by('company:'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('tracking', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Searching by order number + phone digits is guessable in principle, so it gets less room.
        RateLimiter::for('tracking-lookup', fn (Request $request) => $request->filled('order')
            ? [Limit::perMinute(10)->by('order:'.$request->ip()), Limit::perHour(60)->by('order-h:'.$request->ip())]
            : Limit::perMinute(30)->by('code:'.$request->ip()));

        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinute(3)->by('otp:'.$request->input('phone')),
            Limit::perHour(20)->by('otp-ip:'.$request->ip()),
        ]);
    }
}
