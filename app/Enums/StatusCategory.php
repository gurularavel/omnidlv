<?php

namespace App\Enums;

enum StatusCategory: string
{
    case Created = 'created';
    case Pool = 'pool';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case OnDelivery = 'on_delivery';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return __('statuses.categories.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Created => '#64748b',
            self::Pool => '#a855f7',
            self::Assigned => '#3b82f6',
            self::InProgress => '#f59e0b',
            self::OnDelivery => '#0ea5e9',
            self::Completed => '#16a34a',
            self::Cancelled => '#71717a',
            self::Failed => '#dc2626',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::Failed], true);
    }

    /** Categories that count as "the courier currently holds this task". */
    public static function active(): array
    {
        return [self::Assigned, self::InProgress, self::OnDelivery];
    }

    /**
     * Allowed forward moves. Final categories only reopen through an explicit admin action
     * (StatusMachine::reopen), never through a normal status update.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Created => [self::Pool, self::Assigned, self::Cancelled, self::Failed],
            self::Pool => [self::Created, self::Assigned, self::Cancelled, self::Failed],
            self::Assigned => [self::Created, self::Pool, self::Assigned, self::InProgress, self::OnDelivery, self::Cancelled, self::Failed],
            // A moving task can still be handed back or to another courier (breakdown, reroute).
            self::InProgress => [self::Created, self::Pool, self::Assigned, self::InProgress, self::OnDelivery, self::Completed, self::Cancelled, self::Failed],
            self::OnDelivery => [self::Created, self::Pool, self::Assigned, self::InProgress, self::OnDelivery, self::Completed, self::Cancelled, self::Failed],
            self::Completed, self::Cancelled, self::Failed => [],
        };
    }

    public function canMoveTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }
}
