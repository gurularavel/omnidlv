<?php

namespace App\Support;

/**
 * Point-in-polygon without PostGIS. Vertices are [[lat, lng], ...] as stored in teams.polygon.
 */
final class Polygon
{
    /**
     * @param  list<array{0: float|int|string, 1: float|int|string}>  $vertices
     */
    public static function contains(array $vertices, GeoPoint $point): bool
    {
        $count = count($vertices);

        if ($count < 3) {
            return false;
        }

        $inside = false;

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$latI, $lngI] = [(float) $vertices[$i][0], (float) $vertices[$i][1]];
            [$latJ, $lngJ] = [(float) $vertices[$j][0], (float) $vertices[$j][1]];

            $crosses = ($latI > $point->lat) !== ($latJ > $point->lat)
                && $point->lng < ($lngJ - $lngI) * ($point->lat - $latI) / ($latJ - $latI) + $lngI;

            if ($crosses) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * @param  list<array{0: float|int|string, 1: float|int|string}>  $vertices
     * @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}
     */
    public static function bbox(array $vertices): array
    {
        $lats = array_map(fn ($v) => (float) $v[0], $vertices);
        $lngs = array_map(fn ($v) => (float) $v[1], $vertices);

        return [
            'min_lat' => min($lats),
            'max_lat' => max($lats),
            'min_lng' => min($lngs),
            'max_lng' => max($lngs),
        ];
    }
}
