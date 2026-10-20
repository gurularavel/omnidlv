<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="theme-porcelain h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <title>{{ __('Track your order') }} · OmniDLV</title>
    @vite(['resources/css/app.css', 'resources/js/tracking.js'])
</head>
<body class="theme-porcelain min-h-full">
<main x-data="{ mode: @js($mode) }" class="mx-auto flex min-h-screen max-w-[480px] flex-col px-5 pb-8 pt-[max(2rem,env(safe-area-inset-top))]">
    @unless ($embed)
        <div class="flex items-center gap-3">
            <span class="interchange" aria-hidden="true"></span>
            <span class="font-display text-[20px] font-bold tracking-[0.04em]">OMNIDLV</span>
        </div>
    @endunless

    <div class="{{ $embed ? 'pt-2' : 'mt-auto pt-14' }}">
        <h1 class="t-display uppercase">{{ __('Track your order') }}</h1>
        <p class="mt-2 text-[16px] text-[var(--text-2)]">{{ __('Enter the tracking code from the SMS or from the shop.') }}</p>

        @if ($notFound)
            <div class="mt-6 flex gap-3 rounded-[var(--radius-plate)] border-[1.5px] border-ink-scarlet p-4" role="alert">
                <x-icon name="circle-alert" class="mt-0.5 shrink-0 text-[var(--danger-text)]" />
                <span x-text="mode === 'order' ? @js(__('No order matches this number and phone. Check both, or ask the shop for the tracking code.')) : @js(__('No order has this code. Check it in the SMS: it starts with OD and has 14 characters.'))"></span>
            </div>
        @endif

        {{-- By tracking code --}}
        <form x-show="mode === 'code'" method="GET" action="{{ route('tracking.lookup') }}" class="mt-8 flex flex-col gap-4">
            @if ($embed)<input type="hidden" name="embed" value="1">@endif
            <label for="code" class="t-label">{{ __('Tracking code') }}</label>
            <input id="code" name="code" value="{{ request('code') }}" required autocapitalize="characters" autocomplete="off" spellcheck="false"
                   placeholder="OD7KQ2MXR4PA9C" class="input h-14 font-display text-[20px] uppercase tracking-[0.12em]">
            <button class="btn btn-primary h-14 text-[16px]">{{ __('Show status') }}<x-icon name="arrow-right" :size="18" /></button>
        </form>

        {{-- By order number + phone --}}
        <form x-show="mode === 'order'" x-cloak method="GET" action="{{ route('tracking.lookup') }}" class="mt-8 flex flex-col gap-4">
            @if ($embed)<input type="hidden" name="embed" value="1">@endif
            <div>
                <label for="order" class="t-label field-label">{{ __('Order number') }}</label>
                <input id="order" name="order" value="{{ request('order') }}" required autocomplete="off" class="input h-14 text-[20px]">
            </div>
            <div>
                <label for="phone" class="t-label field-label">{{ __('Last 4 digits of your phone') }}</label>
                <input id="phone" name="phone" inputmode="numeric" pattern="\d{4}" maxlength="4" required autocomplete="off" class="input h-14 text-center font-display text-[20px] tracking-[0.4em]">
            </div>
            <button class="btn btn-primary h-14 text-[16px]">{{ __('Show status') }}<x-icon name="arrow-right" :size="18" /></button>
        </form>

        <button type="button" class="btn btn-ghost mt-3 w-full" @click="mode = mode === 'code' ? 'order' : 'code'"
                x-text="mode === 'code' ? @js(__('I have no code: search by order number')) : @js(__('I have a tracking code'))"></button>
    </div>

    @unless ($embed)
        <div class="mt-auto flex gap-4 pt-10 text-[14px] text-[var(--text-3)]">
            @foreach (['az' => 'AZ', 'en' => 'EN', 'ru' => 'RU', 'tr' => 'TR'] as $lc => $name)
                <a href="{{ request()->fullUrlWithQuery(['lang' => $lc]) }}" class="py-2 {{ app()->getLocale() === $lc ? 'text-[var(--text)] underline' : '' }}">{{ $name }}</a>
            @endforeach
        </div>
    @endunless
</main>
</body>
</html>
