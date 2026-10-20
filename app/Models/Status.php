<?php

namespace App\Models;

use App\Enums\StatusCategory;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * System statuses (company_id NULL) are shared; a company sees them plus its own.
 * Each status sits inside one fixed StatusCategory, which drives all business logic.
 */
class Status extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status_category_slug' => StatusCategory::class,
            'name' => 'array',
            'description' => 'array',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'notify_recipient' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $companyId = app(CurrentCompany::class)->id();

            if ($companyId !== null) {
                $builder->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
            }
        });
    }

    public function category(): StatusCategory
    {
        return $this->status_category_slug;
    }

    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->name[$locale] ?? $this->name['en'] ?? $this->slug;
    }

    public function descriptionText(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return $this->description[$locale] ?? $this->description['en'] ?? null;
    }

    /** Default status of a category: the system one, first by sort order. */
    public static function defaultFor(StatusCategory $category): self
    {
        return static::query()
            ->where('status_category_slug', $category->value)
            ->where('is_active', true)
            ->orderByRaw('company_id IS NOT NULL')
            ->orderBy('sort')
            ->firstOrFail();
    }
}
