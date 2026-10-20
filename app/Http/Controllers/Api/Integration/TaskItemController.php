<?php

namespace App\Http\Controllers\Api\Integration;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TaskItemRequest;
use App\Http\Resources\TaskItemResource;
use App\Models\Task;
use App\Models\TaskItem;

/** Items are nested under a task, and the scoped task binding keeps them tenant-safe. */
class TaskItemController extends Controller
{
    public function index(Task $task)
    {
        return TaskItemResource::collection($task->items()->orderBy('id')->get());
    }

    public function store(TaskItemRequest $request, Task $task)
    {
        $this->guardEditable($task);
        $item = $task->items()->create($request->validated());

        return (new TaskItemResource($item))->response()->setStatusCode(201);
    }

    public function show(Task $task, TaskItem $item)
    {
        return new TaskItemResource($item);
    }

    public function update(TaskItemRequest $request, Task $task, TaskItem $item)
    {
        $this->guardEditable($task);
        $item->update($request->validated());

        return new TaskItemResource($item);
    }

    public function destroy(Task $task, TaskItem $item)
    {
        $this->guardEditable($task);
        $item->delete();

        return response()->json(['id' => $item->id, 'deleted' => true]);
    }

    private function guardEditable(Task $task): void
    {
        if ($task->isFinal()) {
            throw new DomainException(__('tasks.errors.final_status'), 'task_final');
        }
    }
}
