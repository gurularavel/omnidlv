<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A public FAQ entry in one language, edited in the platform admin. */
class Faq extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Published entries in the locale, falling back to Azerbaijani when it has none. */
    public static function published(string $locale): Builder
    {
        $locale = static::query()->where('locale', $locale)->where('is_active', true)->exists() ? $locale : 'az';

        return static::query()->where('locale', $locale)->where('is_active', true)->orderBy('sort')->orderBy('id');
    }
}
