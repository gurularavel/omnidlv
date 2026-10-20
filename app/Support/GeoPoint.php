<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * One coordinate type for the whole app. APIs talk lat/lng, OSRM/VROOM want [lng, lat];
 * converting only here avoids the classic swapped-axis bug.
 */
final readonly class GeoPoint
{
    public float $lat;

    public float $lng;

    public function __construct(float $lat, float $lng)
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new InvalidArgumentException("Invalid coordinate: {$lat}, {$lng}");
        }

        $this->lat = round($lat, 6);
        $this->lng = round($lng, 6);
    }

    /** Null for missing, out-of-range or 0,0 ("unknown") coordinates. */
    public static function tryFrom(mixed $lat, mixed $lng): ?self
    {
        if (! is_numeric($lat) || ! is_numeric($lng) || ((float) $lat == 0.0 && (float) $lng == 0.0)) {
            return null;
        }

        try {
            return new self((float) $lat, (float) $lng);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** @return array{0: float, 1: float} [lng, lat] as OSRM/VROOM/GeoJSON expect */
    public function toLngLat(): array
    {
        return [$this->lng, $this->lat];
    }

    /** Great-circle distance in meters. */
    public function distanceTo(self $other): float
    {
        $r = 6371000;
        $dLat = deg2rad($other->lat - $this->lat);
        $dLng = deg2rad($other->lng - $this->lng);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($this->lat)) * cos(deg2rad($other->lat)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
