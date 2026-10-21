@php
    use App\Support\Ui;
    $hubNames = $hubs->pluck('name', 'id');
    $estimated = $plans->contains(fn ($p) => $p->router === 'haversine');
@endphp
<x-layouts::app :title="__('planning.title')">
    <x-slot:actions>
        <form method="GET" class="flex items-center gap-2">
            <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" class="input h-[38px] w-[160px]" onchange="this.form.submit()" aria-label="{{ __('Date') }}">
            <select name="hub" class="input hidden h-[38px] w-[180px] sm:block" onchange="this.form.submit()" aria-label="{{ __('Hub') }}">
                <option value="">{{ __('planning.all_hubs') }}</option>
                @foreach ($hubs as $hub)
                    <option value="{{ $hub->id }}" @selected($hubId === $hub->id)>{{ $hub->name }}</option>
                @endforeach
            </select>
        </form>
    </x-slot:actions>

    <div class="flex flex-col gap-6">
        {{-- Slots waiting to be planned --}}
        <section class="plate" aria-labelledby="slots-title">
            <div class="plate-head"><h2 id="slots-title" class="t-label text-[var(--text)]">{{ __('planning.open_slots') }}</h2></div>

            @if ($slots->isEmpty())
                <x-empty icon="calendar-clock" :title="__('planning.no_open_slots')">{{ __('planning.no_open_slots_body') }}</x-empty>
            @else
                <ul class="divide-y divide-[var(--hairline)]">
                    @foreach ($slots as $slot)
                        @php $formId = 'slot-'.$slot->hub_id.'-'.$slot->slot; @endphp
                        <li x-data="{ open: false }">
                            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 px-5 py-4">
                                <span class="t-value min-w-[150px]">{{ Ui::localTime($slot->starts_at) }}–{{ Ui::localTime($slot->ends_at) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium">{{ $hubNames[$slot->hub_id] ?? '—' }}</span>
                                    <span class="text-[13px] text-[var(--text-2)]">{{ Ui::localTime($slot->starts_at, 'd M') }} · <span class="t-code">{{ $slot->slot }}</span></span>
                                </span>
                                <span class="t-label text-[var(--text-2)]">{{ trans_choice('planning.tasks_count', $slot->tasks_count, ['count' => $slot->tasks_count]) }}</span>
                                <button type="button" class="btn btn-primary btn-sm" @click="open = !open" :aria-expanded="open" aria-controls="{{ $formId }}">
                                    <x-icon name="route" :size="14" />{{ __('planning.close') }}
                                </button>
                            </div>
                            <form id="{{ $formId }}" method="POST" action="{{ route('plans.close') }}" x-show="open" x-cloak
                                  class="grid gap-4 border-t border-[var(--hairline)] bg-night-950/40 px-5 py-4 md:grid-cols-[220px_220px_minmax(0,1fr)_auto] md:items-end">
                                @csrf
                                <input type="hidden" name="slot" value="{{ $slot->slot }}">
                                <input type="hidden" name="hub_id" value="{{ $slot->hub_id }}">
                                <x-field :label="__('planning.shift_filter')" name="shift" :id="$formId.'-shift'">
                                    <select name="shift" id="{{ $formId }}-shift" class="input">
                                        <option value="working">{{ __('planning.shift_working') }}</option>
                                        <option value="all">{{ __('planning.shift_all') }}</option>
                                    </select>
                                </x-field>
                                <x-field :label="__('Team')" name="team_id" :id="$formId.'-team'">
                                    <select name="team_id" id="{{ $formId }}-team" class="input">
                                        <option value="">{{ __('All teams') }}</option>
                                        @foreach ($teams->where('hub_id', $slot->hub_id) as $team)
                                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                                        @endforeach
                                    </select>
                                </x-field>
                                <fieldset class="flex flex-col gap-2 text-[14px]">
                                    <legend class="sr-only">{{ __('Options') }}</legend>
                                    @foreach (['tour' => true, 'dispatch_early' => false, 'from_hub' => true] as $option => $default)
                                        <label class="flex items-start gap-2">
                                            <input type="hidden" name="{{ $option }}" value="0">
                                            <input type="checkbox" name="{{ $option }}" value="1" class="mt-1 size-4 accent-[var(--action)]" @checked($default)>
                                            <span class="text-[var(--text-2)]">{{ __('planning.'.$option) }}</span>
                                        </label>
                                    @endforeach
                                </fieldset>
                                <button class="btn btn-primary">{{ __('planning.close') }}</button>
                                <p class="text-[13px] text-[var(--text-3)] md:col-span-4">{{ __('planning.close_help') }}</p>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- The day's routes, drawn as lines --}}
        <section class="plate" aria-labelledby="plans-title">
            <div class="plate-head">
                <h2 id="plans-title" class="t-label text-[var(--text)]">{{ __('planning.plans_on', ['date' => $date->translatedFormat('j F')]) }}</h2>
                @if ($estimated)
                    <span class="ml-auto flex items-center gap-1.5 text-[12px] text-[var(--risk-text)]"><x-icon name="triangle-alert" :size="14" />{{ __('planning.estimate_note') }}</span>
                @endif
            </div>

            @if ($plans->isEmpty())
                <x-empty icon="route" :title="__('planning.no_plans')">{{ __('planning.no_plans_body') }}</x-empty>
            @else
                <ul class="divide-y divide-[var(--hairline)]">
                    @foreach ($plans as $plan)
                        @php
                            $ink = $plan->courier ? Ui::courierInk($plan->courier) : '#8e99b8';
                            $late = $plan->tasks->filter(fn ($t) => $t->eta_at && $t->ends_at && $t->eta_at->gt($t->ends_at) && ! $t->status_category_slug->isFinal())->count();
                        @endphp
                        <li>
                            <a href="{{ route('plans.show', $plan) }}" class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-5 gap-y-2 px-5 py-4 hover:bg-[var(--surface-hover)] lg:grid-cols-[200px_minmax(0,1fr)_120px_150px]">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{ $plan->courier?->name ?? '—' }}</span>
                                    <span class="text-[13px] text-[var(--text-2)]">{{ Ui::localTime($plan->starts_at) }}–{{ Ui::localTime($plan->ends_at) }} · {{ $plan->hub?->name }}</span>
                                </span>
                                <span class="relative order-last col-span-2 flex h-6 items-center lg:order-none lg:col-span-1" style="color: {{ $ink }}">
                                    <span class="absolute inset-x-0 top-1/2 h-[6px] -translate-y-1/2 rounded-full" style="background: {{ $ink }}"></span>
                                    <span class="relative flex w-full items-center justify-between">
                                        <span class="interchange size-4" aria-hidden="true"></span>
                                        @foreach ($plan->tasks as $stop)
                                            <span @class(['station', 'is-done' => $stop->status_category_slug->isFinal()])
                                                  title="{{ $stop->route_order }} · {{ $stop->tracking_code }} · {{ Ui::localTime($stop->eta_at) }}"
                                                  @if ($stop->eta_at && $stop->ends_at && $stop->eta_at->gt($stop->ends_at)) style="box-shadow:0 0 0 3px #0b1230,0 0 0 5px #ff6b6b" @endif></span>
                                        @endforeach
                                    </span>
                                </span>
                                <span class="text-[13px] text-[var(--text-2)]">
                                    {{ trans_choice('planning.stops', $plan->quantity, ['count' => $plan->quantity]) }}<br>
                                    {{ number_format($plan->distance / 1000, 1) }} km · {{ (int) round($plan->duration / 60) }} {{ __('min') }}
                                </span>
                                <span class="text-right">
                                    <span class="status block text-[13px]" data-form="{{ $plan->status_slug === 'completed' ? 'solid' : ($plan->status_slug === 'in_progress' ? 'solid' : 'dashed') }}" style="--status-ink: #c3c9da">{{ __('planning.status.'.$plan->status_slug) }}</span>
                                    @if ($late)
                                        <span class="t-label text-[11px] text-[var(--danger-text)]">{{ $late }} {{ __('late') }}</span>
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts::app>
