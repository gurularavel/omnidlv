<?php

use App\Enums\Role;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Courier\AppController as CourierAppController;
use App\Http\Controllers\Courier\AuthController as CourierAuthController;
use App\Http\Controllers\Panel\PodController;
use App\Http\Controllers\Panel\SmsLogController;
use App\Http\Controllers\Panel\CourierController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\HubController;
use App\Http\Controllers\Panel\IntegrationController;
use App\Http\Controllers\Panel\SettingsController;
use App\Http\Controllers\Panel\TaskController;
use App\Http\Controllers\Panel\TeamController;
use App\Http\Middleware\EnsureRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
 | Dispatcher panel (/app) and courier PWA (/courier). Loaded with the "web" middleware group
 | from bootstrap/app.php.
 */

Route::middleware('guest')->group(function () {
    Route::get('login', [SessionController::class, 'create'])->name('login');
    Route::post('login', [SessionController::class, 'store'])->middleware('throttle:20,1');
    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:10,1');
});

Route::post('logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

$staff = EnsureRole::using(Role::Owner, Role::Admin, Role::Dispatcher);
$managers = EnsureRole::using(Role::Owner, Role::Admin);

Route::prefix('app')->middleware(['auth', 'tenant', $staff])->group(function () use ($managers) {
    Route::bind('courier', fn ($id) => User::query()->couriers()->findOrFail($id));

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('live', [DashboardController::class, 'live'])->name('dashboard.live');

    Route::resource('tasks', TaskController::class)->except('destroy');
    Route::post('tasks/{task}/status', [TaskController::class, 'status'])->name('tasks.status');
    Route::post('tasks/{task}/assign', [TaskController::class, 'assign'])->name('tasks.assign');
    Route::post('tasks/{task}/lock', [TaskController::class, 'lock'])->name('tasks.lock');

    Route::resource('hubs', HubController::class)->except('show');
    Route::resource('teams', TeamController::class)->except('show');
    Route::resource('couriers', CourierController::class)->except('show');

    Route::middleware($managers)->group(function () {
        Route::get('integration', [IntegrationController::class, 'keys'])->name('integration.keys');
        Route::post('integration/keys', [IntegrationController::class, 'store'])->name('integration.keys.store');
        Route::delete('integration/keys/{token}', [IntegrationController::class, 'destroy'])->name('integration.keys.destroy');

        Route::get('sms', SmsLogController::class)->name('sms.index');

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});

// Courier PWA: SMS-code sign-in, then a mobile app whose actions are JSON endpoints.
Route::prefix('courier')->name('courier.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [CourierAuthController::class, 'phone'])->name('login');
        Route::post('login', [CourierAuthController::class, 'sendCode'])->middleware('throttle:otp')->name('login.send');
        Route::get('login/code', [CourierAuthController::class, 'verifyForm'])->name('verify');
        Route::post('login/code', [CourierAuthController::class, 'verify'])->middleware('throttle:10,1')->name('verify.submit');
    });

    Route::middleware(['auth', 'tenant', EnsureRole::using(Role::Courier)])->group(function () {
        Route::get('/', [CourierAppController::class, 'home'])->name('home');
        Route::get('data', [CourierAppController::class, 'data'])->name('data');
        Route::post('shift/start', [CourierAppController::class, 'startShift'])->name('shift.start');
        Route::post('shift/end', [CourierAppController::class, 'endShift'])->name('shift.end');
        Route::post('location', [CourierAppController::class, 'location'])->middleware('throttle:30,1')->name('location');

        Route::get('tasks/{task}', [CourierAppController::class, 'show'])->name('task');
        Route::get('tasks/{task}/data', [CourierAppController::class, 'taskData'])->name('task.data');
        Route::post('tasks/{task}/step', [CourierAppController::class, 'step'])->name('task.step');
        Route::post('tasks/{task}/complete', [CourierAppController::class, 'complete'])->name('task.complete');
        Route::post('tasks/{task}/fail', [CourierAppController::class, 'fail'])->name('task.fail');
        Route::post('tasks/{task}/claim', [CourierAppController::class, 'claim'])->name('task.claim');
        Route::post('tasks/{task}/code', [CourierAppController::class, 'code'])->middleware('throttle:20,1')->name('task.code');
        Route::post('tasks/{task}/pod', [CourierAppController::class, 'pod'])->name('task.pod');
        Route::post('tasks/{task}/scan', [CourierAppController::class, 'scan'])->name('task.scan');
    });

    Route::post('logout', [CourierAuthController::class, 'logout'])->middleware('auth')->name('logout');
});

// Proof-of-delivery files: signed, short-lived links for signed-in staff or the courier.
Route::get('pods/{pod}', PodController::class)->middleware(['auth', 'tenant', 'signed'])->name('pods.show');
