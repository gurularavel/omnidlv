<?php

namespace App\Services\Routing;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Road network when it answers, straight-line estimate when it does not. The result's
 * "source" says which one was used, so callers can tell an ETA from a guess.
 */
class FallbackRouter implements Router
{
    public function __construct(
        private readonly ?OsrmRouter $osrm,
        private readonly HaversineRouter $haversine,
    ) {}

    public function table(array $points, string $profile = 'car'): array
    {
        return $this->try(fn (Router $r) => $r->table($points, $profile), $profile);
    }

    public function route(array $points, string $profile = 'car'): array
    {
        return $this->try(fn (Router $r) => $r->route($points, $profile), $profile);
    }

    public function name(): string
    {
        return $this->osrm?->name() ?? $this->haversine->name();
    }

    public function hasRoadNetwork(string $profile = 'car'): bool
    {
        return $this->osrm?->supports($profile) ?? false;
    }

    private function try(callable $call, string $profile): array
    {
        if ($this->osrm !== null && ($this->osrm->supports($profile) || $this->osrm->supports('car'))) {
            try {
                return $call($this->osrm);
            } catch (Throwable $e) {
                Log::warning('Routing falls back to straight-line estimate', ['error' => $e->getMessage()]);
            }
        }

        return $call($this->haversine);
    }
}
