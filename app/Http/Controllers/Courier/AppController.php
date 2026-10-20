<?php

namespace App\Http\Controllers\Courier;

use App\Enums\StatusCategory;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\Courier\CourierPresenter;
use App\Services\Courier\CourierWorkService;
use App\Support\GeoPoint;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The courier PWA. Pages are Blade shells; every action is a JSON endpoint so the app can queue
 * it offline and replay it. Replays carry X-Action-Id, and a known id is acknowledged without
 * running the action twice.
 */
class AppController extends Controller
{
    public function __construct(
        private readonly CourierWorkService $work,
        private readonly CourierPresenter $present,
    ) {}

    public function home(Request $request)
    {
        return view('courier.app', ['initial' => $this->present->board($request->user())]);
    }

    public function data(Request $request)
    {
        return response()->json($this->present->board($request->user()));
    }

    public function show(Request $request, Task $task)
    {
        abort_unless($task->courier_id === $request->user()->id, 404);

        return view('courier.task', ['initial' => $this->present->task($task)]);
    }

    public function taskData(Request $request, Task $task)
    {
        abort_unless($task->courier_id === $request->user()->id, 404);

        return response()->json($this->present->task($task));
    }

    public function startShift(Request $request)
    {
        return $this->once($request, 'shift_start', null, function () use ($request) {
            $this->work->startShift($request->user(), $this->point($request));

            return $this->present->board($request->user());
        });
    }

    public function endShift(Request $request)
    {
        return $this->once($request, 'shift_end', null, function () use ($request) {
            $this->work->endShift($request->user());

            return $this->present->board($request->user());
        });
    }

    public function location(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'speed' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'battery' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $point = GeoPoint::tryFrom($data['lat'], $data['lng']);
        abort_if($point === null, 422);

        $this->work->ping($request->user(), $point, [
            'heading' => isset($data['heading']) ? (int) $data['heading'] : null,
            'speed' => $data['speed'] ?? null,
            'accuracy' => isset($data['accuracy']) ? (int) $data['accuracy'] : null,
            'battery' => $data['battery'] ?? null,
        ]);

        return response()->noContent();
    }

    public function step(Request $request, Task $task)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(array_keys(CourierWorkService::ACTIONS))],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->taskAction($request, $task, 'step:'.$data['action'], fn () => $this->work->step($task, $request->user(), $data['action'], $this->point($request), $data['notes'] ?? null));
    }

    public function complete(Request $request, Task $task)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(CourierWorkService::COMPLETE_STATUSES)],
            'notes' => ['nullable', 'string', 'max:255'],
            'received_by' => ['nullable', 'string', 'max:191'],
        ]);

        return $this->taskAction($request, $task, 'complete', fn () => $this->work->complete($task, $request->user(), $data['status'], $this->point($request), $data['notes'] ?? null, $data['received_by'] ?? null));
    }

    public function fail(Request $request, Task $task)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(CourierWorkService::FAIL_STATUSES)],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->taskAction($request, $task, 'fail', fn () => $this->work->fail($task, $request->user(), $data['status'], $this->point($request), $data['notes'] ?? null));
    }

    public function claim(Request $request, Task $task)
    {
        abort_unless($task->status_category_slug === StatusCategory::Pool, 409, __('tasks.errors.already_claimed'));

        return $this->taskAction($request, $task, 'claim', fn () => $this->work->claim($task, $request->user()));
    }

    public function code(Request $request, Task $task)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $ok = $this->work->verifyCode($task, $request->user(), $data['code']);

        return response()->json([
            'verified' => $ok,
            'attempts_left' => max(0, 5 - $task->delivery_code_attempts),
            'task' => $this->present->task($task->refresh()),
        ], $ok ? 200 : 422);
    }

    public function pod(Request $request, Task $task)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['photo', 'signature'])],
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        return $this->taskAction($request, $task, 'pod:'.$data['type'], fn () => $this->work->addPod($task, $request->user(), $data['type'], $data['file'], $this->point($request)));
    }

    public function scan(Request $request, Task $task)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:128']]);
        $item = $this->work->scan($task, $request->user(), $data['code']);

        return response()->json([
            'matched' => $item !== null,
            'item' => $item?->only(['id', 'name', 'integration_id', 'barcode']),
            'task' => $this->present->task($task->refresh()),
        ], $item ? 200 : 404);
    }

    private function taskAction(Request $request, Task $task, string $action, Closure $run): JsonResponse
    {
        return $this->once($request, $action, $task, function () use ($run, $task) {
            $run();

            return $this->present->task($task->refresh());
        });
    }

    /**
     * Runs $run once per X-Action-Id. A repeated id (an offline replay that already reached the
     * server) returns the current state instead of acting again.
     */
    private function once(Request $request, string $action, ?Task $task, Closure $run): JsonResponse
    {
        $id = $request->header('X-Action-Id');

        if ($id !== null && ! preg_match('/^[0-9a-f-]{36}$/i', $id)) {
            $id = null;
        }

        if ($id !== null && DB::table('courier_action_receipts')->where('id', $id)->exists()) {
            $state = $task ? $this->present->task($task->refresh()) : $this->present->board($request->user());

            return response()->json([...$state, 'replayed' => true]);
        }

        try {
            $result = DB::transaction(function () use ($id, $request, $action, $task, $run) {
                $result = $run();

                if ($id !== null) {
                    DB::table('courier_action_receipts')->insert([
                        'id' => $id, 'user_id' => $request->user()->id, 'task_id' => $task?->id,
                        'action' => mb_substr($action, 0, 30), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                return $result;
            });
        } catch (DomainException $e) {
            // Business refusals are final: the app should not retry them, so they carry 422.
            return response()->json(['message' => $e->getMessage(), 'code' => $e->errorCode], 422);
        }

        return response()->json($result);
    }

    private function point(Request $request): ?GeoPoint
    {
        return GeoPoint::tryFrom($request->input('lat'), $request->input('lng'));
    }
}
