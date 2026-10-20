<?php

use App\Enums\Role;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
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

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});

// Courier PWA (phase 4). Routes are named now so the panel can link to them.
Route::prefix('courier')->name('courier.')->group(function () {
    Route::view('login', 'courier.login')->middleware('guest')->name('login');
    Route::view('/', 'courier.login')->middleware('auth')->name('home');
});
