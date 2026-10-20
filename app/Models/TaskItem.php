<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tenant isolation comes through the parent task, which is always resolved company-scoped. */
class TaskItem extends Model
{
    protected $guarded = ['id', 'task_id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'scanned_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
