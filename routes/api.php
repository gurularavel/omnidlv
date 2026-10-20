<?php

use App\Http\Controllers\Api\Integration\CourierController;
use App\Http\Controllers\Api\Integration\HubController;
use App\Http\Controllers\Api\Integration\TaskController;
use App\Http\Controllers\Api\Integration\TaskItemController;
use App\Http\Controllers\Api\Integration\TaskStatusController;
use App\Http\Controllers\Api\Integration\TeamController;
use App\Http\Controllers\Api\TrackingController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('tracking', [TrackingController::class, 'show'])->middleware('throttle:tracking')->name('api.tracking');

/*
 | QDelivery-compatible integration API: same paths and payloads under /api/integration,
 | authenticated with a company API key (Authorization: Bearer <key> or just <key>).
 */
Route::prefix('integration')
    ->middleware(['auth:sanctum', 'tenant.token', 'throttle:integration'])
    ->name('integration.')
    ->group(function () {
        Route::bind('courier', fn ($id) => User::query()->couriers()->findOrFail($id));

        Route::apiResource('hubs', HubController::class);
        Route::apiResource('teams', TeamController::class);

        Route::get('couriers', [CourierController::class, 'index'])->name('couriers.index');
        Route::get('couriers/{courier}', [CourierController::class, 'show'])->name('couriers.show');
        Route::patch('couriers/{courier}', [CourierController::class, 'update'])->name('couriers.update');

        Route::get('statuses', [TaskStatusController::class, 'index'])->name('statuses.index');

        Route::apiResource('tasks', TaskController::class)->except('destroy');
        Route::post('tasks/{task}/lock', [TaskController::class, 'lock'])->name('tasks.lock');
        Route::patch('task-status/{task}', [TaskStatusController::class, 'update'])->name('tasks.status');

        Route::apiResource('tasks.items', TaskItemController::class)->scoped();
    });
