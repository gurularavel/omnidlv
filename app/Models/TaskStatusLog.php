<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\StatusCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskStatusLog extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status_category_slug' => StatusCategory::class,
            'channel' => Channel::class,
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }
}
