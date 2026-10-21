<?php

namespace App\Services\Analytics;

use App\Models\Hub;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Support\Ui;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Delivery KPIs over finished tasks. A task counts in the day it finished (ended_at), in the
 * company's timezone. Success excludes cancellations: a cancelled order is not the courier's miss.
 */
class AnalyticsService
{
    private const OUTCOMES = ['completed', 'failed', 'cancelled'];

    public function report(ReportFilters $f): array
    {
        $summary = $this->summary($f);
        $previous = ($p = $f->previous()) ? $this->summary($p) : null;

        return [
            'summary' => $summary,
            'previous' => $previous,
            'series' => $this->series($f),
            'hours' => $this->hours($f),
            'couriers' => $this->couriers($f),
            'hubs' => $this->hubs($f),
            'reasons' => $this->reasons($f),
        ];
    }

    /** Finished tasks in the slice. Every report and export starts here. */
    public function finished(ReportFilters $f): Builder
    {
        return Task::query()
            ->inCategory(...self::OUTCOMES)
            ->whereBetween('ended_at', $f->utcBounds())
            ->when($f->hubId, fn ($q) => $q->where('hub_id', $f->hubId))
            ->when($f->teamId, fn ($q) => $q->where('team_id', $f->teamId));
    }

    public function summary(ReportFilters $f): array
    {
        $row = $this->finished($f)->toBase()->selectRaw($this->aggregates())->first();

        return $this->kpis($row);
    }

    /** One point per day (or week): outcomes and on-time rate. Empty buckets are filled with zeros. */
    public function series(ReportFilters $f): array
    {
        $bucket = $f->bucket();
        $rows = $this->finished($f)->toBase()
            ->selectRaw("date_trunc('{$bucket}', {$this->local()})::date as bucket", [$f->timezone])
            ->selectRaw($this->aggregates())
            ->groupByRaw('1')
            ->get()
            ->keyBy(fn ($r) => (string) $r->bucket);

        $points = [];
        $cursor = $bucket === 'week' ? $f->from->startOfWeek(Carbon::MONDAY) : $f->from; // date_trunc('week') is ISO: Monday
        while ($cursor->lessThanOrEqualTo($f->to)) {
            $key = $cursor->toDateString();
            $k = $this->kpis($rows->get($key));
            $points[] = [
                'date' => $key,
                'label' => $bucket === 'week'
                    ? $cursor->translatedFormat('d M').'–'.$cursor->addDays(6)->translatedFormat('d M')
                    : $cursor->translatedFormat('D, d M'),
                'short' => $cursor->translatedFormat('d.m'),
                'delivered' => $k['delivered'],
                'failed' => $k['failed'],
                'cancelled' => $k['cancelled'],
                'on_time_rate' => $k['on_time_rate'],
            ];
            $cursor = $bucket === 'week' ? $cursor->addWeek() : $cursor->addDay();
        }

        return ['bucket' => $bucket, 'points' => $points];
    }

    /** Deliveries by hour of the day they were handed over: when the network is busiest. */
    public function hours(ReportFilters $f): array
    {
        $counts = $this->finished($f)->toBase()
            ->where('status_category_slug', 'completed')
            ->selectRaw("extract(hour from {$this->local()})::int as hour, count(*) as n", [$f->timezone])
            ->groupByRaw('1')
            ->pluck('n', 'hour');

        return array_map(fn ($h) => ['hour' => $h, 'delivered' => (int) ($counts[$h] ?? 0)], range(0, 23));
    }

    public function couriers(ReportFilters $f): array
    {
        $rows = $this->finished($f)->toBase()
            ->whereNotNull('courier_id')
            ->select('courier_id')
            ->selectRaw($this->aggregates())
            ->groupBy('courier_id')
            ->get();

        $users = User::query()->with('teams:teams.id,teams.color')->whereIn('id', $rows->pluck('courier_id'))->get()->keyBy('id');

        return $rows
            ->map(fn ($r) => [
                'id' => (int) $r->courier_id,
                'name' => $users[$r->courier_id]->name ?? '#'.$r->courier_id,
                'ink' => Ui::courierInk($users[$r->courier_id] ?? null),
                ...$this->kpis($r),
            ])
            ->sortBy([['delivered', 'desc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    public function hubs(ReportFilters $f): array
    {
        $rows = $this->finished($f)->toBase()
            ->select('hub_id')
            ->selectRaw($this->aggregates())
            ->groupBy('hub_id')
            ->get();

        $names = Hub::query()->whereIn('id', $rows->pluck('hub_id'))->pluck('name', 'id');

        return $rows
            ->map(fn ($r) => ['id' => (int) $r->hub_id, 'name' => $names[$r->hub_id] ?? '#'.$r->hub_id, ...$this->kpis($r)])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /** Why tasks did not reach the recipient: the failed and cancelled statuses, most frequent first. */
    public function reasons(ReportFilters $f): array
    {
        $rows = $this->finished($f)->toBase()
            ->whereIn('status_category_slug', ['failed', 'cancelled'])
            ->select('status_id', 'status_category_slug')
            ->selectRaw('count(*) as n')
            ->groupBy('status_id', 'status_category_slug')
            ->orderByDesc('n')
            ->get();

        $statuses = Status::withoutGlobalScopes()->whereIn('id', $rows->pluck('status_id'))->get()->keyBy('id');

        return $rows->map(fn ($r) => [
            'status_id' => (int) $r->status_id,
            'category' => $r->status_category_slug,
            'label' => $statuses[$r->status_id]?->label() ?? '—',
            'count' => (int) $r->n,
        ])->all();
    }

    /** ended_at (stored as UTC) as wall-clock time in the company's timezone; binds one value. */
    private function local(): string
    {
        return "(ended_at at time zone 'UTC' at time zone ?)";
    }

    private function aggregates(): string
    {
        return <<<'SQL'
            count(*) as total,
            count(*) filter (where status_category_slug = 'completed') as delivered,
            count(*) filter (where status_category_slug = 'failed') as failed,
            count(*) filter (where status_category_slug = 'cancelled') as cancelled,
            count(*) filter (where status_category_slug = 'completed' and not was_delayed) as on_time,
            percentile_cont(0.5) within group (order by extract(epoch from ended_at - started_at))
                filter (where status_category_slug = 'completed' and started_at is not null and ended_at >= started_at) as median_seconds,
            avg(rating) filter (where rating > 0) as rating,
            count(*) filter (where rating > 0) as rated,
            coalesce(sum(amount) filter (where status_category_slug = 'completed' and payment_type = 'cash'), 0) as cash,
            coalesce(sum(distance) filter (where status_category_slug = 'completed'), 0) as distance
            SQL;
    }

    private function kpis(?object $r): array
    {
        $delivered = (int) ($r->delivered ?? 0);
        $failed = (int) ($r->failed ?? 0);
        $median = isset($r->median_seconds) ? (int) round($r->median_seconds / 60) : null;

        return [
            'total' => (int) ($r->total ?? 0),
            'delivered' => $delivered,
            'failed' => $failed,
            'cancelled' => (int) ($r->cancelled ?? 0),
            'success_rate' => $this->rate($delivered, $delivered + $failed),
            'on_time_rate' => $this->rate((int) ($r->on_time ?? 0), $delivered),
            'median_minutes' => $median,
            'rating' => isset($r->rating) ? round((float) $r->rating, 1) : null,
            'rated' => (int) ($r->rated ?? 0),
            'cash' => round((float) ($r->cash ?? 0), 2),
            'distance_km' => round(((int) ($r->distance ?? 0)) / 1000, 1),
        ];
    }

    private function rate(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }

    /** "+3.2" style change between two values, null when either side is missing. */
    public static function delta(?float $now, ?float $before): ?float
    {
        return $now === null || $before === null ? null : round($now - $before, 1);
    }

    public static function localDate(Carbon $time, string $tz, string $format = 'd.m.Y H:i'): string
    {
        return $time->copy()->setTimezone($tz)->format($format);
    }
}
