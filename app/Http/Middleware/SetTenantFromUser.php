<?php

namespace App\Http\Middleware;

use App\Models\Employment;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/** Panel and courier app: the tenant is the signed-in user's active company. */
class SetTenantFromUser
{
    public function __construct(private readonly CurrentCompany $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $employment = Employment::withoutGlobalScopes()
            ->with('company')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderByRaw('company_id = ? DESC', [$user->active_company_id ?? 0])
            ->first();

        if ($employment === null || $employment->company === null) {
            // A platform admin need not work for any company; their home is the admin panel.
            if ($user->is_super && ! $request->expectsJson()) {
                return redirect()->route('admin.dashboard');
            }

            abort(403, __('auth.no_company'));
        }

        // The platform admin may still look inside a suspended company while signed in as its staff.
        if ($employment->company->isSuspended() && ! ($request->hasSession() && $request->session()->has(Impersonation::SESSION_KEY))) {
            abort(403, __('auth.company_suspended'));
        }

        if ($user->active_company_id !== $employment->company_id) {
            $user->forceFill(['active_company_id' => $employment->company_id])->saveQuietly();
        }

        $this->tenant->set($employment->company);
        $user->setRelation('activeCompany', $employment->company);

        View::share('currentCompany', $employment->company);
        View::share('currentRole', $employment->role());

        return $next($request);
    }
}
