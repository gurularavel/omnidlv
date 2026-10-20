<?php

namespace App\Http\Controllers\Api\Integration;

use App\Enums\Channel;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Status;
use App\Models\Task;
use App\Services\StatusMachine;
use App\Support\GeoPoint;

class TaskStatusController extends Controller
{
    public function __construct(private readonly StatusMachine $statuses) {}

    /** PATCH /task-status/{task} — QDelivery "Update Status". */
    public function update(UpdateTaskStatusRequest $request, Task $task)
    {
        $data = $request->validated();

        $this->statuses->transition(
            $task,
            Status::query()->findOrFail($data['status_id']),
            Channel::Api,
            notes: $data['notes'] ?? null,
            at: GeoPoint::tryFrom($data['lat'] ?? null, $data['lng'] ?? null),
        );

        return new TaskResource($task->load(['status', 'hub', 'team', 'courier', 'items', 'statusLogs.status']));
    }

    /** GET /statuses — not in QDelivery, which leaves integrators guessing status ids. */
    public function index()
    {
        return Status::query()->where('is_active', true)->orderBy('sort')->get()->map(fn (Status $s) => [
            'id' => $s->id,
            'slug' => $s->slug,
            'status_category_slug' => $s->status_category_slug->value,
            'name' => $s->label(),
            'is_system' => $s->is_system,
        ]);
    }
}
