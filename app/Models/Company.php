<?php

namespace App\Models;

use App\Enums\Role;
use App\Support\Plans;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

/**
 * Integration API keys are Sanctum tokens owned by the company itself, so a key keeps
 * working when the employee who created it leaves.
 */
class Company extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    // Mirrors the column defaults so a freshly created model carries them before a reload.
    protected $attributes = [
        'timezone' => 'Asia/Baku',
        'locale' => 'az',
        'country' => 'AZ',
        'currency' => 'AZN',
        // Cobalt, not scarlet: on the tracking page scarlet is reserved for failed deliveries.
        'primary_color' => '#2f6bff',
        'secondary_color' => '#0b1230',
        'plan_slug' => 'start',
        'settings' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function employments(): HasMany
    {
        return $this->hasMany(Employment::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'employments')
            ->withPivot(['role_slug', 'is_active'])
            ->withTimestamps();
    }

    public function couriers(): BelongsToMany
    {
        return $this->users()->wherePivot('role_slug', Role::Courier->value);
    }

    public function hubs(): HasMany
    {
        return $this->hasMany(Hub::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at?->isFuture() ?? false;
    }

    public function hasActiveSubscription(): bool
    {
        return ! $this->isSuspended() && ($this->onTrial() || ($this->subscription_ends_at?->isFuture() ?? false));
    }

    /** Suspended by the platform admin: nobody in the company can sign in or use the API. */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /** suspended, trial, active or expired; shown in the platform admin. */
    public function state(): string
    {
        return match (true) {
            $this->isSuspended() => 'suspended',
            $this->onTrial() => 'trial',
            $this->subscription_ends_at?->isFuture() ?? false => 'active',
            default => 'expired',
        };
    }

    public function plan(): array
    {
        return Plans::get($this->plan_slug);
    }

    public function hasFeature(string $feature): bool
    {
        return Plans::allows($this->plan_slug, $feature);
    }
}
