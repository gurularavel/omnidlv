<?php

namespace App\Models;

use App\Support\Plans;
use Illuminate\Database\Eloquent\Model;

/** A SaaS plan, edited by the platform admin. Companies refer to it by slug. */
class Plan extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'features' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'float',
            'price_yearly' => 'float',
            'history_days' => 'integer',
            'max_couriers' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Plans::flush());
        static::deleted(fn () => Plans::flush());
    }

    public function companiesCount(): int
    {
        return Company::query()->where('plan_slug', $this->slug)->count();
    }
}
