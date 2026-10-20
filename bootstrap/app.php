<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\NormalizeBearerToken;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetTenantFromToken;
use App\Http\Middleware\SetTenantFromUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/panel.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => SetTenantFromUser::class,
            'tenant.token' => SetTenantFromToken::class,
            'role' => EnsureRole::class,
        ]);

        $middleware->web(append: [SetLocale::class]);
        $middleware->api(prepend: [NormalizeBearerToken::class], append: [SetLocale::class]);

        // Route-model binding must run after the tenant is known, otherwise {task} would be
        // resolved without the company scope and could load another company's record.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetTenantFromUser::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetTenantFromToken::class);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('courier', 'courier/*') ? route('courier.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isCourier() ? route('courier.home') : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
