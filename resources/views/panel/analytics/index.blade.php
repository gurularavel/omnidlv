@php
    use App\Services\Analytics\AnalyticsService;
    use App\Support\Ui;

    $s = $report['summary'];
    $p = $report['previous'];
    $presets = ['today', '7d', '30d', '90d', 'month', 'last_month'];
    $keep = collect($filters->query())->only(['hub', 'team'])->all();
    $money = fn ($v) => number_format($v, 2, ',', ' ').' '.$currency;
    $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',').'%';

    // Each figure: label, value, delta against the previous span, and whether "up" is good.
    $figures = [
        ['delivered', number_format($s['delivered'], 0, ',', ' '), AnalyticsService::delta($s['delivered'], $p['delivered'] ?? null), true, ''],
        ['success_rate', $pct($s['success_rate']), AnalyticsService::delta($s['success_rate'], $p['success_rate'] ?? null), true, ' '.__('analytics.pp')],
        ['on_time_rate', $pct($s['on_time_rate']), AnalyticsService::delta($s['on_time_rate'], $p['on_time_rate'] ?? null), true, ' '.__('analytics.pp')],
        ['median_minutes', $s['median_minutes'] === null ? '—' : Ui::minutes($s['median_minutes']).($s['median_minutes'] < 100 ? ' '.__('analytics.min') : ''), AnalyticsService::delta($s['median_minutes'], $p['median_minutes'] ?? null), false, ' '.__('analytics.min')],
        ['rating', $s['rating'] === null ? '—' : number_format($s['rating'], 1, ',', ''), AnalyticsService::delta($s['rating'], $p['rating'] ?? null), true, ''],
        ['cash', number_format($s['cash'], 0, ',', ' ').' '.$currency, null, true, ''],
    ];
    $chartLabels = [
        'delivered' => __('analytics.outcome.completed'),
        'failed' => __('analytics.outcome.failed'),
        'cancelled' => __('analytics.outcome.cancelled'),
        'on_time' => __('analytics.figures.on_time_rate'),
    ];
    $reasonMax = max(1, ...array_column($report['reasons'], 'count'));
@endphp
<x-layouts::app :title="__('analytics.title')">
    <x-slot:actions>
        <div x-data="{ open: false }" class="relative">
            <button type="button" class="btn" @click="open = !open" :aria-expanded="open" aria-haspopup="true">
                <x-icon name="download" :size="16" /><span class="hidden sm:inline">{{ __('analytics.export.button') }}</span>
            </button>
            <div x-show="open" x-cloak @click.outside="open = false" x-transition.origin.top.right
                 class="plate absolute right-0 top-11 z-30 flex w-64 flex-col p-1.5 shadow-[0_12px_32px_rgb(0_0_0/0.4)]">
                @foreach (['tasks', 'couriers'] as $r)
                    <span class="t-label px-3 pb-1 pt-2 text-[var(--text-3)]">{{ __('analytics.export.'.$r) }}</span>
                    @foreach (['xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV'] as $format => $name)
                        <a href="{{ route('analytics.export', [...$filters->query(), 'report' => $r, 'format' => $format]) }}"
                           class="flex items-center gap-2 rounded px-3 py-2 hover:bg-[var(--surface-hover)]" @click="open = false">
                            <x-icon name="file-spreadsheet" :size="16" class="text-[var(--text-3)]" />{{ $name }}
                        </a>
                    @endforeach
                @endforeach
            </div>
        </div>
    </x-slot:actions>

    {{-- One row of filters scopes everything below. --}}
    <nav class="-mx-1 mb-4 flex gap-x-1 overflow-x-auto border-b-[1.5px] border-[var(--outline)] [scrollbar-width:none]" aria-label="{{ __('analytics.period') }}">
        @foreach ($presets as $key)
            <a href="{{ route('analytics.index', [...$keep, 'period' => $key]) }}" class="nav-link shrink-0"
               @if ($filters->preset === $key) aria-current="page" @endif>{{ __('analytics.presets.'.$key) }}</a>
        @endforeach
    </nav>

    <form method="GET" x-data="{ period: @js($filters->preset) }"
          class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-[150px_150px_200px_200px_auto] lg:items-end">
        <input type="hidden" name="period" :value="period" value="{{ $filters->preset }}">
        <label class="flex flex-col gap-1.5">
            <span class="t-label">{{ __('analytics.from') }}</span>
            <input type="date" name="from" value="{{ $filters->from->toDateString() }}" class="input" @change="period = 'custom'">
        </label>
        <label class="flex flex-col gap-1.5">
            <span class="t-label">{{ __('analytics.to') }}</span>
            <input type="date" name="to" value="{{ $filters->to->toDateString() }}" class="input" @change="period = 'custom'">
        </label>
        <label class="flex flex-col gap-1.5">
            <span class="t-label">{{ __('Hub') }}</span>
            <x-select name="hub" :options="$hubs" :value="$filters->hubId" :placeholder="__('All hubs')" />
        </label>
        <label class="flex flex-col gap-1.5">
            <span class="t-label">{{ __('Team') }}</span>
            <x-select name="team" :options="$teams" :value="$filters->teamId" :placeholder="__('analytics.all_teams')" />
        </label>
        <div class="flex gap-2">
            <button class="btn"><x-icon name="filter" :size="16" />{{ __('Filter') }}</button>
            @if ($filters->hubId || $filters->teamId || $filters->preset !== '7d')
                <a href="{{ route('analytics.index') }}" class="btn btn-ghost">{{ __('Reset') }}</a>
            @endif
        </div>
    </form>

    @if ($filters->clamped)
        <div class="flash mb-5" role="status">
            <x-icon name="info" class="mt-0.5 text-[var(--text-2)]" />
            <span>{{ __('analytics.history_limit', ['days' => $filters->historyDays]) }}</span>
        </div>
    @endif

    <p class="mb-3 text-[var(--text-2)]">
        {{ __('analytics.span', ['from' => $filters->from->translatedFormat('d M Y'), 'to' => $filters->to->translatedFormat('d M Y')]) }}
        · {{ trans_choice('analytics.finished', $s['total'], ['count' => $s['total']]) }}
        @if ($p) · {{ trans_choice('analytics.compared', $filters->days(), ['count' => $filters->days()]) }} @endif
    </p>

    {{-- The figures: one plate, hairline-divided, every number tabular. --}}
    {{-- gap-px over a hairline ground draws the dividers at every breakpoint. --}}
    <section class="plate mb-6 grid grid-cols-2 gap-px overflow-hidden bg-[var(--hairline)] sm:grid-cols-3 xl:grid-cols-6" aria-label="{{ __('analytics.summary') }}">
        @foreach ($figures as [$key, $value, $delta, $upIsGood, $unit])
            <div class="flex flex-col gap-2 bg-[var(--surface-raised)] px-5 py-4">
                <span class="t-label">{{ __('analytics.figures.'.$key) }}</span>
                <span class="t-display">{{ $value }}</span>
                <span class="text-[13px] text-[var(--text-3)]">
                    @if ($delta === null || $delta == 0)
                        @if ($key === 'rating' && $s['rated'])
                            {{ trans_choice('analytics.rated', $s['rated'], ['count' => $s['rated']]) }}
                        @elseif ($key === 'cash')
                            {{ __('analytics.cash_note') }}
                        @else
                            {{ $p ? __('analytics.no_change') : '—' }}
                        @endif
                    @else
                        @php $good = ($delta > 0) === $upIsGood; @endphp
                        <span @class(['text-ink-green-text' => $good, 'text-[var(--danger-text)]' => ! $good])>
                            {{ $delta > 0 ? '▲ +' : '▼ −' }}{{ rtrim(rtrim(number_format(abs($delta), 1, ',', ''), '0'), ',') }}{{ $unit }}
                        </span>
                        {{ __('analytics.vs_previous') }}
                    @endif
                </span>
            </div>
        @endforeach
    </section>

    @if ($s['total'] === 0)
        <div class="plate">
            <x-empty icon="chart-column" :title="__('analytics.empty_title')">{{ __('analytics.empty_body') }}</x-empty>
        </div>
    @else
        <div x-data="analyticsCharts(@js(['series' => $report['series'], 'hours' => $report['hours']]), @js($chartLabels))" class="mb-6 grid gap-6 lg:grid-cols-2">
            <section class="plate lg:col-span-2" aria-labelledby="outcomes-title">
                <div class="plate-head flex-wrap">
                    <h2 id="outcomes-title" class="t-label text-[var(--text)]">{{ __('analytics.charts.outcomes_'.$report['series']['bucket']) }}</h2>
                    <span class="ml-auto flex flex-wrap items-center gap-x-4 gap-y-1 text-[13px] text-[var(--text-2)]">
                        <span class="flex items-center gap-1.5"><span class="legend-key" style="background:var(--chart-delivered)"></span>{{ $chartLabels['delivered'] }}</span>
                        <span class="flex items-center gap-1.5"><span class="legend-key" style="background:var(--chart-cancelled)"></span>{{ $chartLabels['cancelled'] }}</span>
                        <span class="flex items-center gap-1.5"><span class="legend-key is-hatched"></span>{{ $chartLabels['failed'] }}</span>
                    </span>
                </div>
                <div class="px-3 pb-2 pt-4">
                    <div x-ref="outcomes" id="chart-outcomes" class="chart" aria-label="{{ __('analytics.charts.outcomes_'.$report['series']['bucket']) }}"></div>
                </div>
                <details class="border-t border-[var(--hairline)]">
                    <summary class="cursor-pointer px-[18px] py-3 text-[13px] text-[var(--text-2)] hover:text-[var(--text)]">{{ __('analytics.show_table') }}</summary>
                    <div class="max-h-[320px] overflow-auto">
                        <table class="tbl">
                            <thead><tr>
                                <th>{{ __('analytics.columns.date') }}</th>
                                <th class="text-right">{{ $chartLabels['delivered'] }}</th>
                                <th class="text-right">{{ $chartLabels['failed'] }}</th>
                                <th class="text-right">{{ $chartLabels['cancelled'] }}</th>
                                <th class="text-right">{{ $chartLabels['on_time'] }}</th>
                            </tr></thead>
                            <tbody>
                            @foreach ($report['series']['points'] as $point)
                                <tr>
                                    <td>{{ $point['label'] }}</td>
                                    <td class="text-right">{{ $point['delivered'] }}</td>
                                    <td class="text-right">{{ $point['failed'] }}</td>
                                    <td class="text-right">{{ $point['cancelled'] }}</td>
                                    <td class="text-right">{{ $pct($point['on_time_rate']) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </section>

            <section class="plate" aria-labelledby="ontime-title">
                <div class="plate-head"><h2 id="ontime-title" class="t-label text-[var(--text)]">{{ __('analytics.charts.on_time') }}</h2></div>
                <div class="px-3 pb-2 pt-4"><div x-ref="onTime" id="chart-ontime" class="chart"></div></div>
            </section>

            <section class="plate" aria-labelledby="hours-title">
                <div class="plate-head"><h2 id="hours-title" class="t-label text-[var(--text)]">{{ __('analytics.charts.hours') }}</h2></div>
                <div class="px-3 pb-2 pt-4"><div x-ref="hours" id="chart-hours" class="chart"></div></div>
            </section>
        </div>

        {{-- Couriers: the leaderboard dispatch asks for first. --}}
        <section class="plate mb-6 overflow-hidden" aria-labelledby="couriers-title">
            <div class="plate-head">
                <h2 id="couriers-title" class="t-label text-[var(--text)]">{{ __('Couriers') }}</h2>
                <a href="{{ route('analytics.export', [...$filters->query(), 'report' => 'couriers', 'format' => 'xlsx']) }}" class="btn btn-ghost btn-sm ml-auto">
                    <x-icon name="download" :size="14" />.xlsx
                </a>
            </div>
            @if (empty($report['couriers']))
                <p class="px-[18px] py-6 text-[var(--text-2)]">{{ __('analytics.no_couriers') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="tbl min-w-[860px]">
                        <thead><tr>
                            <th>{{ __('Courier') }}</th>
                            <th class="text-right">{{ $chartLabels['delivered'] }}</th>
                            <th class="text-right">{{ $chartLabels['failed'] }}</th>
                            <th class="text-right">{{ $chartLabels['cancelled'] }}</th>
                            <th class="text-right">{{ __('analytics.figures.success_rate') }}</th>
                            <th class="text-right">{{ __('analytics.figures.on_time_rate') }}</th>
                            <th class="text-right">{{ __('analytics.figures.median_minutes') }}</th>
                            <th class="text-right">{{ __('analytics.figures.rating') }}</th>
                            <th class="text-right">{{ __('analytics.figures.cash') }}</th>
                            <th class="text-right">{{ __('analytics.columns.distance_km') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($report['couriers'] as $c)
                            <tr>
                                <td>
                                    <span class="flex items-center gap-3">
                                        <span class="h-[6px] w-6 shrink-0 rounded-full" style="background: {{ $c['ink'] }}"></span>
                                        <a href="{{ route('tasks.index', ['courier_id' => $c['id']]) }}" class="truncate hover:underline">{{ $c['name'] }}</a>
                                    </span>
                                </td>
                                <td class="text-right">{{ $c['delivered'] }}</td>
                                <td @class(['text-right', 'text-[var(--danger-text)]' => $c['failed'] > 0])>{{ $c['failed'] }}</td>
                                <td class="text-right text-[var(--text-2)]">{{ $c['cancelled'] }}</td>
                                <td class="text-right">{{ $pct($c['success_rate']) }}</td>
                                <td class="text-right">{{ $pct($c['on_time_rate']) }}</td>
                                <td class="text-right">{{ $c['median_minutes'] === null ? '—' : Ui::minutes($c['median_minutes']) }}</td>
                                <td class="text-right">{{ $c['rating'] === null ? '—' : number_format($c['rating'], 1, ',', '') }}<span class="text-[var(--text-3)]">{{ $c['rated'] ? ' · '.$c['rated'] : '' }}</span></td>
                                <td class="whitespace-nowrap text-right">{{ $money($c['cash']) }}</td>
                                <td class="text-right">{{ $c['distance_km'] ? number_format($c['distance_km'], 1, ',', ' ') : '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="plate overflow-hidden" aria-labelledby="hubs-title">
                <div class="plate-head"><h2 id="hubs-title" class="t-label text-[var(--text)]">{{ __('Hubs') }}</h2></div>
                <div class="overflow-x-auto">
                    <table class="tbl">
                        <thead><tr>
                            <th>{{ __('Hub') }}</th>
                            <th class="text-right">{{ __('analytics.columns.finished') }}</th>
                            <th class="text-right">{{ __('analytics.figures.success_rate') }}</th>
                            <th class="text-right">{{ __('analytics.figures.on_time_rate') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($report['hubs'] as $h)
                            <tr>
                                <td><a href="{{ route('analytics.index', [...$filters->query(), 'hub' => $h['id']]) }}" class="hover:underline">{{ $h['name'] }}</a></td>
                                <td class="text-right">{{ $h['total'] }}</td>
                                <td class="text-right">{{ $pct($h['success_rate']) }}</td>
                                <td class="text-right">{{ $pct($h['on_time_rate']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="plate" aria-labelledby="reasons-title">
                <div class="plate-head"><h2 id="reasons-title" class="t-label text-[var(--text)]">{{ __('analytics.reasons') }}</h2></div>
                @if (empty($report['reasons']))
                    <p class="px-[18px] py-6 text-[var(--text-2)]">{{ __('analytics.no_reasons') }}</p>
                @else
                    <ul class="flex flex-col gap-3.5 px-[18px] py-4">
                        @foreach ($report['reasons'] as $r)
                            <li class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1.5">
                                <span class="flex min-w-0 items-center gap-2">
                                    <x-status :category="\App\Enums\StatusCategory::from($r['category'])" :label="$r['label']" class="truncate" />
                                </span>
                                <span class="t-value text-right">{{ $r['count'] }}</span>
                                <span class="col-span-2 h-[6px] rounded-full bg-[var(--hairline)]">
                                    <span class="block h-full rounded-full" style="width: {{ max(2, round($r['count'] / $reasonMax * 100)) }}%; background: {{ $r['category'] === 'failed' ? 'var(--chart-failed)' : 'var(--chart-cancelled)' }}"></span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    @endif
</x-layouts::app>
