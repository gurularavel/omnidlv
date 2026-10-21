<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskPlan extends Model
{
    use BelongsToCompany;

    public const PLANNED = 'planned';

    public const IN_PROGRESS = 'in_progress';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'start' => 'array',
            'end' => 'array',
            'geometry' => 'array',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'plan_id')->orderBy('route_order');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }
}
