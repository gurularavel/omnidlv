<?php

namespace App\Http\Controllers;

use App\Services\TrackingService;
use Illuminate\Http\Request;

class TrackingPageController extends Controller
{
    public function __construct(private readonly TrackingService $tracking) {}

    /** Public entry: the recipient types the tracking code, or the order number + phone digits. */
    public function lookup(Request $request)
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:64'],
            'order' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $embed = $request->boolean('embed');
        $searched = filled($data['code'] ?? null) || filled($data['order'] ?? null);

        if ($searched) {
            $task = filled($data['code'] ?? null)
                ? $this->tracking->find($data['code'])
                : $this->tracking->findByOrder($data['order'], $data['phone'] ?? '');

            if ($task) {
                return redirect()->route('tracking.show', array_filter([
                    'code' => $task->tracking_code,
                    'embed' => $embed ? 1 : null,
                ]));
            }
        }

        return view('tracking.lookup', [
            'notFound' => $searched,
            'mode' => filled($data['order'] ?? null) ? 'order' : 'code',
            'embed' => $embed,
        ]);
    }

    public function show(Request $request, string $code)
    {
        $normalized = TrackingService::normalizeCode($code);

        // One canonical URL per order, whatever the recipient typed.
        if ($normalized !== $code) {
            return redirect()->route('tracking.show', ['code' => $normalized, ...$request->only('embed', 'lang')], 301);
        }

        $task = $this->tracking->find($code) ?? abort(404);

        if ($task->company->locale && ! $request->has('lang') && ! session()->has('locale')) {
            app()->setLocale($task->company->locale);
        }

        return view('tracking.show', [
            'tracking' => $this->tracking->present($task),
            'task' => $task,
            'embed' => $request->boolean('embed'),
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
