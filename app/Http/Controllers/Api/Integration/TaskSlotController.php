<?php

namespace App\Http\Controllers\Api\Integration;

use App\Enums\Channel;
use App\Http\Controllers\Controller;
use App\Models\Hub;
use App\Models\Team;
use App\Services\Planning\SlotService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TaskSlotController extends Controller
{
    public function __construct(private readonly SlotService $slots) {}

    public function index(Request $request)
    {
        $request->validate(['hub_id' => ['nullable', 'integer']]);
        $hubs = Hub::query()->get(['id', 'name'])->keyBy('id');

        return response()->json([
            'data' => $this->slots->open($request->integer('hub_id') ?: null)->map(fn ($s) => [
                'hub_id' => $s->hub_id,
                'slot' => $s->slot,
                'starts_at' => $s->starts_at?->toIso8601ZuluString(),
                'ends_at' => $s->ends_at?->toIso8601ZuluString(),
                'tasks_count' => (int) $s->tasks_count,
                'hub' => $hubs->has($s->hub_id) ? ['id' => $s->hub_id, 'name' => $hubs[$s->hub_id]->name] : null,
            ])->values(),
        ]);
    }

    public function close(Request $request, string $slot)
    {
        $data = $request->validate([
            'hub_id' => ['required', 'integer', Rule::exists(Hub::class, 'id')->where('company_id', $request->user()->id)->whereNull('deleted_at')],
            'shift' => ['nullable', Rule::in(['working', 'resting', 'all'])],
            'team_id' => ['nullable', 'integer', Rule::exists(Team::class, 'id')->where('company_id', $request->user()->id)],
            'online' => ['nullable', Rule::in(['online', 'offline', 'all'])],
            'duty' => ['nullable', Rule::in(['on_duty', 'idle', 'all'])],
            'tour' => ['nullable', 'boolean'],
            'dispatch_early' => ['nullable', 'boolean'],
            'from_hub' => ['nullable', 'boolean'],
        ]);

        abort_unless(preg_match('/^\d{16}$/', $slot), 404);

        $result = $this->slots->close($slot, Hub::query()->findOrFail($data['hub_id']), $data, Channel::Api);

        return response()->json($result);
    }

    public function shift(Request $request)
    {
        $data = $request->validate([
            'task_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'task_ids.*' => ['integer'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'slot' => ['required', 'string', 'regex:/^\d{16}$/'],
            'slot_starts_at' => ['nullable', 'date'],
            'slot_ends_at' => ['nullable', 'date', 'after:slot_starts_at'],
        ]);

        $utc = fn (?string $v) => $v ? Carbon::parse($v, 'UTC')->utc() : null;

        $moved = $this->slots->shift(
            array_values(array_unique($data['task_ids'])),
            $data['slot'],
            $utc($data['starts_at']),
            $utc($data['ends_at']),
            $utc($data['slot_starts_at'] ?? null),
            $utc($data['slot_ends_at'] ?? null),
            Channel::Api,
        );

        return response()->json(['moved' => $moved]);
    }
}
