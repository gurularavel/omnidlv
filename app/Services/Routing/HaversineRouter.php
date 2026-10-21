<?php

namespace App\Services\Routing;

use App\Support\GeoPoint;

/**
 * Straight-line estimate ("basic" in QDelivery). Roads are longer than the crow flies, so the
 * distance is stretched by a detour factor and the time comes from an average city speed.
 * Good enough to order stops when no road network is available; not for promising ETAs.
 */
class HaversineRouter implements Router
{
    public const DETOUR = 1.3;

    /** Average door-to-door speed, km/h. */
    private const SPEED = ['car' => 25, 'truck' => 20, 'bicycle' => 14, 'foot' => 5];

    public function table(array $points, string $profile = 'car'): array
    {
        $durations = $distances = [];

        foreach ($points as $i => $from) {
            foreach ($points as $j => $to) {
                $meters = $i === $j ? 0 : (int) round($from->distanceTo($to) * self::DETOUR);
                $distances[$i][$j] = $meters;
                $durations[$i][$j] = $this->seconds($meters, $profile);
            }
        }

        return ['durations' => $durations, 'distances' => $distances, 'source' => $this->name()];
    }

    public function route(array $points, string $profile = 'car'): array
    {
        $distance = 0;

        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $distance += (int) round($points[$i - 1]->distanceTo($points[$i]) * self::DETOUR);
        }

        return [
            'distance' => $distance,
            'duration' => $this->seconds($distance, $profile),
            'geometry' => array_map(fn (GeoPoint $p) => [$p->lat, $p->lng], $points),
            'source' => $this->name(),
        ];
    }

    public function name(): string
    {
        return 'haversine';
    }

    private function seconds(int $meters, string $profile): int
    {
        $kmh = self::SPEED[$profile] ?? self::SPEED['car'];

        return (int) round($meters / ($kmh * 1000 / 3600));
    }
}
