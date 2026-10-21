<?php

namespace App\Services\Routing;

use App\Support\GeoPoint;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Self-hosted OSRM. One OSRM process serves one profile, so each profile has its own base URL
 * (services.routing.osrm.<profile>). Any failure throws; FallbackRouter decides what then.
 */
class OsrmRouter implements Router
{
    public function __construct(private readonly array $urls, private readonly int $timeout = 10) {}

    public function table(array $points, string $profile = 'car'): array
    {
        $json = $this->get($profile, 'table', $points, ['annotations' => 'duration,distance']);

        return [
            'durations' => array_map(fn ($row) => array_map(fn ($v) => (int) round($v ?? PHP_INT_MAX / 4), $row), $json['durations']),
            'distances' => array_map(fn ($row) => array_map(fn ($v) => (int) round($v ?? PHP_INT_MAX / 4), $row), $json['distances']),
            'source' => $this->name(),
        ];
    }

    public function route(array $points, string $profile = 'car'): array
    {
        $json = $this->get($profile, 'route', $points, ['overview' => 'simplified', 'geometries' => 'geojson']);
        $route = $json['routes'][0] ?? throw new RuntimeException('OSRM returned no route');

        return [
            'distance' => (int) round($route['distance']),
            'duration' => (int) round($route['duration']),
            // GeoJSON is [lng, lat]; the rest of the app draws [lat, lng].
            'geometry' => array_map(fn ($c) => [round($c[1], 6), round($c[0], 6)], $route['geometry']['coordinates']),
            'source' => $this->name(),
        ];
    }

    public function name(): string
    {
        return 'osrm';
    }

    public function supports(string $profile): bool
    {
        return ! empty($this->urls[$profile]);
    }

    private function get(string $profile, string $service, array $points, array $query): array
    {
        $base = $this->urls[$profile] ?? $this->urls['car'] ?? null;

        if (! $base) {
            throw new RuntimeException("No OSRM server for profile {$profile}");
        }

        $coords = implode(';', array_map(fn (GeoPoint $p) => $p->lng.','.$p->lat, $points));
        $response = Http::timeout($this->timeout)->get(rtrim($base, '/')."/{$service}/v1/driving/{$coords}", $query);
        $json = $response->json();

        if (! $response->successful() || ($json['code'] ?? null) !== 'Ok') {
            throw new RuntimeException('OSRM '.$service.' failed: '.($json['message'] ?? $response->status()));
        }

        return $json;
    }
}
