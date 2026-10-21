<?php

namespace App\Models;

use App\Support\GeoPoint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Latest known state of a courier. History lives in courier_location_pings. */
class CourierLocation extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'is_working' => 'boolean',
            'on_duty' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
            'speed' => 'float',
            'last_seen_at' => 'datetime',
            'fixed_at' => 'datetime',
            'is_mock' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function point(): ?GeoPoint
    {
        return GeoPoint::tryFrom($this->lat, $this->lng);
    }
}
