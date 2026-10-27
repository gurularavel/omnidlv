<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The platform admin signs in as a company's staff member to see and fix the company from
 * the inside. The admin's id stays in the session so they can return to the admin panel.
 */
final class Impersonation
{
    public const SESSION_KEY = 'impersonator_id';

    public static function start(Request $request, User $admin, User $target): void
    {
        Auth::guard('web')->login($target);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $admin->id);
    }

    /** Signs the admin back in. Returns null (and signs out) when the session has no valid admin. */
    public static function stop(Request $request): ?User
    {
        $admin = User::query()->find($request->session()->pull(self::SESSION_KEY));

        if (! $admin?->is_super) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return null;
        }

        Auth::guard('web')->login($admin);
        $request->session()->regenerate();

        return $admin;
    }

    public static function active(): bool
    {
        return session()->has(self::SESSION_KEY);
    }
}
