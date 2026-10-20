<?php

namespace App\Models;

use App\Enums\AssignmentType;
use App\Enums\Channel;
use App\Enums\StatusCategory;
use App\Enums\TaskType;
use App\Models\Concerns\BelongsToCompany;
use App\Support\GeoPoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $guarded = ['id', 'company_id', 'tracking_code', 'delivery_code_attempts'];

    protected $hidden = ['delivery_code'];

    protected function casts(): array
    {
        return [
            'type_slug' => TaskType::class,
            'status_category_slug' => StatusCategory::class,
            'assignment_type' => AssignmentType::class,
            'channel' => Channel::class,
            'assignment_options' => 'array',
            'slot_options' => 'array',
            'tags' => 'array',
            'delivery_code' => 'encrypted',
            'status_at' => 'datetime',
            'slot_starts_at' => 'datetime',
            'slot_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'seen_at' => 'datetime',
            'claimed_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'origin_lat' => 'float',
            'origin_lng' => 'float',
            'destination_lat' => 'float',
            'destination_lng' => 'float',
            'amount' => 'decimal:2',
            'is_origin_resolved' => 'boolean',
            'is_destination_resolved' => 'boolean',
            'requires_delivery_code' => 'boolean',
            'requires_delivery_code_mandatory' => 'boolean',
            'requires_pod_photo' => 'boolean',
            'requires_pod_photo_mandatory' => 'boolean',
            'required_dont_ring_the_bell' => 'boolean',
            'requires_contactless' => 'boolean',
            'is_locked' => 'boolean',
            'is_confirmed' => 'boolean',
            'was_finalized_duly' => 'boolean',
            'was_delayed' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }

    public function originHub(): BelongsTo
    {
        return $this->belongsTo(Hub::class, 'origin_hub_id');
    }

    public function destinationHub(): BelongsTo
    {
        return $this->belongsTo(Hub::class, 'destination_hub_id');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaskItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(TaskStatusLog::class)->orderBy('id');
    }

    public function category(): StatusCategory
    {
        return $this->status_category_slug;
    }

    public function isFinal(): bool
    {
        return $this->status_category_slug->isFinal();
    }

    public function originPoint(): ?GeoPoint
    {
        return GeoPoint::tryFrom($this->origin_lat, $this->origin_lng);
    }

    public function destinationPoint(): ?GeoPoint
    {
        return GeoPoint::tryFrom($this->destination_lat, $this->destination_lng);
    }

    /** The point the courier must reach to finish: destination for drop-off, origin for pickup. */
    public function targetPoint(): ?GeoPoint
    {
        return $this->type_slug === TaskType::Pickup ? $this->originPoint() : $this->destinationPoint();
    }

    public function isLate(): bool
    {
        return ! $this->isFinal() && $this->ends_at?->isPast();
    }

    public function scopeInCategory(Builder $query, StatusCategory|string ...$categories): void
    {
        $query->whereIn('status_category_slug', array_map(
            fn ($c) => $c instanceof StatusCategory ? $c->value : $c,
            $categories,
        ));
    }

    public function scopeActive(Builder $query): void
    {
        $query->inCategory(...StatusCategory::active());
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNotIn('status_category_slug', ['completed', 'cancelled', 'failed']);
    }
}
