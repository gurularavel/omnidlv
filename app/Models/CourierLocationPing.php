<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** GPS history (thinned to one row per 10 s per courier). */
class CourierLocationPing extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'speed' => 'float',
            'recorded_at' => 'datetime',
        ];
    }
}
