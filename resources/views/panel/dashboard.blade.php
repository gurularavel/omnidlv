@php
    $c = $snapshot['counts'];
    $segments = [
        ['late', $c['late'], __('Late'), route('tasks.index', ['late' => 1]), 'text-[var(--danger-text)]'],
        ['unassigned', $c['unassigned'], __('Unassigned'), route('tasks.index', ['status_category_slug' => 'created,pool']), ''],
        ['unresolved', $c['unresolved'], __('Address not found'), route('tasks.index', ['unresolved' => 1]), $c['unresolved'] ? 'text-ink-amber' : ''],
        ['in_motion', $c['in_motion'], __('In motion'), route('tasks.index', ['status_category_slug' => 'assigned,in_progress,on_delivery']), ''],
        ['failed', $c['failed'], __('Failed today'), route('tasks.index', ['status_category_slug' => 'failed']), $c['failed'] ? 'text-[var(--danger-text)]' : ''],
    ];
@endphp
<x-layouts::app :title="__('Live monitor')" full-bleed>
    <x-slot:actions>
        <form method="GET" class="hidden sm:block">
            <select name="hub" class="input h-[38px] w-[200px]" onchange="this.form.submit()" aria-label="{{ __('Hub') }}">
                <option value="">{{ __('All hubs') }}</option>
                @foreach ($hubs as $hub)
                    <option value="{{ $hub->id }}" @selected($hubId === $hub->id)>{{ $hub->name }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('tasks.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" />{{ __('New task') }}</a>
    </x-slot:actions>

    <div x-data="liveMonitor(@js(route('dashboard.live', array_filter(['hub' => $hubId]))), @js($snapshot))"
         class="grid min-h-[calc(100vh-4rem)] grid-rows-[auto_1fr]">

        {{-- Service status board: each segment opens the filtered task list. --}}
        <div class="scroll-strip flex overflow-x-auto border-b-[1.5px] border-[var(--hairline)]">
            @foreach ($segments as [$key, $value, $label, $url, $tone])
                <a href="{{ $url }}" class="group flex min-w-[150px] items-baseline gap-3 border-r border-[var(--hairline)] px-5 py-4 hover:bg-[var(--surface-hover)] lg:px-7">
                    <span class="t-display {{ $tone }}" x-text="data.counts.{{ $key }}">{{ $value }}</span>
                    <span class="t-label group-hover:text-[var(--text)]">{{ $label }}</span>
                </a>
            @endforeach
            <div class="ml-auto flex min-w-[260px] items-baseline gap-3 px-5 py-4 lg:px-7">
                <span class="t-display text-ink-green-text" x-text="data.counts.completed">{{ $c['completed'] }}</span>
                <span class="t-label">{{ __('Delivered today') }}</span>
                <span class="text-[13px] text-[var(--text-2)]" x-show="data.counts.on_time_rate !== null" @if ($c['on_time_rate'] === null) x-cloak @endif>
                    · <span x-text="data.counts.on_time_rate">{{ $c['on_time_rate'] }}</span>% {{ __('on time') }}
                </span>
            </div>
        </div>

        <div class="grid min-h-0 lg:grid-cols-[minmax(0,1fr)_400px]">
            <div class="flex min-h-0 flex-col">
                {{-- Live map --}}
                <div class="relative min-h-[380px] flex-1">
                    <div x-ref="map" class="absolute inset-0" role="application" aria-label="{{ __('Live map of couriers and stops') }}"></div>
                    <div class="pointer-events-none absolute left-4 top-4 z-[400] flex items-center gap-2 rounded-md bg-night-900/85 px-3 py-2 text-[13px]">
                        <span class="size-2 rounded-full bg-ink-scarlet motion-safe:animate-pulse"></span>
                        <span class="t-label text-[var(--text)]">{{ __('Live network') }}</span>
                        <span class="text-[var(--text-3)]" x-show="loading" x-cloak>· {{ __('updating') }}</span>
                    </div>
                </div>

                {{-- Courier lines: each courier's day drawn as a transit line. --}}
                <section class="max-h-[42vh] overflow-y-auto border-t-[1.5px] border-[var(--hairline)]" aria-labelledby="lines-title">
                    <div class="sticky top-0 z-10 flex items-center gap-3 bg-night-900 px-5 py-3 lg:px-7">
                        <h2 id="lines-title" class="t-label text-[var(--text)]">{{ __('Courier lines') }}</h2>
                        <span class="text-[13px] text-[var(--text-3)]" x-text="data.lines.length + ' ' + @js(__('active'))"></span>
                        <span class="ml-auto hidden items-center gap-4 text-[12px] text-[var(--text-3)] sm:flex">
                            <span class="flex items-center gap-1.5"><span class="station is-done size-3 border-2 text-[var(--text-2)]"></span>{{ __('served') }}</span>
                            <span class="flex items-center gap-1.5"><span class="station size-3 border-2 text-[var(--text-2)]"></span>{{ __('upcoming') }}</span>
                        </span>
                    </div>

                    <div x-show="data.lines.length === 0" @if (count($snapshot['lines'])) x-cloak @endif>
                        <x-empty icon="route" :title="__('No courier is on a route yet')">{{ __('Assigned tasks appear here as lines, one per courier, with every stop on the way.') }}</x-empty>
                    </div>

                    <ul class="pb-3">
                        <template x-for="line in data.lines" :key="line.courier_id">
                            <li>
                                <div role="button" tabindex="0" @click="select(line.courier_id)" @keydown.enter="select(line.courier_id)"
                                     class="grid w-full cursor-pointer grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 px-5 py-3 transition-opacity hover:bg-[var(--surface-hover)] sm:grid-cols-[160px_minmax(0,1fr)_80px] lg:px-7"
                                     :class="selected !== null && selected !== line.courier_id && 'opacity-40'"
                                     :aria-pressed="selected === line.courier_id">
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium" x-text="line.name"></span>
                                        <span class="t-label text-[11px] text-[var(--text-3)]" x-text="line.served + ' / ' + (line.served + line.remaining)"></span>
                                    </span>
                                    <span class="relative order-last col-span-2 flex h-6 items-center sm:order-none sm:col-span-1" :style="`color:${line.ink}`">
                                        <span class="absolute inset-x-0 top-1/2 h-[6px] -translate-y-1/2 rounded-full" :style="`background:${line.ink}`"></span>
                                        <span class="relative flex w-full items-center justify-between">
                                            <template x-for="stop in line.stops" :key="stop.id">
                                                <a :href="stop.url" @click.stop class="station" :class="stop.done && 'is-done'"
                                                   :title="stop.code + ' · ' + stop.address + ' · ' + stop.minutes_label"
                                                   :aria-label="stop.code + ' · ' + stop.minutes_label"
                                                   :style="stop.urgency === 'late' && !stop.done ? 'color:#ff6b6b;box-shadow:0 0 0 3px #0b1230,0 0 0 5px #ff6b6b' : ''"></a>
                                            </template>
                                        </span>
                                    </span>
                                    <span class="text-right">
                                        <span x-show="line.late > 0" class="t-label text-[var(--danger-text)]" x-text="line.late + ' ' + @js(__('late'))"></span>
                                        <span x-show="line.late === 0" class="t-label text-[var(--text-3)]">{{ __('on time') }}</span>
                                    </span>
                                </div>
                            </li>
                        </template>
                    </ul>
                </section>
            </div>

            {{-- Needs attention --}}
            <aside class="flex min-h-0 flex-col border-t-[1.5px] border-[var(--hairline)] lg:max-h-[calc(100vh-4rem-74px)] lg:border-l-[1.5px] lg:border-t-0" aria-labelledby="attention-title">
                <div class="flex items-center gap-3 border-b border-[var(--hairline)] px-5 py-4">
                    <x-icon name="triangle-alert" class="text-ink-amber" />
                    <h2 id="attention-title" class="t-label text-[var(--text)]">{{ __('Needs attention') }}</h2>
                    <span class="ml-auto t-label text-[var(--text-3)]" x-text="data.attention.length"></span>
                </div>
                <div x-show="data.attention.length === 0" @if (count($snapshot['attention'])) x-cloak @endif>
                    <x-empty icon="circle-check-big" :title="__('All clear')">{{ __('Nothing is late, unassigned or missing an address right now.') }}</x-empty>
                </div>
                <ul class="flex-1 overflow-y-auto">
                    <template x-for="item in data.attention" :key="item.id">
                        <li class="border-b border-[var(--hairline)]">
                            <a :href="item.url" class="grid grid-cols-[64px_minmax(0,1fr)] gap-4 px-5 py-3.5 hover:bg-[var(--surface-hover)]">
                                <span class="flex flex-col items-start">
                                    <span class="t-display text-[24px]"
                                          :class="{ 'text-[var(--danger-text)]': item.urgency === 'late', 'text-ink-amber': item.urgency === 'risk' }"
                                          x-text="item.minutes_label"></span>
                                    <span class="t-label text-[10px] text-[var(--text-3)]">{{ __('min') }}</span>
                                </span>
                                <span class="min-w-0">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="t-code" x-text="item.code"></span>
                                        <span class="tag h-5 text-[11px]" :class="item.reason === 'late' && 'border-ink-scarlet/70 text-[var(--danger-text)]'" x-text="item.reason_label"></span>
                                    </span>
                                    <span class="mt-0.5 block truncate text-[13px] text-[var(--text-2)]" x-text="[item.address, item.recipient].filter(Boolean).join(' · ')"></span>
                                    <span class="status mt-1 text-[12px] text-[var(--text-3)]" :data-form="item.status_form" :style="`--status-ink:${item.status_ink}`"
                                          x-text="item.courier ? item.status + ' · ' + item.courier : item.status"></span>
                                </span>
                            </a>
                        </li>
                    </template>
                </ul>
            </aside>
        </div>
    </div>
</x-layouts::app>
