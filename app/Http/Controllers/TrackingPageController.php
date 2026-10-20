<?php

namespace App\Http\Controllers;

use App\Services\TrackingService;
use Illuminate\Http\Request;

class TrackingPageController extends Controller
{
    public function __construct(private readonly TrackingService $tracking) {}

    public function show(string $code)
    {
        $task = $this->tracking->find($code) ?? abort(404);

        if ($task->company->locale && ! request()->has('lang') && ! session()->has('locale')) {
            app()->setLocale($task->company->locale);
        }

        return view('tracking.show', [
            'tracking' => $this->tracking->present($task),
            'task' => $task,
        ]);
    }

    public function rate(Request $request, string $code)
    {
        $task = $this->tracking->find($code) ?? abort(404);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->tracking->rate($task, (int) $data['rating'], $data['comment'] ?? null);

        return redirect()->route('tracking.show', $task->tracking_code);
    }
}
