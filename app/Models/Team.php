<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\GeoPoint;
use App\Support\Polygon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $guarded = ['id', 'company_id', 'min_lat', 'max_lat', 'min_lng', 'max_lng'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'polygon' => 'array',
            'tags' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Team $team) {
            $bbox = $team->polygon ? Polygon::bbox($team->polygon) : array_fill_keys(['min_lat', 'max_lat', 'min_lng', 'max_lng'], null);
            $team->forceFill($bbox);
        });
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }

    public function couriers(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function covers(GeoPoint $point): bool
    {
        return $this->polygon !== null && Polygon::contains($this->polygon, $point);
    }

    /** The first team (optionally within a hub) whose polygon contains the point. */
    public static function forPoint(GeoPoint $point, ?int $hubId = null): ?self
    {
        return static::query()
            ->whereNotNull('polygon')
            ->when($hubId, fn ($q) => $q->where('hub_id', $hubId))
            ->where('min_lat', '<=', $point->lat)->where('max_lat', '>=', $point->lat)
            ->where('min_lng', '<=', $point->lng)->where('max_lng', '>=', $point->lng)
            ->orderBy('id')
            ->get()
            ->first(fn (Team $team) => $team->covers($point));
    }
}
