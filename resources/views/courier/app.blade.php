@php
    $labels = [
        'next' => ['claim' => __('Take this order'), 'pickup' => __('Picked up'), 'depart' => __('On my way'), 'arrive' => __('I have arrived'), 'complete' => __('Hand over')],
        'shiftEndBlocked' => __('Finish or hand back your active tasks before ending the shift.'),
        'gpsDenied' => __('Location is off. Allow it so the dispatcher can see you.'),
        'queued' => __('Saved offline. It will be sent when the network is back.'),
    ];
@endphp
<x-layouts::courier :title="__('Route')">
    <div x-data="courierBoard(@js($initial), @js($labels))" x-cloak class="mx-auto flex min-h-screen max-w-[520px] flex-col">

        {{-- Shift bar --}}
        <header class="sticky top-0 z-20 flex items-center gap-3 border-b-[1.5px] border-[var(--outline)] bg-white/95 px-4 pb-3 pt-[max(.75rem,env(safe-area-inset-top))] backdrop-blur">
            <span class="size-[18px] shrink-0 rounded-full border-[4px]" :style="`border-color:${b.shift.active ? b.courier.ink : '#8e99b8'}`" aria-hidden="true"></span>
            <span class="t-label text-[var(--text)]" x-text="b.shift.active ? @js(__('On shift')) : @js(__('Off shift'))"></span>
            <span class="ml-auto text-[14px] text-[var(--text-2)]"
                  x-text="b.counts.delivered + '/' + (b.counts.delivered + b.counts.active) + (b.counts.pool ? ' · ' + @js(__('Pool')) + ' ' + b.counts.pool : '')"></span>
            <button type="button" class="-mr-2 grid size-11 place-items-center rounded-md" @click="menu = true" aria-label="{{ __('Menu') }}"><x-icon name="menu" :size="22" /></button>
        </header>

        <div class="flex flex-col gap-2 px-4 pt-3" aria-live="polite">
            <p x-show="queued > 0" class="flex items-center gap-2 rounded-md border-[1.5px] border-dashed border-ink-amber px-3 py-2 text-[14px]">
                <x-icon name="refresh-cw" :size="16" /><span x-text="@js(__('Waiting to send:')) + ' ' + queued"></span>
            </p>
            <p x-show="gpsDenied" class="flex gap-2 rounded-md border-[1.5px] border-ink-scarlet px-3 py-2 text-[14px]"><x-icon name="locate-fixed" :size="16" class="mt-0.5 shrink-0" /><span x-text="labels.gpsDenied"></span></p>
            <p x-show="error" class="flex gap-2 rounded-md border-[1.5px] border-ink-scarlet px-3 py-2 text-[14px]" role="alert"><x-icon name="circle-alert" :size="16" class="mt-0.5 shrink-0" /><span x-text="error"></span></p>
        </div>

        {{-- Off shift --}}
        <section x-show="!b.shift.active" class="flex flex-1 flex-col px-5 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-10">
            <h1 class="t-display uppercase" x-text="@js(__('Hello, :name', ['name' => '__NAME__'])).replace('__NAME__', b.courier.name.split(' ')[0])"></h1>
            <p class="mt-3 text-[16px] text-[var(--text-2)]" x-text="b.counts.active ? @js(__('Orders waiting for you:')) + ' ' + b.counts.active : @js(__('Start your shift to receive orders.'))"></p>
            <p class="mt-1 text-[16px] text-[var(--text-3)]" x-show="b.counts.delivered" x-text="@js(__('Delivered today:')) + ' ' + b.counts.delivered"></p>
            <button type="button" class="btn btn-primary mt-auto h-16 text-[16px]" :disabled="busy" @click="shift(true)">{{ __('Start shift') }}<x-icon name="arrow-right" :size="20" /></button>
            <p class="mt-3 text-center text-[13px] text-[var(--text-3)]">{{ __('Your location is shared with the dispatcher only while you are on shift and the app is open.') }}</p>
        </section>

        {{-- On shift with a next stop --}}
        <template x-if="b.shift.active && n">
            <div class="flex flex-1 flex-col pb-[calc(10.5rem+env(safe-area-inset-bottom))]">
                <section class="px-5 pt-4">
                    <p class="flex flex-wrap items-baseline gap-x-2">
                        <span class="t-hero" :class="{ 'text-[var(--danger-text)]': n.urgency === 'late', 'text-[var(--risk-text)]': n.urgency === 'risk' }" x-text="n.minutes_label"></span>
                        <span class="t-label text-[14px]" x-text="n.minutes_in_hours ? (n.urgency === 'late' ? @js(__('late')) : @js(__('left'))) : (n.urgency === 'late' ? @js(__('min late')) : @js(__('min left')))"></span>
                        {{-- A drop-off still at the pickup step goes to the hub first; say so where the time is. --}}
                        <span x-show="n.category === 'assigned' && n.type === 'drop_off'" class="tag h-7 border-[var(--text)] px-2 text-[13px] text-[var(--text)]">{{ __('Pick up first') }}</span>
                    </p>
                    <h1 class="t-display mt-4 uppercase leading-[1.05]" x-text="n.target.address"></h1>
                    <p class="mt-1 text-[16px] text-[var(--text-2)]" x-text="[n.target.building && @js(__('bldg')) + ' ' + n.target.building, n.target.floor && @js(__('floor')) + ' ' + n.target.floor, n.target.apartment && @js(__('apt')) + ' ' + n.target.apartment].filter(Boolean).join(' · ')"></p>
                    <p class="mt-2 text-[16px]"><span x-text="n.target.name"></span><span x-show="n.phone" class="text-[var(--text-2)] tabular-nums" x-text="' · ' + n.phone_display"></span></p>
                    <p class="t-code mt-1 text-[13px] text-[var(--text-3)]" x-text="n.code"></p>
                    <p x-show="n.target.description" class="mt-2 rounded-md bg-[var(--surface-hover)] p-3 text-[16px] text-[var(--text-2)]" x-text="n.target.description"></p>
                </section>

                <div class="mx-5 mt-4 h-[170px] overflow-hidden rounded-[var(--radius-plate)] border-[1.5px] border-[var(--outline)]" x-ref="map" aria-hidden="true"></div>

                <div class="mx-5 mt-3 flex flex-wrap gap-2">
                    <template x-if="n.cash"><span class="tag h-8 border-[var(--text)] px-3 text-[14px] font-semibold text-[var(--text)]" x-text="@js(__('Collect')) + ' ' + n.cash.toFixed(2) + ' ' + (n.currency || '')"></span></template>
                    <template x-if="n.requirements.code"><span class="tag h-8 px-3 text-[14px]" x-text="@js(__('SMS code')) + (n.requirements.code_mandatory ? ' · ' + @js(__('mandatory')) : '')"></span></template>
                    <template x-if="n.requirements.photo"><span class="tag h-8 px-3 text-[14px]" x-text="@js(__('Photo')) + (n.requirements.photo_mandatory ? ' · ' + @js(__('mandatory')) : '')"></span></template>
                    <template x-if="n.requirements.dont_ring"><span class="tag h-8 px-3 text-[14px]">{{ __("Don't ring the bell") }}</span></template>
                    <template x-if="n.requirements.contactless"><span class="tag h-8 px-3 text-[14px]">{{ __('Contactless') }}</span></template>
                    <a :href="n.url" class="tag h-8 px-3 text-[14px] text-[var(--text)] underline-offset-2">{{ __('Details') }}<x-icon name="chevron-right" :size="14" /></a>
                </div>

                <div class="mx-5 mt-3 grid grid-cols-2 gap-3">
                    <a :href="n.phone ? 'tel:' + n.phone : null" :aria-disabled="!n.phone" class="btn h-14 text-[14px]"><x-icon name="phone" :size="18" />{{ __('Call') }}</a>
                    <a :href="n.navigate?.google" :aria-disabled="!n.navigate" target="_blank" rel="noopener" class="btn h-14 text-[14px]"><x-icon name="navigation" :size="18" />{{ __('Navigate') }}</a>
                </div>
            </div>
        </template>

        {{-- On shift, nothing assigned --}}
        <section x-show="b.shift.active && !n" class="flex flex-1 flex-col px-5 pt-8 pb-[calc(6rem+env(safe-area-inset-bottom))]">
            <h1 class="t-display uppercase">{{ __('No orders right now') }}</h1>
            <p class="mt-2 text-[16px] text-[var(--text-2)]" x-text="b.pool.length ? @js(__('Orders in the pool are open to you. Take one, or wait for the dispatcher.')) : @js(__('Stay nearby. New orders appear here by themselves.'))"></p>
            <ul class="mt-6 flex flex-col gap-3">
                <template x-for="p in b.pool" :key="p.id">
                    <li class="plate flex items-center gap-4 p-4">
                        <span class="t-value w-12" :class="{ 'text-[var(--danger-text)]': p.urgency === 'late', 'text-[var(--risk-text)]': p.urgency === 'risk' }" x-text="p.minutes_label"></span>
                        <span class="min-w-0 flex-1"><span class="block truncate font-medium" x-text="p.address"></span><span class="text-[14px] text-[var(--text-2)]" x-text="p.window"></span></span>
                        <button type="button" class="btn h-12" :disabled="busy" @click="claim(p)">{{ __('Take') }}</button>
                    </li>
                </template>
            </ul>
        </section>

        {{-- Primary action + the rest of the day --}}
        <div x-show="b.shift.active" class="fixed inset-x-0 bottom-0 z-30 border-t-[1.5px] border-[var(--outline)] bg-white pb-[max(.75rem,env(safe-area-inset-bottom))]">
            <div class="mx-auto max-w-[520px]">
                <div x-show="n && n.next" class="px-4 pt-3">
                    <button type="button" class="btn btn-primary h-16 w-full text-[16px]" :disabled="busy" @click="primary()">
                        <span x-text="n && labels.next[n.next]"></span><x-icon name="arrow-right" :size="20" />
                    </button>
                </div>
                <button type="button" class="flex w-full items-center gap-4 px-5 py-3" @click="drawer = true" :aria-expanded="drawer">
                    <span class="t-label text-[var(--text)]" x-text="@js(__('Then')) + ' ' + Math.max(0, b.active.length - 1)"></span>
                    <span class="relative flex h-4 flex-1 items-center" :style="`color:${b.courier.ink}`" aria-hidden="true">
                        <span class="absolute inset-x-0 h-[5px] rounded-full" :style="`background:${b.courier.ink}`"></span>
                        <span class="relative flex w-full justify-between">
                            <template x-for="s in b.active.slice(1, 9)" :key="s.id">
                                <span class="station size-3.5 border-[3px] bg-white" :style="s.urgency === 'late' ? 'box-shadow:0 0 0 2px #fff,0 0 0 4px #e21d2d' : ''"></span>
                            </template>
                        </span>
                    </span>
                    <x-icon name="chevron-right" :size="18" class="-rotate-90" />
                </button>
            </div>
        </div>

        {{-- Drawer: the day as a line --}}
        <div x-show="drawer" x-transition.opacity class="fixed inset-0 z-40 bg-night-950/45" @click="drawer = false"></div>
        <section x-show="drawer" x-transition.origin.bottom class="fixed inset-x-0 bottom-0 z-50 mx-auto flex max-h-[85vh] max-w-[520px] flex-col rounded-t-2xl bg-white pb-[max(1rem,env(safe-area-inset-bottom))]" role="dialog" aria-modal="true" aria-labelledby="drawer-title">
            <div class="flex items-center gap-1 border-b border-[var(--outline)] px-4 pt-4" role="tablist">
                <h2 id="drawer-title" class="sr-only">{{ __('My day') }}</h2>
                @foreach (['route' => __('Route'), 'pool' => __('Pool'), 'done' => __('Done')] as $key => $label)
                    <button type="button" role="tab" class="nav-link h-11" :aria-current="tab === '{{ $key }}' ? 'page' : null" :aria-selected="tab === '{{ $key }}'" @click="tab = '{{ $key }}'">
                        {{ $label }} <span class="text-[var(--text-3)]" x-text="{{ $key === 'route' ? 'b.active.length' : ($key === 'pool' ? 'b.pool.length' : 'b.done.length') }}"></span>
                    </button>
                @endforeach
                <button type="button" class="ml-auto grid size-11 place-items-center" @click="drawer = false" aria-label="{{ __('Close') }}"><x-icon name="x" :size="20" /></button>
            </div>

            <ol class="overflow-y-auto px-4 py-3">
                <template x-for="(s, i) in tab === 'route' ? b.active : (tab === 'pool' ? b.pool : b.done)" :key="s.id">
                    <li class="relative grid grid-cols-[28px_minmax(0,1fr)_auto] items-start gap-3 pb-4">
                        <span x-show="tab !== 'pool' && i < (tab === 'route' ? b.active : b.done).length - 1" class="absolute left-[11px] top-6 bottom-0 w-[5px] rounded-full" :style="`background:${b.courier.ink}`"></span>
                        <span class="station relative mt-1 size-6 border-[5px] bg-white" :class="tab === 'done' && 'is-done'" :style="`color:${tab === 'pool' ? '#8e99b8' : b.courier.ink};` + (s.urgency === 'late' && tab === 'route' ? 'box-shadow:0 0 0 2px #fff,0 0 0 4px #e21d2d' : '')"></span>
                        <a :href="tab === 'pool' ? null : s.url" class="min-w-0">
                            <span class="block truncate font-medium" x-text="s.address"></span>
                            <span class="block text-[14px] text-[var(--text-2)]" x-text="[s.name, s.status].filter(Boolean).join(' · ')"></span>
                        </a>
                        <span class="text-right">
                            <span class="t-value block" :class="{ 'text-[var(--danger-text)]': s.urgency === 'late' && tab !== 'done', 'text-[var(--risk-text)]': s.urgency === 'risk' && tab !== 'done' }" x-text="s.minutes_label"></span>
                            <button type="button" x-show="tab === 'pool'" class="btn btn-sm mt-1 h-10" :disabled="busy" @click="claim(s)">{{ __('Take') }}</button>
                        </span>
                    </li>
                </template>
            </ol>
            <p x-show="(tab === 'route' ? b.active : (tab === 'pool' ? b.pool : b.done)).length === 0" class="px-5 pb-6 text-[var(--text-3)]">{{ __('Nothing here yet.') }}</p>
        </section>

        {{-- Menu --}}
        <div x-show="menu" x-transition.opacity class="fixed inset-0 z-40 bg-night-950/45" @click="menu = false"></div>
        <section x-show="menu" x-transition.origin.bottom class="fixed inset-x-0 bottom-0 z-50 mx-auto max-w-[520px] rounded-t-2xl bg-white px-4 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-5" role="dialog" aria-modal="true" aria-label="{{ __('Menu') }}">
            <p class="t-title" x-text="b.courier.name"></p>
            <p class="text-[var(--text-2)]" x-text="b.company"></p>
            <p x-show="b.shift.active" class="mt-1 text-[14px] text-[var(--text-3)]" x-text="@js(__('On shift since')) + ' ' + b.shift.started_label"></p>
            <div class="mt-5 flex flex-col gap-2">
                <button type="button" x-show="b.shift.active" class="btn h-14" :disabled="busy" @click="shift(false)">{{ __('End shift') }}</button>
                <div class="flex gap-2">
                    @foreach (['az' => 'AZ', 'en' => 'EN', 'ru' => 'RU', 'tr' => 'TR'] as $code => $name)
                        <a href="{{ route('courier.home', ['lang' => $code]) }}" class="btn h-12 flex-1 {{ app()->getLocale() === $code ? 'border-[var(--text)]' : 'btn-ghost' }}">{{ $name }}</a>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('courier.logout') }}">
                    @csrf
                    <button class="btn btn-ghost h-12 w-full"><x-icon name="log-out" :size="18" />{{ __('Sign out') }}</button>
                </form>
            </div>
        </section>
    </div>
</x-layouts::courier>
