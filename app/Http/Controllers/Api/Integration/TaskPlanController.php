<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskPlanResource;
use App\Models\TaskPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TaskPlanController extends Controller
{
    private const RELATIONS = ['courier', 'tasks.status', 'tasks.hub', 'tasks.statusLogs.status'];

    public function index(Request $request)
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'courier_id' => ['nullable', 'integer'],
            'slot' => ['nullable', 'string', 'max:16'],
            'status_slug' => ['nullable', Rule::in([TaskPlan::PLANNED, TaskPlan::IN_PROGRESS, TaskPlan::COMPLETED, TaskPlan::CANCELLED])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tz = $request->user()->timezone ?? config('app.timezone');

        $plans = TaskPlan::query()
            ->with(self::RELATIONS)
            ->when($request->input('date'), fn ($q, $d) => $q->whereBetween('starts_at', [
                Carbon::parse($d, $tz)->startOfDay()->utc(), Carbon::parse($d, $tz)->endOfDay()->utc(),
            ]))
            ->when($request->integer('courier_id'), fn ($q, $id) => $q->where('courier_id', $id))
            ->when($request->input('slot'), fn ($q, $slot) => $q->where('slot', $slot))
            ->when($request->input('status_slug'), fn ($q, $s) => $q->where('status_slug', $s))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return TaskPlanResource::collection($plans);
    }

    /** QDelivery names the path parameter {task}; it is the plan id. */
    public function show(int $plan)
    {
        return new TaskPlanResource(TaskPlan::query()->with(self::RELATIONS)->findOrFail($plan));
    }
}
