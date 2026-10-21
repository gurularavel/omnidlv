<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use BelongsToCompany, MassPrunable;

    public const PENDING = 'pending';

    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'next_retry_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /** The log keeps 30 days; the integrator's own system is the long-term record. */
    public function prunable(): Builder
    {
        return static::withoutGlobalScopes()->where('created_at', '<', now()->subDays(30));
    }
}
