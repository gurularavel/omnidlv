<?php

use App\Enums\Role;
use App\Http\Controllers\Api\Courier\AccountController as CourierAccountController;
use App\Http\Controllers\Api\Courier\AuthController as CourierAuthController;
use App\Http\Controllers\Api\Courier\LocationController as CourierLocationController;
use App\Http\Controllers\Api\Integration\CourierController;
use App\Http\Controllers\Api\Integration\HubController;
use App\Http\Controllers\Api\Integration\RoutingController;
use App\Http\Controllers\Api\Integration\TaskController;
use App\Http\Controllers\Api\Integration\TaskItemController;
use App\Http\Controllers\Api\Integration\TaskPlanController;
use App\Http\Controllers\Api\Integration\TaskSlotController;
use App\Http\Controllers\Api\Integration\TaskStatusController;
use App\Http\Controllers\Api\Integration\TeamController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Courier\AppController as CourierAppController;
use App\Http\Middleware\EnsureRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Public, no key: by tracking code, or by order number + last 4 phone digits (stricter limit).
Route::get('tracking', [TrackingController::class, 'show'])->middleware('throttle:tracking-lookup')->name('api.tracking');

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

        Route::get('task-slots', [TaskSlotController::class, 'index'])->name('slots.index');
        Route::post('task-slots/shift', [TaskSlotController::class, 'shift'])->name('slots.shift');
        Route::post('task-slots/{slot}/close', [TaskSlotController::class, 'close'])->middleware('throttle:20,1')->name('slots.close');

        Route::get('task-plans', [TaskPlanController::class, 'index'])->name('plans.index');
        Route::get('task-plans/{plan}', [TaskPlanController::class, 'show'])->whereNumber('plan')->name('plans.show');

        Route::post('routing/distance', [RoutingController::class, 'distance'])->name('routing.distance');
        Route::post('routing/solve', [RoutingController::class, 'solve'])->middleware('throttle:30,1')->name('routing.solve');
    });

/*
 | Native courier app (Flutter). Personal tokens with the "courier" ability; task actions reuse
 | the PWA's JSON endpoints, so both apps follow exactly the same rules.
 */
Route::prefix('courier/v1')->name('courier-api.')->group(function () {
    Route::get('app-config', [CourierAccountController::class, 'config'])->name('config');
    Route::post('auth/otp', [CourierAuthController::class, 'requestCode'])->middleware('throttle:otp')->name('otp');
    Route::post('auth/verify', [CourierAuthController::class, 'verify'])->middleware('throttle:10,1')->name('verify');

    Route::middleware(['auth:sanctum', 'courier.token', 'tenant', EnsureRole::using(Role::Courier)])->group(function () {
        Route::post('auth/logout', [CourierAuthController::class, 'logout'])->name('logout');
        Route::get('me', [CourierAccountController::class, 'me'])->name('me');
        Route::patch('me/locale', [CourierAccountController::class, 'updateLocale'])->name('locale');
        Route::post('devices', [CourierAccountController::class, 'registerDevice'])->name('devices');
        Route::post('account/deletion-request', [CourierAccountController::class, 'requestDeletion'])->name('deletion');

        Route::get('board', [CourierAppController::class, 'data'])->name('board');
        Route::post('shift/start', [CourierAppController::class, 'startShift'])->name('shift.start');
        Route::post('shift/end', [CourierAppController::class, 'endShift'])->name('shift.end');
        Route::post('locations', [CourierLocationController::class, 'store'])->middleware('throttle:60,1')->name('locations');

        Route::get('tasks/{task}', [CourierAppController::class, 'taskData'])->name('tasks.show');
        Route::post('tasks/{task}/claim', [CourierAppController::class, 'claim'])->name('tasks.claim');
        Route::post('tasks/{task}/step', [CourierAppController::class, 'step'])->name('tasks.step');
        Route::post('tasks/{task}/complete', [CourierAppController::class, 'complete'])->name('tasks.complete');
        Route::post('tasks/{task}/fail', [CourierAppController::class, 'fail'])->name('tasks.fail');
        Route::post('tasks/{task}/code', [CourierAppController::class, 'code'])->middleware('throttle:20,1')->name('tasks.code');
        Route::post('tasks/{task}/scan', [CourierAppController::class, 'scan'])->name('tasks.scan');
        Route::post('tasks/{task}/pods', [CourierAppController::class, 'pod'])->name('tasks.pods');
    });
});
