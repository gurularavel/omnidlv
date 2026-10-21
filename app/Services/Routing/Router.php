<?php

namespace App\Services\Routing;

use App\Support\GeoPoint;

/** Travel times and distances between points on the road network (or an estimate of them). */
interface Router
{
    /**
     * Square matrices over $points, in seconds and meters.
     *
     * @param  list<GeoPoint>  $points
     * @return array{durations: list<list<int>>, distances: list<list<int>>, source: string}
     */
    public function table(array $points, string $profile = 'car'): array;

    /**
     * Driving route through $points in order.
     *
     * @param  list<GeoPoint>  $points
     * @return array{distance: int, duration: int, geometry: list<array{0: float, 1: float}>, source: string}
     */
    public function route(array $points, string $profile = 'car'): array;

    /** Short name returned to API clients as "type". */
    public function name(): string;
}
