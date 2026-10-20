<?php

namespace App\Http\Controllers\Panel;

use App\Enums\Channel;
use App\Enums\StatusCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\Hub;
use App\Models\Status;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Queries\TaskFilter;
use App\Services\AssignmentService;
use App\Services\StatusMachine;
use App\Services\TaskService;
use App\Support\GeoPoint;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly AssignmentService $assignments,
        private readonly StatusMachine $statuses,
    ) {}

    public function index(Request $request)
    {
        $filters = TaskFilter::fromRequest($request);

        $tasks = TaskFilter::apply(Task::query(), $filters)
            ->with(['status', 'courier:id,name', 'hub:id,name'])
            ->orderByRaw("CASE WHEN status_category_slug IN ('completed','cancelled','failed') THEN 1 ELSE 0 END")
            ->orderBy('ends_at')
            ->paginate(50)
            ->withQueryString();

        return view('panel.tasks.index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'hubs' => Hub::query()->orderBy('name')->pluck('name', 'id'),
            'couriers' => User::query()->couriers()->orderBy('name')->pluck('name', 'id'),
            'tabCounts' => $this->tabCounts(),
        ]);
    }

    public function create(Request $request)
    {
        $hubs = Hub::query()->orderBy('name')->get();

        if ($hubs->isEmpty()) {
            return redirect()->route('hubs.create')->with('error', __('Add a hub first: every task starts or ends at one.'));
        }

        return view('panel.tasks.form', $this->formData(new Task([
            'hub_id' => $request->integer('hub') ?: $hubs->first()->id,
            'starts_at' => now()->startOfMinute(),
            'ends_at' => now()->addHours(2)->startOfMinute(),
            'quantity' => 1,
        ]), $hubs));
    }

    public function store(StoreTaskRequest $request)
    {
        $task = $this->tasks->create($this->fromLocalTime($request->validated()), Channel::Panel, $request->user());

        return redirect()->route('tasks.show', $task)->with('status', __('Task :code created.', ['code' => $task->tracking_code]));
    }

    public function show(Task $task)
    {
        $task->load(['status', 'courier.courierDetail', 'courier.teams:id,color', 'hub', 'team', 'items', 'statusLogs.status', 'statusLogs.user:id,name', 'owner:id,name']);

        return view('panel.tasks.show', [
            'task' => $task,
            'statuses' => Status::query()->where('is_active', true)->orderBy('sort')->get(),
            'couriers' => User::query()->couriers()->with('courierLocation')->orderBy('name')->get(),
        ]);
    }

    public function edit(Task $task)
    {
        return view('panel.tasks.form', $this->formData($task->load('items'), Hub::query()->orderBy('name')->get()));
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $this->tasks->update($task, $this->fromLocalTime($request->validated()), Channel::Panel, $request->user());

        return redirect()->route('tasks.show', $task)->with('status', __('Task updated.'));
    }

    public function status(UpdateTaskStatusRequest $request, Task $task)
    {
        $data = $request->validated();
        $this->statuses->transition(
            $task, Status::query()->findOrFail($data['status_id']), Channel::Panel, $request->user(),
            $data['notes'] ?? null, GeoPoint::tryFrom($data['lat'] ?? null, $data['lng'] ?? null),
        );

        return back()->with('status', __('Status changed.'));
    }

    public function assign(Request $request, Task $task)
    {
        $data = $request->validate([
            'courier_id' => ['nullable', 'integer', Rule::exists('employments', 'user_id')
                ->where('company_id', $task->company_id)->where('role_slug', 'courier')->where('is_active', true)],
            'to_pool' => ['nullable', 'boolean'],
            'ignore_capacity' => ['nullable', 'boolean'],
        ]);

        if (empty($data['courier_id'])) {
            $this->assignments->unassign($task, Channel::Panel, $request->user(), toPool: (bool) ($data['to_pool'] ?? false));

            return back()->with('status', __('Courier removed from the task.'));
        }

        $courier = User::query()->findOrFail($data['courier_id']);
        $this->assignments->assign($task, $courier, Channel::Panel, $request->user(), (bool) ($data['ignore_capacity'] ?? false));

        return back()->with('status', __('Assigned to :name.', ['name' => $courier->name]));
    }

    public function lock(Task $task)
    {
        $this->tasks->setLocked($task, ! $task->is_locked);

        return back()->with('status', $task->is_locked ? __('Task locked for the courier.') : __('Task unlocked.'));
    }

    /**
     * The panel's datetime-local inputs are in the company timezone; the service treats
     * offset-less times as UTC (the API contract), so convert here.
     */
    private function fromLocalTime(array $data): array
    {
        $tz = app(\App\Support\CurrentCompany::class)->getOrFail()->timezone;

        foreach (['starts_at', 'ends_at', 'slot_starts_at', 'slot_ends_at'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = \Illuminate\Support\Carbon::parse($data[$field], $tz)->utc()->toIso8601String();
            }
        }

        return $data;
    }

    private function formData(Task $task, $hubs): array
    {
        return [
            'task' => $task,
            'hubs' => $hubs,
            'teams' => Team::query()->orderBy('name')->pluck('name', 'id'),
            'couriers' => User::query()->couriers()->orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function tabCounts(): array
    {
        $counts = Task::query()
            ->selectRaw('status_category_slug, count(*) as total')
            ->where(fn ($q) => $q->open()->orWhere('ended_at', '>=', now()->subDay()))
            ->groupBy('status_category_slug')
            ->pluck('total', 'status_category_slug');

        $sum = fn (StatusCategory ...$c) => collect($c)->sum(fn ($x) => $counts[$x->value] ?? 0);

        return [
            'open' => $sum(StatusCategory::Created, StatusCategory::Pool, StatusCategory::Assigned, StatusCategory::InProgress, StatusCategory::OnDelivery),
            'unassigned' => $sum(StatusCategory::Created, StatusCategory::Pool),
            'moving' => $sum(StatusCategory::Assigned, StatusCategory::InProgress, StatusCategory::OnDelivery),
            'done' => $sum(StatusCategory::Completed),
            'problem' => $sum(StatusCategory::Failed, StatusCategory::Cancelled),
        ];
    }
}
