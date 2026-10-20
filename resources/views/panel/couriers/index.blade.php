<x-layouts::app :title="__('Couriers')">
    <x-slot:actions>
        <a href="{{ route('couriers.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" />{{ __('New courier') }}</a>
    </x-slot:actions>

    <form method="GET" class="mb-5 flex flex-wrap gap-3">
        <div class="relative min-w-[240px] flex-1 sm:max-w-[360px]">
            <x-icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-3)]" />
            <input name="q" value="{{ request('q') }}" class="input pl-9" placeholder="{{ __('Name or phone') }}" aria-label="{{ __('Search couriers') }}">
        </div>
        <select name="state" class="input w-[180px]" onchange="this.form.submit()" aria-label="{{ __('State') }}">
            <option value="">{{ __('Everyone') }}</option>
            <option value="working" @selected(request('state') === 'working')>{{ __('On shift') }}</option>
            <option value="online" @selected(request('state') === 'online')>{{ __('Online') }}</option>
        </select>
        <button class="btn"><x-icon name="filter" :size="16" />{{ __('Filter') }}</button>
    </form>

    <div class="plate overflow-hidden">
        @if ($couriers->isEmpty())
            <x-empty icon="truck" :title="request()->hasAny(['q', 'state']) ? __('No courier matches') : __('No couriers yet')" :action="request()->hasAny(['q', 'state']) ? null : route('couriers.create')" :action-label="__('New courier')">
                {{ __('Add couriers by phone number. They sign in to the courier app with a code sent by SMS.') }}
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="tbl min-w-[880px]">
                    <thead><tr><th>{{ __('Courier') }}</th><th>{{ __('State') }}</th><th>{{ __('Vehicle') }}</th><th>{{ __('Teams') }}</th><th class="text-right">{{ __('Active tasks') }}</th><th class="text-right">{{ __('Rating') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($couriers as $courier)
                        @php $loc = $courier->courierLocation; $detail = $courier->courierDetail; @endphp
                        <tr>
                            <td><span class="flex items-center gap-3">
                                <span class="grid size-9 place-items-center rounded-lg font-display font-semibold text-white" style="background: {{ \App\Support\Ui::courierInk($courier) }}">{{ mb_strtoupper(mb_substr($courier->name, 0, 1)) }}</span>
                                <span><span class="block font-medium">{{ $courier->name }}</span><span class="text-[13px] text-[var(--text-2)]">{{ $courier->phone }}</span></span></span></td>
                            <td>
                                @if ($loc?->is_working)
                                    <span class="status" data-form="solid" style="--status-ink:#22b14c">{{ $loc->on_duty ? __('On a route') : __('On shift') }}</span>
                                @else
                                    <span class="status text-[var(--text-3)]" data-form="dashed" style="--status-ink:#8e99b8">{{ __('Off shift') }}</span>
                                @endif
                                <span class="block text-[12px] text-[var(--text-3)]">{{ $loc?->last_seen_at ? __('seen :time', ['time' => $loc->last_seen_at->diffForHumans()]) : __('never signed in') }}</span>
                            </td>
                            <td class="text-[var(--text-2)]">{{ $detail?->vehicle_type_slug?->label() ?? '—' }}{{ $detail?->vehicle_license_plate ? ' · '.$detail->vehicle_license_plate : '' }}</td>
                            <td><span class="flex flex-wrap gap-1.5">@forelse ($courier->teams as $team)<span class="tag gap-1.5"><span class="size-2 rounded-full" style="background: {{ $team->color }}"></span>{{ $team->name }}</span>@empty<span class="text-[var(--text-3)]">—</span>@endforelse</span></td>
                            <td class="text-right">{{ $courier->active_tasks_count }}@if ($detail?->capacity)<span class="text-[var(--text-3)]"> / {{ $detail->capacity }}</span>@endif</td>
                            <td class="text-right">{{ $detail && $detail->scored_tasks_count ? number_format($detail->rating, 1) : '—' }}</td>
                            <td class="text-right"><a href="{{ route('couriers.edit', $courier) }}" class="btn btn-sm">{{ __('Edit') }}</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-[var(--hairline)] px-4 py-3">{{ $couriers->links() }}</div>
        @endif
    </div>
</x-layouts::app>
