<?php

namespace App\Models;

use App\Enums\VehicleType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierDetail extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id', 'rating', 'scored_tasks_count'];

    protected function casts(): array
    {
        return [
            'vehicle_type_slug' => VehicleType::class,
            'tags' => 'array',
            'rating' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
