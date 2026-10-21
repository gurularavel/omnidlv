<?php

namespace App\Services\Routing;

use App\Support\GeoPoint;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Entry point for distance and route optimisation. Uses OSRM / VROOM when configured and
 * reachable, and the built-in estimate and solver otherwise, so planning never stops because
 * a routing server is down.
 */
class RoutingService
{
    /** QDelivery's profile names map onto the OSRM profiles we run. */
    public const PROFILES = [
        'car' => 'car', 'az-car' => 'car', 'tr-car' => 'car',
        'truck' => 'truck', 'az-truck' => 'truck', 'tr-truck' => 'truck', 'tr-truck-fsmb' => 'truck',
    ];

    public function __construct(
        private readonly FallbackRouter $router,
        private readonly ?VroomSolver $vroom,
    ) {}

    public static function profile(?string $name): string
    {
        return self::PROFILES[$name ?? 'car'] ?? 'car';
    }

    /** @return array{distance: float, duration: int, type: string} */
    public function distance(GeoPoint $from, GeoPoint $to, string $strategy = 'basic', string $profile = 'car'): array
    {
        if ($strategy !== 'accurate') {
            // Bird's-eye, as QDelivery documents it: no detour factor.
            $meters = $from->distanceTo($to);

            return ['distance' => round($meters, 1), 'duration' => (int) round($meters / (25 / 3.6)), 'type' => 'haversine'];
        }

        $route = $this->router->route([$from, $to], self::profile($profile));

        return ['distance' => (float) $route['distance'], 'duration' => $route['duration'], 'type' => $route['source']];
    }

    public function route(array $points, string $profile = 'car'): array
    {
        return $this->router->route($points, self::profile($profile));
    }

    public function solve(array $problem): array
    {
        foreach ($problem['vehicles'] as &$vehicle) {
            $vehicle['profile'] = self::profile($vehicle['profile'] ?? 'car');
        }
        unset($vehicle);

        if ($this->vroom !== null) {
            try {
                return $this->vroom->solve($problem) + ['solver' => $this->vroom->name()];
            } catch (Throwable $e) {
                Log::warning('VROOM unavailable, using the built-in solver', ['error' => $e->getMessage()]);
            }
        }

        return (new InsertionSolver($this->router))->solve($problem);
    }
}
