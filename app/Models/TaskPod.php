<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/** Proof of delivery: a photo or a signature, stored on the private disk. */
class TaskPod extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'taken_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** Short-lived signed link; POD files are never public. */
    public function url(): string
    {
        return URL::temporarySignedRoute('pods.show', now()->addMinutes(30), ['pod' => $this->id]);
    }
}
