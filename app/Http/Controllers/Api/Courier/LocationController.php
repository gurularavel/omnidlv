<?php

namespace App\Http\Controllers\Api\Courier;

use App\Http\Controllers\Controller;
use App\Services\Courier\CourierWorkService;
use App\Support\GeoPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** GPS from the native app: a batch of fixes, which may have been collected offline. */
class LocationController extends Controller
{
    public function store(Request $request, CourierWorkService $work)
    {
        $data = $request->validate([
            'locations' => ['required', 'array', 'min:1', 'max:'.max(1, (int) config('courier_app.location.batch_size') * 4)],
            'locations.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'locations.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'locations.*.recorded_at' => ['required', 'date'],
            'locations.*.heading' => ['nullable', 'numeric', 'between:-1,360'],
            'locations.*.speed' => ['nullable', 'numeric', 'min:-1', 'max:300'],
            'locations.*.accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'locations.*.battery' => ['nullable', 'integer', 'between:0,100'],
            'locations.*.is_mock' => ['nullable', 'boolean'],
        ]);

        $fixes = [];
        foreach ($data['locations'] as $fix) {
            $point = GeoPoint::tryFrom($fix['lat'], $fix['lng']);
            if ($point === null) {
                continue;
            }

            // Platforms report "unknown" heading/speed as -1.
            $fixes[] = [
                'point' => $point,
                'recorded_at' => Carbon::parse($fix['recorded_at'])->utc(),
                'heading' => isset($fix['heading']) && $fix['heading'] >= 0 ? (int) $fix['heading'] : null,
                'speed' => isset($fix['speed']) && $fix['speed'] >= 0 ? (float) $fix['speed'] : null,
                'accuracy' => isset($fix['accuracy']) ? (int) $fix['accuracy'] : null,
                'battery' => $fix['battery'] ?? null,
                'is_mock' => (bool) ($fix['is_mock'] ?? false),
            ];
        }

        return response()->json(['accepted' => count($fixes), 'stored' => $work->pingBatch($request->user(), $fixes)]);
    }
}
