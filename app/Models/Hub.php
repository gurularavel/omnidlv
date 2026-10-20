<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\GeoPoint;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hub extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'tags' => 'array',
        ];
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function point(): GeoPoint
    {
        return new GeoPoint($this->lat, $this->lng);
    }
}
