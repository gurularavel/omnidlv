<?php

namespace App\Http\Requests\Concerns;

use App\Enums\Role;
use App\Support\CurrentCompany;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rule;

/** exists-rules restricted to the current tenant, so foreign ids fail validation. */
trait TenantRules
{
    protected function companyId(): ?int
    {
        return app(CurrentCompany::class)->id();
    }

    protected function existsInCompany(string $table): Exists
    {
        return Rule::exists($table, 'id')
            ->where('company_id', $this->companyId())
            ->whereNull('deleted_at');
    }

    protected function existsCourier(): Exists
    {
        return Rule::exists('employments', 'user_id')
            ->where('company_id', $this->companyId())
            ->where('role_slug', Role::Courier->value)
            ->where('is_active', true);
    }
}
