<?php

namespace App\Models;

use App\Enums\Role;
use App\Support\CurrentCompany;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $guarded = ['id', 'is_super'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_super' => 'boolean',
        ];
    }

    public function employments(): HasMany
    {
        return $this->hasMany(Employment::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'employments')
            ->withPivot(['role_slug', 'is_active'])
            ->withTimestamps();
    }

    public function activeCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'active_company_id');
    }

    /** Employment in the given company, else the current tenant, else the user's active company. */
    public function employment(Company|int|null $company = null): ?Employment
    {
        $companyId = $company instanceof Company ? $company->id : $company;
        $companyId ??= app(CurrentCompany::class)->id() ?? $this->active_company_id;

        // Employment is company-scoped; bypass the scope so a user's other companies stay visible.
        $this->loadMissing(['employments' => fn ($q) => $q->withoutGlobalScope('company')]);

        return $this->employments->firstWhere('company_id', $companyId);
    }

    public function roleIn(Company|int|null $company = null): ?Role
    {
        $employment = $this->employment($company);

        return $employment?->is_active ? Role::from($employment->role_slug) : null;
    }

    public function isCourier(Company|int|null $company = null): bool
    {
        return $this->roleIn($company) === Role::Courier;
    }

    /** Active couriers of the current company (employments carry the tenant scope). */
    public function scopeCouriers(Builder $query, bool $activeOnly = true): void
    {
        $query->whereHas('employments', fn ($q) => $q
            ->where('role_slug', Role::Courier->value)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true)));
    }

    // Courier-side relations. CourierDetail and Team are company-scoped, so these resolve
    // to the current tenant automatically.

    public function courierDetail(): HasOne
    {
        return $this->hasOne(CourierDetail::class);
    }

    public function courierLocation(): HasOne
    {
        return $this->hasOne(CourierLocation::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    public function courierTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'courier_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(CourierShift::class);
    }

    public function locationPings(): HasMany
    {
        return $this->hasMany(CourierLocationPing::class);
    }
}
