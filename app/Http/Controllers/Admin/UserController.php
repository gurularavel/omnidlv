<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $kind = $request->input('kind');

        $users = User::query()
            ->with(['employments' => fn ($q) => $q->withoutGlobalScopes()->with('company:id,name,suspended_at')])
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'ilike', "%{$term}%")->orWhere('email', 'ilike', "%{$term}%")->orWhere('phone', 'ilike', "%{$term}%")))
            ->when($kind === 'super', fn ($q) => $q->where('is_super', true))
            ->when($kind === 'staff', fn ($q) => $q->whereHas('employments', fn ($e) => $e->withoutGlobalScopes()->where('role_slug', '<>', Role::Courier->value)))
            ->when($kind === 'courier', fn ($q) => $q->whereHas('employments', fn ($e) => $e->withoutGlobalScopes()->where('role_slug', Role::Courier->value)))
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users]);
    }

    /** Sets a new random password and shows it once. Couriers sign in by SMS code and have none. */
    public function password(User $user)
    {
        abort_if(! $user->email, 422, __('admin.users.no_email'));

        $password = Str::password(14, symbols: false);
        $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

        return back()
            ->with('status', __('admin.users.password_reset', ['name' => $user->name]))
            ->with('credentials', ['email' => $user->email, 'password' => $password]);
    }

    public function impersonate(Request $request, User $user)
    {
        // Another platform admin is not someone to act as.
        abort_if($user->is_super || $user->is($request->user()), 403);

        $employment = $user->employments()->withoutGlobalScopes()->where('is_active', true)
            ->where('role_slug', '<>', Role::Courier->value)
            ->when($request->integer('company'), fn ($q, $id) => $q->where('company_id', $id))
            ->first();

        if ($employment === null) {
            return back()->with('error', __('admin.users.cannot_impersonate'));
        }

        $user->forceFill(['active_company_id' => $employment->company_id])->saveQuietly();
        Impersonation::start($request, $request->user(), $user);

        return redirect()->route('dashboard');
    }

    public function leave(Request $request)
    {
        $companyId = $request->user()?->active_company_id;
        $admin = Impersonation::stop($request);

        if ($admin === null) {
            return redirect()->route('login');
        }

        return $companyId
            ? redirect()->route('admin.companies.show', $companyId)
            : redirect()->route('admin.dashboard');
    }
}
