<?php

namespace App\Services\Geocoding;

use App\Support\GeoPoint;

/** Deterministic geocoder for tests and offline development (GEOCODER=fake). */
class FakeGeocoder implements Geocoder
{
    /** @var array<string, GeoPoint|null> */
    private array $known = [];

    public function set(string $address, ?GeoPoint $point): self
    {
        $this->known[mb_strtolower(trim($address))] = $point;

        return $this;
    }

    public function geocode(string $address, ?string $country = null): ?GeoPoint
    {
        $key = mb_strtolower(trim($address));

        if (array_key_exists($key, $this->known)) {
            return $this->known[$key];
        }

        if (str_contains($key, 'unresolvable')) {
            return null;
        }

        // Spread unknown addresses around central Baku so routing tests get distinct points.
        $hash = crc32($key);

        return new GeoPoint(40.40 + ($hash % 1000) / 10000, 49.85 + (intdiv($hash, 1000) % 1000) / 10000);
    }

    public function reverse(GeoPoint $point, ?string $locale = null): ?string
    {
        return "{$point->lat}, {$point->lng}";
    }
}
