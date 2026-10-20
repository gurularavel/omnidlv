<?php

namespace App\Http\Controllers;

use App\Services\TrackingService;

class TrackingPageController extends Controller
{
    public function __construct(private readonly TrackingService $tracking) {}

    public function show(string $code)
    {
        $task = $this->tracking->find($code) ?? abort(404);

        return view('tracking.show', [
            'tracking' => $this->tracking->present($task),
            'task' => $task,
        ]);
    }
}
