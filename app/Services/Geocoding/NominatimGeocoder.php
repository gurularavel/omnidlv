<?php

namespace App\Services\Geocoding;

use App\Support\GeoPoint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Public Nominatim allows ~1 request/second and requires an identifying User-Agent, so
 * results are cached and a self-hosted instance can be swapped in via NOMINATIM_URL.
 */
class NominatimGeocoder implements Geocoder
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $userAgent,
        private readonly int $cacheDays = 30,
    ) {}

    public function geocode(string $address, ?string $country = null): ?GeoPoint
    {
        $address = trim(preg_replace('/\s+/u', ' ', $address));

        if ($address === '') {
            return null;
        }

        $key = 'geocode:'.sha1(mb_strtolower($address).'|'.$country);

        $result = Cache::remember($key, now()->addDays($this->cacheDays), function () use ($address, $country) {
            $hit = $this->request('search', array_filter([
                'q' => $address,
                'format' => 'jsonv2',
                'limit' => 1,
                'countrycodes' => $country ? strtolower($country) : null,
            ]));

            // Cache misses too ("none"), otherwise every retry of a bad address hits the API.
            return isset($hit[0]['lat']) ? [(float) $hit[0]['lat'], (float) $hit[0]['lon']] : 'none';
        });

        return is_array($result) ? GeoPoint::tryFrom($result[0], $result[1]) : null;
    }

    public function reverse(GeoPoint $point, ?string $locale = null): ?string
    {
        $key = "reverse:{$point->lat},{$point->lng}:{$locale}";

        return Cache::remember($key, now()->addDays($this->cacheDays), function () use ($point, $locale) {
            $hit = $this->request('reverse', [
                'lat' => $point->lat,
                'lon' => $point->lng,
                'format' => 'jsonv2',
                'accept-language' => $locale ?? app()->getLocale(),
            ]);

            return $hit['display_name'] ?? null;
        });
    }

    private function request(string $endpoint, array $query): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent])
                ->timeout(8)
                ->retry(2, 1000, throw: false)
                ->get(rtrim($this->baseUrl, '/').'/'.$endpoint, $query);

            return $response->successful() ? $response->json() : null;
        } catch (Throwable $e) {
            Log::warning('Geocoding failed', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
