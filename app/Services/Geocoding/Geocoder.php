<?php

namespace App\Services\Geocoding;

use App\Support\GeoPoint;

interface Geocoder
{
    /** Returns null when the address cannot be resolved; never throws for "not found". */
    public function geocode(string $address, ?string $country = null): ?GeoPoint;

    public function reverse(GeoPoint $point, ?string $locale = null): ?string;
}
