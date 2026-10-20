<?php

namespace App\Queries;

use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Task list filters shared by the integration API and the panel. Accepts both
 * `?status_category_slug=` and QDelivery's `?filter[status_category_slug]=` styles.
 */
class TaskFilter
{
    public static function fromRequest(Request $request): array
    {
        return array_merge($request->query(), (array) $request->query('filter', []));
    }

    public static function apply(Builder $query, array $f): Builder
    {
        $list = fn ($v) => array_values(array_filter(is_array($v) ? $v : explode(',', (string) $v), fn ($x) => $x !== ''));

        return $query
            ->when($f['status_category_slug'] ?? null, fn ($q, $v) => $q->whereIn('status_category_slug', $list($v)))
            ->when($f['status_id'] ?? null, fn ($q, $v) => $q->whereIn('status_id', $list($v)))
            ->when($f['courier_id'] ?? null, fn ($q, $v) => $v === 'none' ? $q->whereNull('courier_id') : $q->whereIn('courier_id', $list($v)))
            ->when($f['hub_id'] ?? null, fn ($q, $v) => $q->whereIn('hub_id', $list($v)))
            ->when($f['team_id'] ?? null, fn ($q, $v) => $q->whereIn('team_id', $list($v)))
            ->when($f['type_slug'] ?? $f['action_slug'] ?? null, fn ($q, $v) => $q->where('type_slug', $v))
            ->when($f['integration_id'] ?? null, fn ($q, $v) => $q->whereIn('integration_id', $list($v)))
            ->when($f['tracking_code'] ?? null, fn ($q, $v) => $q->whereIn('tracking_code', $list($v)))
            ->when($f['slot'] ?? null, fn ($q, $v) => $q->where('slot', $v))
            ->when($f['tags'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($list, $v) {
                foreach ($list($v) as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            }))
            ->when(isset($f['locked']) && $f['locked'] !== '', fn ($q) => $q->where('is_locked', filter_var($f['locked'], FILTER_VALIDATE_BOOL)))
            ->when($f['late'] ?? null, fn ($q) => $q->open()->where('ends_at', '<', now()))
            ->when($f['unresolved'] ?? null, fn ($q) => $q->where(fn ($q) => $q->where('is_destination_resolved', false)->orWhere('is_origin_resolved', false)))
            ->when($f['date'] ?? null, fn ($q, $v) => self::dateRange($q, 'ends_at', $v))
            ->when($f['created'] ?? null, fn ($q, $v) => self::dateRange($q, 'created_at', $v))
            ->when(trim((string) ($f['q'] ?? '')), function ($q, $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $q->where(fn ($q) => $q
                    ->where('tracking_code', 'ilike', $like)
                    ->orWhere('integration_id', 'ilike', $like)
                    ->orWhere('destination_name', 'ilike', $like)
                    ->orWhere('destination_phone', 'ilike', $like)
                    ->orWhere('destination_address', 'ilike', $like)
                    ->orWhere('origin_name', 'ilike', $like));
            });
    }

    /** "2026-10-01,2026-10-31" (QDelivery) or a single day, interpreted in the company timezone. */
    private static function dateRange(Builder $q, string $column, string $value): void
    {
        $tz = app(CurrentCompany::class)->get()?->timezone ?? config('app.timezone');
        $parts = array_map('trim', explode(',', $value));

        try {
            $from = Carbon::parse($parts[0], $tz)->startOfDay()->utc();
            $to = Carbon::parse($parts[1] ?? $parts[0], $tz)->endOfDay()->utc();
        } catch (\Throwable) {
            return;
        }

        $q->whereBetween($column, [$from, $to]);
    }
}
