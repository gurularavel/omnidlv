<?php

namespace App\Http\Controllers\Api\Integration;

use App\Enums\Channel;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Queries\TaskFilter;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    private const RELATIONS = ['status', 'courier', 'hub', 'team', 'items', 'statusLogs.status'];

    public function __construct(private readonly TaskService $tasks) {}

    public function index(Request $request)
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:200']]);

        $tasks = TaskFilter::apply(Task::query(), TaskFilter::fromRequest($request))
            ->with(['status', 'courier', 'hub', 'team', 'statusLogs.status'])
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 50))
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request)
    {
        $task = $this->tasks->create($request->validated(), Channel::Api);

        return (new TaskResource($task->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(Task $task)
    {
        return new TaskResource($task->load(self::RELATIONS));
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $task = $this->tasks->update($task, $request->validated(), Channel::Api);

        return new TaskResource($task->load(self::RELATIONS));
    }

    public function lock(Request $request, Task $task)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['lock', 'unlock'])]]);
        $this->tasks->setLocked($task, $data['action'] === 'lock');

        return response()->json([
            'id' => $task->id,
            'integration_id' => $task->integration_id,
            'locked' => $task->is_locked,
        ]);
    }
}
