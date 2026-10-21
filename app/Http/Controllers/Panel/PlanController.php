<?php

namespace App\Http\Controllers\Panel;

use App\Enums\Channel;
use App\Http\Controllers\Controller;
use App\Models\Hub;
use App\Models\TaskPlan;
use App\Models\Team;
use App\Services\Planning\SlotService;
use App\Support\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Slots waiting to be planned, the day's routes, and one route in detail. */
class PlanController extends Controller
{
    public function __construct(
        private readonly SlotService $slots,
        private readonly CurrentCompany $tenant,
    ) {}

    public function index(Request $request)
    {
        $tz = $this->tenant->getOrFail()->timezone;
        $date = rescue(fn () => Carbon::createFromFormat('Y-m-d', (string) $request->input('date'), $tz), null, false) ?? now($tz);
        $hubId = $request->integer('hub') ?: null;

        $plans = TaskPlan::query()
            ->with(['courier', 'hub:id,name', 'tasks:id,plan_id,route_order,tracking_code,status_category_slug,ends_at,eta_at,destination_address'])
            ->when($hubId, fn ($q) => $q->where('hub_id', $hubId))
            ->where('status_slug', '!=', TaskPlan::CANCELLED)
            ->whereBetween('starts_at', [$date->copy()->startOfDay()->utc(), $date->copy()->endOfDay()->utc()])
            ->orderBy('starts_at')
            ->get();

        return view('panel.plans.index', [
            'date' => $date,
            'hubId' => $hubId,
            'hubs' => Hub::query()->orderBy('name')->get(['id', 'name']),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name', 'hub_id']),
            'slots' => $this->slots->open($hubId),
            'plans' => $plans,
        ]);
    }

    public function close(Request $request)
    {
        $data = $request->validate([
            'slot' => ['required', 'string', 'regex:/^\d{16}$/'],
            'hub_id' => ['required', 'integer', Rule::exists(Hub::class, 'id')->where('company_id', $this->tenant->id())->whereNull('deleted_at')],
            'shift' => ['required', Rule::in(['working', 'all'])],
            'team_id' => ['nullable', 'integer', Rule::exists(Team::class, 'id')->where('company_id', $this->tenant->id())],
            'tour' => ['boolean'],
            'dispatch_early' => ['boolean'],
            'from_hub' => ['boolean'],
        ]);

        $result = $this->slots->close($data['slot'], Hub::query()->findOrFail($data['hub_id']), [
            'shift' => $data['shift'],
            'team_id' => $data['team_id'] ?? null,
            'tour' => $request->boolean('tour'),
            'dispatch_early' => $request->boolean('dispatch_early'),
            'from_hub' => $request->boolean('from_hub'),
        ], Channel::Panel, $request->user());

        $message = __('planning.planned_result', ['plans' => count($result['plans']), 'assigned' => $result['assigned']]);
        if ($result['unassigned'] !== []) {
            $message .= ' '.__('planning.planned_left', ['count' => count($result['unassigned'])]);
        }

        $date = TaskPlan::query()->whereKey($result['plans'])->min('starts_at');

        return redirect()->route('plans.index', array_filter([
            'date' => $date ? Carbon::parse($date)->setTimezone($this->tenant->getOrFail()->timezone)->format('Y-m-d') : null,
        ]))->with('status', $message);
    }

    public function show(TaskPlan $plan)
    {
        $plan->load(['courier.courierDetail', 'hub', 'tasks.status']);

        return view('panel.plans.show', ['plan' => $plan]);
    }

    /** Moves one stop up or down and recomputes the times along the route. */
    public function move(Request $request, TaskPlan $plan)
    {
        $data = $request->validate([
            'task_id' => ['required', 'integer'],
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        abort_unless($plan->status_slug === TaskPlan::PLANNED, 422);

        $order = $plan->tasks()->pluck('id')->all();
        $i = array_search($data['task_id'], $order, true);
        $j = $data['direction'] === 'up' ? $i - 1 : $i + 1;

        if ($i !== false && isset($order[$j])) {
            [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
            $this->slots->reorder($plan, $order);
        }

        return redirect()->route('plans.show', $plan)->with('status', __('planning.reordered'));
    }
}
