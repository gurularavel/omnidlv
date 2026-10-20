<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskPod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PodController extends Controller
{
    public function __invoke(Request $request, TaskPod $pod)
    {
        // The task lookup is tenant-scoped: a link from another company resolves to nothing.
        $task = Task::query()->findOrFail($pod->task_id);

        if ($request->user()->isCourier() && $task->courier_id !== $request->user()->id) {
            abort(404);
        }

        abort_unless(Storage::disk('local')->exists($pod->path), 404);

        return Storage::disk('local')->response($pod->path, headers: ['Cache-Control' => 'private, max-age=1800']);
    }
}
