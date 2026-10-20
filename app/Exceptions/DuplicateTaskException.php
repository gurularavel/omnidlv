<?php

namespace App\Exceptions;

use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** integration_id + action_slug must be unique per company; 409 lets clients retry safely. */
class DuplicateTaskException extends RuntimeException
{
    public function __construct(public readonly Task $existing)
    {
        parent::__construct(__('tasks.errors.duplicate', ['id' => $existing->integration_id]));
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'code' => 'duplicate_task',
                'task_id' => $this->existing->id,
                'tracking_code' => $this->existing->tracking_code,
            ], 409);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
