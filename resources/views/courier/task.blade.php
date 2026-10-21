@php
    $completeOptions = ['delivered' => __('Handed to the recipient'), 'left_at_door' => __('Left at the door'), 'delivered_to_other' => __('Handed to someone else')];
    $failOptions = ['recipient_not_available' => __('Recipient not available'), 'wrong_address' => __('Wrong address'), 'refused' => __('Recipient refused'), 'damaged' => __('Item damaged'), 'failed' => __('Other reason')];
    $nextLabels = ['claim' => __('Take this order'), 'pickup' => __('Picked up'), 'depart' => __('On my way'), 'arrive' => __('I have arrived'), 'complete' => __('Delivered')];
    $labels = [
        'completeOptions' => $completeOptions, 'failOptions' => $failOptions, 'nextLabels' => $nextLabels,
        'wrongCode' => __('Wrong code.'), 'scanned' => __('Scanned'), 'noMatch' => __('not in this order'),
        'cameraDenied' => __('The camera is not available. Type the code below.'),
    ];
@endphp
<x-layouts::courier :title="$initial['code']">
    <div x-data="courierTask(@js($initial), @js($labels))" x-cloak @keydown.escape.window="sheet === 'scan' ? closeScanner() : (sheet = null)"
         class="mx-auto flex min-h-screen max-w-[520px] flex-col pb-[calc(9rem+env(safe-area-inset-bottom))]">

        <header class="sticky top-0 z-20 flex items-center gap-3 border-b-[1.5px] border-[var(--outline)] bg-white/95 px-3 pb-3 pt-[max(.75rem,env(safe-area-inset-top))] backdrop-blur">
            <a href="{{ route('courier.home') }}" class="grid size-11 place-items-center rounded-md" aria-label="{{ __('Back') }}"><x-icon name="chevron-right" :size="22" class="rotate-180" /></a>
            <span class="min-w-0">
                <span class="t-code block truncate" x-text="t.code"></span>
                <span class="status text-[13px] text-[var(--text-2)]" :data-form="t.status_form" style="--status-ink:#45506e" x-text="t.status"></span>
            </span>
            <span x-show="queued > 0" class="tag ml-auto gap-1.5 border-ink-amber text-[var(--text)]"><x-icon name="refresh-cw" :size="13" /><span x-text="queued"></span></span>
        </header>

        <div x-show="error" x-transition class="mx-4 mt-4 flex gap-3 rounded-[var(--radius-plate)] border-[1.5px] border-ink-scarlet bg-white p-3" role="alert">
            <x-icon name="circle-alert" class="mt-0.5 shrink-0 text-[var(--danger-text)]" /><span x-text="error"></span>
        </div>
        <div x-show="t.locked" class="mx-4 mt-4 flex gap-3 rounded-[var(--radius-plate)] border-[1.5px] border-ink-amber p-3">
            <x-icon name="lock" class="mt-0.5 shrink-0" /><span>{{ __('The dispatcher locked this order. Call them before you continue.') }}</span>
        </div>

        {{-- Where and when --}}
        <section class="px-4 pt-5">
            <div class="flex items-baseline gap-3">
                <span class="t-display" :class="{ 'text-[var(--danger-text)]': t.urgency === 'late', 'text-[var(--risk-text)]': t.urgency === 'risk' }" x-text="t.minutes_label"></span>
                <span class="t-label" x-text="t.final ? @js(__('finished')) : (t.minutes_in_hours ? (t.urgency === 'late' ? @js(__('late')) : @js(__('left'))) : (t.urgency === 'late' ? @js(__('min late')) : @js(__('min left'))))"></span>
                <span class="ml-auto text-[var(--text-2)]" x-text="t.window"></span>
            </div>
            <h1 class="t-display mt-4 uppercase leading-[1.05]" x-text="t.target.address"></h1>
            <p class="mt-2 text-[16px]"><span x-text="t.target.name || '—'"></span><span x-show="t.phone" class="tabular-nums text-[var(--text-2)]" x-text="' · ' + t.phone_display"></span></p>
            <p class="text-[var(--text-2)]" x-text="[t.target.building && @js(__('bldg')) + ' ' + t.target.building, t.target.floor && @js(__('floor')) + ' ' + t.target.floor, t.target.apartment && @js(__('apt')) + ' ' + t.target.apartment].filter(Boolean).join(' · ')"></p>
            <p x-show="t.target.description" class="mt-2 rounded-md bg-[var(--surface-hover)] p-3 text-[var(--text-2)]" x-text="t.target.description"></p>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <a :href="t.phone ? 'tel:' + t.phone : null" :aria-disabled="!t.phone" class="btn h-12"><x-icon name="phone" :size="18" />{{ __('Call') }}</a>
                <a :href="t.navigate?.google" :aria-disabled="!t.navigate" target="_blank" rel="noopener" class="btn h-12"><x-icon name="navigation" :size="18" />{{ __('Navigate') }}</a>
            </div>
            <a x-show="t.navigate" :href="t.navigate?.waze" target="_blank" rel="noopener" class="mt-2 block py-2 text-center text-[14px] text-[var(--text-2)] underline">{{ __('Open in Waze') }}</a>
        </section>

        {{-- Requirements and money --}}
        <section class="mt-5 flex flex-wrap gap-2 px-4">
            <template x-if="t.cash"><span class="tag h-8 border-[var(--text)] px-3 text-[14px] font-semibold text-[var(--text)]" x-text="@js(__('Collect')) + ' ' + t.cash.toFixed(2) + ' ' + (t.currency || '')"></span></template>
            <template x-if="t.requirements.code"><span class="tag h-8 px-3 text-[14px]" x-text="@js(__('SMS code')) + (t.requirements.code_mandatory ? ' · ' + @js(__('mandatory')) : '')"></span></template>
            <template x-if="t.requirements.photo"><span class="tag h-8 px-3 text-[14px]" x-text="@js(__('Photo')) + (t.requirements.photo_mandatory ? ' · ' + @js(__('mandatory')) : '')"></span></template>
            <template x-if="t.requirements.dont_ring"><span class="tag h-8 px-3 text-[14px]">{{ __("Don't ring the bell") }}</span></template>
            <template x-if="t.requirements.contactless"><span class="tag h-8 px-3 text-[14px]">{{ __('Contactless') }}</span></template>
        </section>

        <section x-show="t.notes || t.recipient_notes" class="mx-4 mt-5 flex flex-col gap-2">
            <p x-show="t.recipient_notes"><span class="t-label block">{{ __('From the recipient') }}</span><span x-text="t.recipient_notes"></span></p>
            <p x-show="t.notes"><span class="t-label block">{{ __('From the dispatcher') }}</span><span x-text="t.notes"></span></p>
        </section>

        {{-- Items and scanning --}}
        <section x-show="t.items.length" class="mx-4 mt-6">
            <div class="mb-2 flex items-center">
                <h2 class="t-label text-[var(--text)]">{{ __('Items') }} <span class="text-[var(--text-3)]" x-text="t.items.filter(i => i.scanned).length + '/' + t.items.length"></span></h2>
                <button type="button" class="btn btn-sm ml-auto h-10" @click="openScanner()" :disabled="t.final"><x-icon name="scan-barcode" :size="16" />{{ __('Scan') }}</button>
            </div>
            <ul class="plate divide-y divide-[var(--hairline)]">
                <template x-for="item in t.items" :key="item.id">
                    <li class="flex items-center gap-3 px-4 py-3">
                        <span class="grid size-6 shrink-0 place-items-center rounded-full border-2" :class="item.scanned ? 'border-ink-green bg-ink-green text-white' : 'border-[var(--hairline-strong)]'">
                            <span x-show="item.scanned" class="contents"><x-icon name="check" :size="14" /></span>
                        </span>
                        <span class="min-w-0 flex-1"><span class="block truncate" x-text="item.name"></span><span class="t-code text-[12px] text-[var(--text-3)]" x-text="item.barcode || item.sku"></span></span>
                        <span class="text-[var(--text-2)]" x-text="'× ' + item.quantity"></span>
                    </li>
                </template>
            </ul>
        </section>

        {{-- Proof of delivery --}}
        <section x-show="['on_delivery'].includes(t.category) || t.pods.length || t.code_verified" class="mx-4 mt-6 flex flex-col gap-3">
            <h2 class="t-label text-[var(--text)]">{{ __('Proof of delivery') }}</h2>

            <div x-show="t.requirements.code && !t.final" class="plate p-4">
                <template x-if="t.code_verified">
                    <p class="flex items-center gap-2"><x-icon name="circle-check-big" class="text-ink-green" />{{ __('Code confirmed') }}</p>
                </template>
                <template x-if="!t.code_verified">
                    <form @submit.prevent="verifyCode()" class="flex flex-col gap-3">
                        <label class="t-label" for="dcode">{{ __('Code from the recipient\'s SMS') }}</label>
                        <div class="flex gap-2">
                            <input id="dcode" x-model="code" inputmode="numeric" autocomplete="off" maxlength="8" class="input h-12 flex-1 text-center font-display text-[20px] tracking-[0.3em]">
                            <button class="btn h-12" :disabled="busy || code.length < 4">{{ __('Check') }}</button>
                        </div>
                        <p class="text-[13px] text-[var(--text-3)]" x-text="@js(__('Tries left:')) + ' ' + t.code_attempts_left"></p>
                    </form>
                </template>
            </div>

            <div class="grid grid-cols-2 gap-3" x-show="!t.final">
                <label class="btn h-14 cursor-pointer" :class="t.has_photo && 'border-ink-green'">
                    <x-icon name="camera" :size="18" /><span x-text="t.has_photo ? @js(__('Photo added')) : @js(__('Take photo'))"></span>
                    <input type="file" accept="image/*" capture="environment" class="sr-only" @change="photo($event)">
                </label>
                <button type="button" class="btn h-14" @click="sheet = 'signature'; $nextTick(() => initSignature())"><x-icon name="pencil" :size="18" />{{ __('Signature') }}</button>
            </div>

            <div x-show="t.pods.length" class="flex flex-wrap gap-2">
                <template x-for="pod in t.pods" :key="pod.id">
                    <img :src="pod.url" alt="" class="h-20 w-24 rounded-md border border-[var(--outline)] object-cover" :class="pod.type === 'signature' && 'object-contain p-1'">
                </template>
            </div>
        </section>

        {{-- Sticky actions --}}
        <div x-show="!t.final" class="fixed inset-x-0 bottom-0 z-30 border-t-[1.5px] border-[var(--outline)] bg-white px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-3">
            <div class="mx-auto flex max-w-[520px] flex-col gap-2">
                <button type="button" x-show="t.next" class="btn btn-primary h-16 text-[16px]" :disabled="busy || (t.next === 'complete' && !t.can_complete)"
                        @click="t.next === 'complete' ? (sheet = 'complete') : primary()">
                    <span x-text="labels.nextLabels[t.next]"></span><x-icon name="arrow-right" :size="20" />
                </button>
                <p x-show="t.next === 'complete' && !t.can_complete" class="text-center text-[13px] text-[var(--text-2)]">
                    {{ __('Add the mandatory proof above to finish.') }}
                </p>
                <button type="button" x-show="t.can_fail" class="btn btn-ghost h-12" @click="sheet = 'fail'">{{ __('Could not deliver') }}</button>
            </div>
        </div>

        {{-- Bottom sheets --}}
        <div x-show="sheet" x-transition.opacity class="fixed inset-0 z-40 bg-night-950/50" @click="sheet = null"></div>

        <div x-show="sheet === 'complete'" x-transition.origin.bottom class="fixed inset-x-0 bottom-0 z-50 mx-auto max-w-[520px] rounded-t-2xl bg-white px-4 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-5" role="dialog" aria-modal="true" aria-labelledby="complete-title">
            <div class="mb-4 flex items-start gap-3">
                <h2 id="complete-title" class="t-title flex-1">{{ __('How was it handed over?') }}</h2>
                <button type="button" class="-mr-2 -mt-2 grid size-11 shrink-0 place-items-center" @click="sheet = null" aria-label="{{ __('Close') }}"><x-icon name="x" :size="20" /></button>
            </div>
            <div class="flex flex-col gap-2">
                <template x-for="(label, slug) in labels.completeOptions" :key="slug">
                    <label class="flex min-h-14 items-center gap-3 rounded-[var(--radius-plate)] border-[1.5px] px-4" :class="outcome === slug ? 'border-[var(--text)]' : 'border-[var(--outline)]'">
                        <input type="radio" name="outcome" :value="slug" x-model="outcome" class="size-5 accent-[#0b1230]"><span x-text="label"></span>
                    </label>
                </template>
                <input x-show="outcome === 'delivered_to_other'" x-model="receivedBy" class="input h-12" placeholder="{{ __('Who received it?') }}">
                <button type="button" class="btn btn-primary mt-2 h-14" :disabled="busy" @click="complete()">{{ __('Confirm delivery') }}</button>
            </div>
        </div>

        <div x-show="sheet === 'fail'" x-transition.origin.bottom class="fixed inset-x-0 bottom-0 z-50 mx-auto max-w-[520px] rounded-t-2xl bg-white px-4 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-5" role="dialog" aria-modal="true" aria-labelledby="fail-title">
            <div class="mb-4 flex items-start gap-3">
                <h2 id="fail-title" class="t-title flex-1">{{ __('Why could it not be delivered?') }}</h2>
                <button type="button" class="-mr-2 -mt-2 grid size-11 shrink-0 place-items-center" @click="sheet = null" aria-label="{{ __('Close') }}"><x-icon name="x" :size="20" /></button>
            </div>
            <div class="flex flex-col gap-2">
                <template x-for="(label, slug) in labels.failOptions" :key="slug">
                    <label class="flex min-h-12 items-center gap-3 rounded-[var(--radius-plate)] border-[1.5px] px-4" :class="reason === slug ? 'border-[var(--text)]' : 'border-[var(--outline)]'">
                        <input type="radio" name="reason" :value="slug" x-model="reason" class="size-5 accent-[#0b1230]"><span x-text="label"></span>
                    </label>
                </template>
                <label for="fail-notes" class="t-label mt-2">{{ __('Note for the dispatcher') }}</label>
                <textarea id="fail-notes" x-model="notes" class="input" maxlength="255"></textarea>
                <button type="button" class="btn btn-danger mt-2 h-14" :disabled="busy || !reason" @click="fail()">{{ __('Report the problem') }}</button>
            </div>
        </div>

        <div x-show="sheet === 'signature'" x-transition.origin.bottom class="fixed inset-x-0 bottom-0 z-50 mx-auto max-w-[520px] rounded-t-2xl bg-white px-4 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-5" role="dialog" aria-modal="true" aria-labelledby="sig-title">
            <div class="mb-3 flex items-start gap-3">
                <h2 id="sig-title" class="t-title flex-1">{{ __('Recipient signature') }}</h2>
                <button type="button" class="-mr-2 -mt-2 grid size-11 shrink-0 place-items-center" @click="sheet = null" aria-label="{{ __('Close') }}"><x-icon name="x" :size="20" /></button>
            </div>
            <canvas x-ref="sig" class="h-[220px] w-full touch-none rounded-[var(--radius-plate)] border-[1.5px] border-dashed border-[var(--hairline-strong)]"></canvas>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <button type="button" class="btn h-12" @click="pad?.clear()">{{ __('Clear') }}</button>
                <button type="button" class="btn btn-primary h-12" :disabled="busy" @click="saveSignature()">{{ __('Save') }}</button>
            </div>
        </div>

        <div x-show="sheet === 'scan'" class="fixed inset-0 z-50 flex flex-col bg-black" role="dialog" aria-modal="true" aria-label="{{ __('Scan') }}">
            <video x-ref="video" playsinline muted class="flex-1 object-cover"></video>
            <div class="flex flex-col gap-3 bg-white p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <p class="text-center text-[var(--text-2)]" x-text="scanMessage || @js(__('Point the camera at the barcode'))"></p>
                <form @submit.prevent="submitScan(manualCode)" class="flex gap-2">
                    <input x-model="manualCode" class="input h-12 flex-1" placeholder="{{ __('Or type the code') }}">
                    <button class="btn h-12">{{ __('Check') }}</button>
                </form>
                <button type="button" class="btn h-12" @click="closeScanner()">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</x-layouts::courier>
