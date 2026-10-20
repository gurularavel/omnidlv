<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('role:owner,admin'). Runs after the tenant middleware. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->roleIn();

        if ($role === null || ! in_array($role->value, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }

    public static function using(Role ...$roles): string
    {
        return 'role:'.implode(',', array_map(fn (Role $r) => $r->value, $roles));
    }
}
