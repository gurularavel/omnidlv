<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Integration API: the Sanctum token belongs to a Company, which becomes the tenant. */
class SetTenantFromToken
{
    public function __construct(private readonly CurrentCompany $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->user();

        if (! $company instanceof Company) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $company->hasActiveSubscription()) {
            return response()->json(['message' => __('billing.subscription_inactive'), 'code' => 'subscription_inactive'], 402);
        }

        $this->tenant->set($company);

        return $next($request);
    }
}
