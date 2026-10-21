<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Services\Routing\RoutingService;
use App\Support\GeoPoint;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** QDelivery /routing/distance and /routing/solve. */
class RoutingController extends Controller
{
    public function __construct(private readonly RoutingService $routing) {}

    public function distance(Request $request)
    {
        $data = $request->validate([
            'pickup_lat' => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng' => ['required', 'numeric', 'between:-180,180'],
            'destination_lat' => ['required', 'numeric', 'between:-90,90'],
            'destination_lng' => ['required', 'numeric', 'between:-180,180'],
            'strategy' => ['nullable', Rule::in(['basic', 'accurate'])],
            'profile' => ['nullable', Rule::in(array_keys(RoutingService::PROFILES))],
        ]);

        return response()->json($this->routing->distance(
            new GeoPoint((float) $data['pickup_lat'], (float) $data['pickup_lng']),
            new GeoPoint((float) $data['destination_lat'], (float) $data['destination_lng']),
            $data['strategy'] ?? 'basic',
            $data['profile'] ?? 'car',
        ));
    }

    /** VROOM format in and out. Limits keep one request from occupying the solver for minutes. */
    public function solve(Request $request)
    {
        $data = $request->validate([
            'vehicles' => ['required', 'array', 'min:1', 'max:200'],
            'vehicles.*.id' => ['required', 'integer', 'distinct'],
            'vehicles.*.profile' => ['required', Rule::in(array_keys(RoutingService::PROFILES))],
            'vehicles.*.description' => ['nullable', 'string', 'max:255'],
            'vehicles.*.start' => ['nullable', 'array', 'size:2'],
            'vehicles.*.start.*' => ['numeric'],
            'vehicles.*.end' => ['nullable', 'array', 'size:2'],
            'vehicles.*.end.*' => ['numeric'],
            'vehicles.*.capacity' => ['nullable', 'array', 'max:5'],
            'vehicles.*.capacity.*' => ['integer', 'min:0'],
            'vehicles.*.speed_factor' => ['nullable', 'numeric', 'gt:0', 'lte:5'],
            'vehicles.*.max_tasks' => ['nullable', 'integer', 'min:0'],
            'vehicles.*.max_travel_time' => ['nullable', 'integer', 'min:0'],
            'vehicles.*.max_distance' => ['nullable', 'integer', 'min:0'],
            'vehicles.*.time_window' => ['nullable', 'array', 'size:2'],
            'vehicles.*.time_window.*' => ['integer', 'min:0'],
            'vehicles.*.skills' => ['nullable', 'array'],
            'vehicles.*.skills.*' => ['integer'],
            'vehicles.*.cost' => ['nullable', 'array'],
            'vehicles.*.break' => ['nullable', 'array'],
            'vehicles.*.steps' => ['nullable', 'array'],
            'jobs' => ['nullable', 'array', 'max:2000'],
            'jobs.*.id' => ['required', 'integer', 'distinct'],
            'jobs.*.location' => ['required', 'array', 'size:2'],
            'jobs.*.location.*' => ['numeric'],
            'jobs.*.description' => ['nullable', 'string', 'max:255'],
            'jobs.*.setup' => ['nullable', 'integer', 'min:0'],
            'jobs.*.service' => ['nullable', 'integer', 'min:0'],
            'jobs.*.delivery' => ['nullable', 'array'],
            'jobs.*.delivery.*' => ['integer', 'min:0'],
            'jobs.*.pickup' => ['nullable', 'array'],
            'jobs.*.pickup.*' => ['integer', 'min:0'],
            'jobs.*.skills' => ['nullable', 'array'],
            'jobs.*.skills.*' => ['integer'],
            'jobs.*.priority' => ['nullable', 'integer', 'between:0,100'],
            'jobs.*.time_windows' => ['nullable', 'array'],
            'jobs.*.time_windows.*' => ['array', 'size:2'],
        ]);

        // VROOM rejects explicit nulls, so drop unset optional fields.
        $clean = fn (array $rows) => array_map(fn ($row) => array_filter($row, fn ($v) => $v !== null), $rows);

        return response()->json($this->routing->solve([
            'vehicles' => $clean($data['vehicles']),
            'jobs' => $clean($data['jobs'] ?? []),
        ]));
    }
}
