<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrackingService;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function __construct(private readonly TrackingService $tracking) {}

    /**
     * GET /api/tracking. QDelivery reads `code` from a JSON body on GET, which many proxies
     * and browsers drop, so ?code= is accepted as well.
     */
    public function show(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $task = $this->tracking->find($data['code']);

        if ($task === null) {
            return response()->json(['message' => __('tracking.not_found')], 404);
        }

        return response()->json($this->tracking->present($task));
    }
}
