<?php

namespace App\Models;

use App\Enums\WebhookEvent;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebhookEndpoint extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'secret' => 'encrypted',
            'is_active' => 'boolean',
            'disabled_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }

    public static function newSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function scopeListeningTo(Builder $query, WebhookEvent $event): void
    {
        $query->where('is_active', true)->whereJsonContains('events', $event->value);
    }

    public function listensTo(WebhookEvent $event): bool
    {
        return in_array($event->value, $this->events ?? [], true);
    }
}
