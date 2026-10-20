@php
    use App\Support\Ui;
    $category = $task->status_category_slug;
    $ink = Ui::statusStyle($category)['ink'];
    $allowed = collect($category->allowedNext())->map->value->push($category->value)->unique();
    $nextStatuses = $statuses->filter(fn ($s) => $allowed->contains($s->status_category_slug->value) && $s->id !== $task->status_id)
        ->groupBy(fn ($s) => $s->status_category_slug->label());
    $points = collect([
        $task->originPoint() ? ['lat' => $task->origin_lat, 'lng' => $task->origin_lng, 'label' => $task->origin_name ?? __('Origin'), 'hub' => (bool) $task->origin_hub_id] : null,
        $task->destinationPoint() ? ['lat' => $task->destination_lat, 'lng' => $task->destination_lng, 'label' => $task->destination_name ?? __('Destination'), 'hub' => (bool) $task->destination_hub_id] : null,
    ])->filter()->values();
    $requirements = collect([
        $task->requires_delivery_code ? __('SMS code').($task->requires_delivery_code_mandatory ? ' · '.__('mandatory') : '') : null,
        $task->requires_pod_photo ? __('Proof photo').($task->requires_pod_photo_mandatory ? ' · '.__('mandatory') : '') : null,
        $task->required_dont_ring_the_bell ? __("Don't ring the bell") : null,
        $task->requires_contactless ? __('Contactless') : null,
    ])->filter();
@endphp
<x-layouts::app :title="$task->tracking_code">
    <x-slot:actions>
        <a href="{{ route('tracking.show', $task->tracking_code) }}" target="_blank" rel="noopener" class="btn hidden sm:inline-flex"><x-icon name="external-link" :size="16" />{{ __('Tracking page') }}</a>
        @unless ($task->isFinal())
            <a href="{{ route('tasks.edit', $task) }}" class="btn"><x-icon name="pencil" :size="16" />{{ __('Edit') }}</a>
        @endunless
    </x-slot:actions>

    {{-- Headline: identity, state, time left --}}
    <div class="mb-6 flex flex-wrap items-end gap-x-10 gap-y-4 border-b-[1.5px] border-[var(--hairline)] pb-6">
        <div>
            <p class="t-display tracking-[0.04em]">{{ $task->tracking_code }}</p>
            <p class="mt-2 text-[var(--text-2)]">
                {{ $task->type_slug->label() }} · {{ $task->hub->name }} ·
                @if ($task->integration_id)<span>#{{ $task->integration_id }} · </span>@endif
                {{ __('Created :time via :channel', ['time' => Ui::localTime($task->created_at, 'd M H:i'), 'channel' => __('channels.'.$task->channel->value)]) }}
                @if ($task->owner) · {{ $task->owner->name }} @endif
            </p>
        </div>
        <div>
            <p class="t-label mb-2">{{ __('Status') }}</p>
            <x-status :category="$category" :label="$task->status->label()" class="t-value" />
        </div>
        <div>
            <p class="t-label mb-2">{{ $task->isFinal() ? __('Finished') : __('Minutes left') }}</p>
            <x-minutes :ends-at="$task->isFinal() ? $task->ended_at : $task->ends_at" :final="$task->isFinal()" class="t-display" />
        </div>
        <div>
            <p class="t-label mb-2">{{ __('Window') }}</p>
            <p class="t-value">{{ Ui::localTime($task->starts_at, 'd M H:i') }} – {{ Ui::localTime($task->ends_at) }}</p>
        </div>
        @if ($task->is_locked)
            <span class="tag ml-auto h-7 gap-1.5 border-ink-amber/70 text-ink-amber"><x-icon name="lock" :size="14" />{{ __('Locked for the courier') }}</span>
        @endif
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="flex flex-col gap-6">
            <div class="grid gap-6 lg:grid-cols-2">
                <section class="plate overflow-hidden">
                    <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Route') }}</h2></div>
                    @if ($points->isNotEmpty())
                        <div x-data="routePreview(@js($points), @js($task->courier ? Ui::courierInk($task->courier) : '#f4f2ec'))" class="h-[280px]"><div x-ref="map" class="h-full"></div></div>
                    @else
                        <x-empty icon="map" :title="__('Not on the map yet')">{{ __('The address could not be found. Add coordinates to plan the route.') }}</x-empty>
                    @endif
                </section>

                <section class="plate">
                    <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Recipient') }}</h2></div>
                    <dl class="grid gap-4 p-5">
                        <div><dt class="t-label">{{ __('Name') }}</dt><dd class="t-value mt-1">{{ $task->destination_name ?? '—' }}</dd></div>
                        <div><dt class="t-label">{{ __('Phone') }}</dt>
                            <dd class="mt-1">@if ($task->destination_phone)<a href="tel:{{ $task->destination_phone }}" class="inline-flex items-center gap-2 hover:underline"><x-icon name="phone" :size="15" />{{ $task->destination_phone }}</a>@else — @endif</dd></div>
                        <div><dt class="t-label">{{ __('Address') }}</dt>
                            <dd class="mt-1">{{ $task->destination_address }}
                                @if ($task->destination_address_building || $task->destination_address_floor || $task->destination_address_apartment)
                                    <span class="block text-[var(--text-2)]">{{ collect([$task->destination_address_building ? __('bldg').' '.$task->destination_address_building : null, $task->destination_address_floor ? __('floor').' '.$task->destination_address_floor : null, $task->destination_address_apartment ? __('apt').' '.$task->destination_address_apartment : null])->filter()->implode(', ') }}</span>
                                @endif
                                @if ($task->destination_address_description)<span class="block text-[var(--text-2)]">{{ $task->destination_address_description }}</span>@endif
                                @unless ($task->is_destination_resolved)<span class="mt-1 flex items-center gap-1.5 text-ink-amber"><x-icon name="circle-alert" :size="14" />{{ __('Not found on the map') }}</span>@endunless
                            </dd></div>
                    </dl>
                </section>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="plate">
                    <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Details') }}</h2></div>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-4 p-5">
                        <div><dt class="t-label">{{ __('Origin') }}</dt><dd class="mt-1">{{ $task->origin_name ?? $task->origin_address ?? '—' }}</dd></div>
                        <div><dt class="t-label">{{ __('Team') }}</dt><dd class="mt-1">{{ $task->team?->name ?? '—' }}</dd></div>
                        <div><dt class="t-label">{{ __('Quantity') }}</dt><dd class="mt-1">{{ $task->quantity }}</dd></div>
                        <div><dt class="t-label">{{ __('Payment') }}</dt><dd class="mt-1">{{ $task->payment_type ? __('payment.'.$task->payment_type) : '—' }}@if ($task->amount) · {{ number_format((float) $task->amount, 2) }} {{ $task->currency }}@endif</dd></div>
                        <div class="col-span-2"><dt class="t-label">{{ __('Requirements') }}</dt>
                            <dd class="mt-2 flex flex-wrap gap-2">@forelse ($requirements as $r)<span class="tag">{{ $r }}</span>@empty<span class="text-[var(--text-3)]">{{ __('None') }}</span>@endforelse</dd></div>
                        @if ($task->requires_delivery_code && $task->delivery_code)
                            <div class="col-span-2"><dt class="t-label">{{ __('Delivery code') }}</dt><dd class="t-display mt-1 tracking-[0.3em]">{{ $task->delivery_code }}</dd></div>
                        @endif
                        @if ($task->tags)
                            <div class="col-span-2"><dt class="t-label">{{ __('Tags') }}</dt><dd class="mt-2 flex flex-wrap gap-2">@foreach ($task->tags as $tag)<span class="tag">{{ $tag }}</span>@endforeach</dd></div>
                        @endif
                        @if ($task->notes)
                            <div class="col-span-2"><dt class="t-label">{{ __('For the team') }}</dt><dd class="mt-1 whitespace-pre-line text-[var(--text-2)]">{{ $task->notes }}</dd></div>
                        @endif
                        @if ($task->recipient_notes)
                            <div class="col-span-2"><dt class="t-label">{{ __('From the recipient') }}</dt><dd class="mt-1 whitespace-pre-line text-[var(--text-2)]">{{ $task->recipient_notes }}</dd></div>
                        @endif
                    </dl>
                </section>

                <section class="plate">
                    <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Items') }}</h2><span class="ml-auto t-label text-[var(--text-3)]">{{ $task->items->count() }}</span></div>
                    @if ($task->items->isEmpty())
                        <p class="p-5 text-[var(--text-3)]">{{ __('No items listed.') }}</p>
                    @else
                        <table class="tbl">
                            <thead><tr><th>SKU</th><th>{{ __('Name') }}</th><th class="text-right">{{ __('Qty') }}</th><th class="text-right">{{ __('Amount') }}</th></tr></thead>
                            <tbody>
                            @foreach ($task->items as $item)
                                <tr><td class="t-code text-[13px]">{{ $item->integration_id }}</td><td>{{ $item->name }}</td>
                                    <td class="text-right">{{ $item->quantity }}</td><td class="text-right">{{ $item->amount !== null ? number_format((float) $item->amount, 2) : '—' }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>
            </div>

            {{-- Journey: the status history as a vertical line with stations. --}}
            <section class="plate">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Journey') }}</h2></div>
                <ol class="relative px-5 py-4">
                    @foreach ($task->statusLogs as $log)
                        @php $style = Ui::statusStyle($log->status_category_slug); @endphp
                        <li class="relative grid grid-cols-[28px_minmax(0,1fr)_auto] gap-4 pb-5 last:pb-0">
                            @unless ($loop->last)
                                <span class="absolute left-[11px] top-5 bottom-0 w-[6px] rounded-full" style="background: {{ $style['ink'] }}; opacity: .55"></span>
                            @endunless
                            <span @class(['station relative mt-0.5 size-6 border-[5px]', 'is-done' => ! $loop->last]) style="color: {{ $style['ink'] }}"></span>
                            <span class="min-w-0">
                                <span class="block font-medium">{{ $log->status->label() }}</span>
                                <span class="block text-[13px] text-[var(--text-2)]">
                                    {{ $log->user?->name ?? __('roles.'.($log->role_slug ?? 'system')) }} · {{ __('channels.'.$log->channel->value) }}
                                </span>
                                @if ($log->notes)<span class="mt-1 block text-[13px] text-[var(--text-2)]">“{{ $log->notes }}”</span>@endif
                            </span>
                            <span class="text-right text-[13px] text-[var(--text-2)]">{{ Ui::localTime($log->created_at, 'd M H:i') }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        <aside class="flex flex-col gap-6 xl:sticky xl:top-24 xl:self-start">
            <section class="plate">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Courier') }}</h2></div>
                <div class="p-5">
                    @if ($task->courier)
                        <div class="mb-4 flex items-center gap-3">
                            <span class="grid size-10 place-items-center rounded-lg font-display font-semibold text-white" style="background: {{ Ui::courierInk($task->courier) }}">{{ mb_substr($task->courier->name, 0, 1) }}</span>
                            <span class="min-w-0">
                                <span class="block font-medium">{{ $task->courier->name }}</span>
                                <a href="tel:{{ $task->courier->phone }}" class="text-[13px] text-[var(--text-2)] hover:underline">{{ $task->courier->phone }}</a>
                            </span>
                        </div>
                    @endif
                    @if ($task->isFinal())
                        <p class="text-[var(--text-3)]">{{ __('The task is finished; the courier can no longer change.') }}</p>
                    @else
                        <form method="POST" action="{{ route('tasks.assign', $task) }}" class="flex flex-col gap-3">
                            @csrf
                            <label for="courier_id" class="t-label">{{ $task->courier ? __('Reassign to') : __('Assign to') }}</label>
                            <select name="courier_id" id="courier_id" class="input">
                                <option value="">{{ __('Nobody (unassign)') }}</option>
                                @foreach ($couriers as $courier)
                                    <option value="{{ $courier->id }}" @selected($courier->id === $task->courier_id)>
                                        {{ $courier->name }}{{ $courier->courierLocation?->is_online ? ' · '.__('online') : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <label class="flex items-center gap-2 text-[13px] text-[var(--text-2)]">
                                <input type="checkbox" name="ignore_capacity" value="1" class="check"> {{ __('Allow over capacity') }}
                            </label>
                            <button class="btn btn-primary">{{ __('Save assignment') }}</button>
                        </form>
                    @endif
                </div>
            </section>

            @unless ($task->isFinal())
                <section class="plate">
                    <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Change status') }}</h2></div>
                    <form method="POST" action="{{ route('tasks.status', $task) }}" class="flex flex-col gap-3 p-5">
                        @csrf
                        <select name="status_id" class="input" required aria-label="{{ __('New status') }}">
                            <option value="">{{ __('Choose a status') }}</option>
                            @foreach ($nextStatuses as $group => $items)
                                <optgroup label="{{ $group }}">
                                    @foreach ($items as $status)<option value="{{ $status->id }}">{{ $status->label() }}</option>@endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <input name="notes" class="input" maxlength="255" placeholder="{{ __('Note (optional)') }}" aria-label="{{ __('Note') }}">
                        @error('status_id')<p class="field-error">{{ $message }}</p>@enderror
                        <button class="btn">{{ __('Change status') }}</button>
                    </form>
                </section>

                <form method="POST" action="{{ route('tasks.lock', $task) }}">
                    @csrf
                    <button class="btn w-full">
                        <x-icon :name="$task->is_locked ? 'lock-open' : 'lock'" :size="16" />
                        {{ $task->is_locked ? __('Unlock for the courier') : __('Lock for the courier') }}
                    </button>
                </form>
            @endunless
        </aside>
    </div>
</x-layouts::app>
