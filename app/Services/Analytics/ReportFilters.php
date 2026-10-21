<?php

namespace App\Services\Analytics;

use App\Models\Company;
use App\Support\Plans;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The slice every report is computed on: a span of whole days in the company's timezone,
 * optionally narrowed to one hub or team. The plan's history limit clamps the start.
 */
final class ReportFilters
{
    public const PRESETS = ['today', '7d', '30d', '90d', 'month', 'last_month', 'custom'];

    /** Longest span a custom range may cover, so one request never scans years of tasks. */
    public const MAX_DAYS = 366;

    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $preset,
        public readonly string $timezone,
        public readonly ?int $hubId,
        public readonly ?int $teamId,
        public readonly ?int $historyDays,
        public readonly bool $clamped,
    ) {}

    public static function fromRequest(Request $request, Company $company): self
    {
        return self::make(
            $company,
            (string) $request->query('period', '7d'),
            $request->query('from'),
            $request->query('to'),
            $request->integer('hub') ?: null,
            $request->integer('team') ?: null,
        );
    }

    public static function make(Company $company, string $preset, mixed $from = null, mixed $to = null, ?int $hubId = null, ?int $teamId = null): self
    {
        $tz = in_array($company->timezone, timezone_identifiers_list(), true) ? $company->timezone : config('app.timezone');
        $today = CarbonImmutable::now($tz)->startOfDay();
        $preset = in_array($preset, self::PRESETS, true) ? $preset : '7d';

        [$start, $end] = match ($preset) {
            'today' => [$today, $today],
            '30d' => [$today->subDays(29), $today],
            '90d' => [$today->subDays(89), $today],
            'month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'custom' => [self::date($from, $tz) ?? $today->subDays(6), self::date($to, $tz) ?? $today],
            default => [$today->subDays(6), $today],
        };

        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }
        if ($start->diffInDays($end) >= self::MAX_DAYS) {
            $start = $end->subDays(self::MAX_DAYS - 1);
        }

        $historyDays = Plans::get($company->plan_slug)['history_days'];
        $clamped = false;
        if ($historyDays !== null && $start->lessThan($limit = $today->subDays($historyDays - 1))) {
            $start = $limit;
            $end = $end->lessThan($start) ? $start : $end;
            $clamped = true;
        }

        return new self($start, $end->endOfDay(), $preset, $tz, $hubId, $teamId, $historyDays, $clamped);
    }

    /** Number of calendar days covered. */
    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to->startOfDay()) + 1;
    }

    /** The span of equal length just before this one, or null when the plan's history does not reach it. */
    public function previous(): ?self
    {
        $to = $this->from->subDay();
        $from = $to->subDays($this->days() - 1);

        if ($this->historyDays !== null && $from->lessThan(CarbonImmutable::now($this->timezone)->startOfDay()->subDays($this->historyDays - 1))) {
            return null;
        }

        return new self($from, $to->endOfDay(), 'custom', $this->timezone, $this->hubId, $this->teamId, $this->historyDays, false);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} the bounds in UTC, as stored */
    public function utcBounds(): array
    {
        return [$this->from->utc(), $this->to->utc()];
    }

    /** Charts switch from days to weeks past two months, so a bar never shrinks to a hairline. */
    public function bucket(): string
    {
        return $this->days() > 62 ? 'week' : 'day';
    }

    /** Query string that reproduces this slice (links, exports). */
    public function query(): array
    {
        return array_filter([
            'period' => $this->preset,
            'from' => $this->preset === 'custom' ? $this->from->toDateString() : null,
            'to' => $this->preset === 'custom' ? $this->to->toDateString() : null,
            'hub' => $this->hubId,
            'team' => $this->teamId,
        ]);
    }

    private static function date(mixed $value, string $tz): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value, $tz) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
