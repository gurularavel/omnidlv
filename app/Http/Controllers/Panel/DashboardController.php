<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Hub;
use App\Services\LiveMonitor;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly LiveMonitor $monitor) {}

    public function index(Request $request)
    {
        $hubId = $request->integer('hub') ?: null;

        return view('panel.dashboard', [
            'hubs' => Hub::query()->orderBy('name')->get(['id', 'name']),
            'hubId' => $hubId,
            'snapshot' => $this->monitor->snapshot($hubId),
        ]);
    }

    public function live(Request $request)
    {
        return response()->json($this->monitor->snapshot($request->integer('hub') ?: null));
    }
}
