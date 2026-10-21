@php
    use App\Support\Ui;
    $ink = $plan->courier ? Ui::courierInk($plan->courier) : '#8e99b8';
    $editable = $plan->status_slug === \App\Models\TaskPlan::PLANNED;
    $map = [
        'ink' => $ink,
        'geometry' => $plan->geometry ?? [],
        'hub' => $plan->hub ? ['lat' => $plan->hub->lat, 'lng' => $plan->hub->lng, 'label' => $plan->hub->name] : null,
        'stops' => $plan->tasks->filter(fn ($t) => $t->targetPoint())->map(fn ($t) => [
            'lat' => $t->targetPoint()->lat, 'lng' => $t->targetPoint()->lng,
            'label' => $t->route_order.' · '.$t->tracking_code, 'done' => $t->status_category_slug->isFinal(),
        ])->values(),
    ];
@endphp
<x-layouts::app :title="__('planning.route_of', ['name' => $plan->courier?->name ?? '—'])">
    <x-slot:actions>
        <a href="{{ route('plans.index', ['date' => $plan->starts_at?->setTimezone($currentCompany->timezone)->format('Y-m-d')]) }}" class="btn"><x-icon name="arrow-left" :size="16" />{{ __('planning.title') }}</a>
    </x-slot:actions>

    <div class="mb-6 flex flex-wrap items-end gap-x-10 gap-y-4 border-b-[1.5px] border-[var(--hairline)] pb-6">
        <div>
            <p class="t-label mb-2">{{ __('Window') }}</p>
            <p class="t-value">{{ Ui::localTime($plan->starts_at, 'd M H:i') }} – {{ Ui::localTime($plan->ends_at) }}</p>
        </div>
        <div>
            <p class="t-label mb-2">{{ __('planning.distance') }}</p>
            <p class="t-value">{{ number_format($plan->distance / 1000, 1) }} km</p>
        </div>
        <div>
            <p class="t-label mb-2">{{ __('planning.driving') }}</p>
            <p class="t-value">{{ (int) round($plan->duration / 60) }} {{ __('min') }}</p>
        </div>
        <div>
            <p class="t-label mb-2">{{ __('Status') }}</p>
            <p class="t-value">{{ __('planning.status.'.$plan->status_slug) }}</p>
        </div>
        <p class="ml-auto text-[13px] text-[var(--text-3)]">
            {{ __('planning.solver.'.($plan->solver ?? 'none')) }} · {{ __('planning.router.'.($plan->router ?? 'haversine')) }}
        </p>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_480px]">
        <section class="plate overflow-hidden">
            <div x-data="planMap(@js($map))" class="h-[420px] xl:h-[calc(100vh-15rem)]"><div x-ref="map" class="h-full" role="application" aria-label="{{ __('planning.route_of', ['name' => $plan->courier?->name ?? '—']) }}"></div></div>
        </section>

        <section class="plate self-start" aria-labelledby="stops-title">
            <div class="plate-head">
                <h2 id="stops-title" class="t-label text-[var(--text)]">{{ trans_choice('planning.stops', $plan->tasks->count(), ['count' => $plan->tasks->count()]) }}</h2>
                @if ($plan->router === 'haversine')
                    <span class="ml-auto text-[12px] text-[var(--risk-text)]">{{ __('planning.estimate_note') }}</span>
                @endif
            </div>
            <ol class="relative">
                @foreach ($plan->tasks as $stop)
                    @php $late = $stop->eta_at && $stop->ends_at && $stop->eta_at->gt($stop->ends_at) && ! $stop->status_category_slug->isFinal(); @endphp
                    <li class="grid grid-cols-[36px_minmax(0,1fr)_auto] gap-x-3 border-b border-[var(--hairline)] px-5 py-3">
                        <span class="flex flex-col items-center pt-1" style="color: {{ $ink }}">
                            <span @class(['station', 'is-done' => $stop->status_category_slug->isFinal()])></span>
                            <span class="t-label mt-1 text-[11px] text-[var(--text-3)]">{{ $stop->route_order }}</span>
                        </span>
                        <a href="{{ route('tasks.show', $stop) }}" class="min-w-0 hover:underline">
                            <span class="t-code block text-[13px]">{{ $stop->tracking_code }}</span>
                            <span class="block truncate text-[13px] text-[var(--text-2)]">{{ $stop->destination_address }}</span>
                            <x-status :category="$stop->status_category_slug" :label="$stop->status->label()" class="mt-1 text-[12px]" />
                        </a>
                        <span class="flex flex-col items-end gap-1 text-right">
                            <span @class(['t-value', 'text-[var(--danger-text)]' => $late])>{{ Ui::localTime($stop->eta_at) }}</span>
                            <span class="text-[12px] text-[var(--text-3)]">{{ __('planning.window') }} {{ Ui::localTime($stop->starts_at) }}–{{ Ui::localTime($stop->ends_at) }}</span>
                            @if ($late)<span class="t-label text-[10px] text-[var(--danger-text)]">{{ __('planning.late_eta') }}</span>@endif
                            @if ($editable)
                                <span class="mt-1 flex gap-1">
                                    @foreach (['up' => ['chevron-up', ! $loop->first], 'down' => ['chevron-down', ! $loop->last]] as $direction => [$icon, $enabled])
                                        <form method="POST" action="{{ route('plans.move', $plan) }}">@csrf
                                            <input type="hidden" name="task_id" value="{{ $stop->id }}">
                                            <input type="hidden" name="direction" value="{{ $direction }}">
                                            <button class="btn btn-sm btn-ghost size-8 p-0" @disabled(! $enabled) aria-label="{{ __('planning.move_'.$direction) }} · {{ $stop->tracking_code }}"><x-icon :name="$icon" :size="16" /></button>
                                        </form>
                                    @endforeach
                                </span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>
</x-layouts::app>
