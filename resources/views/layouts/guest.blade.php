@props(['title'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1230">
    <title>{{ $title }} · OmniDLV</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/phone-input-entry.js'])
</head>
<body class="h-full">
<div class="grid min-h-full lg:grid-cols-[minmax(0,1.1fr)_minmax(420px,0.9fr)]">
    <section class="relative hidden overflow-hidden border-r-[1.5px] border-[var(--hairline)] lg:flex lg:flex-col lg:justify-between lg:p-12">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <span class="interchange" aria-hidden="true"></span>
            <span class="font-display text-[20px] font-bold tracking-[0.04em]">OMNIDLV</span>
        </a>

        {{-- The product's mechanism as a line diagram: four channels feed one interchange. --}}
        <svg viewBox="0 0 640 420" class="my-10 w-full max-w-[640px]" role="img" aria-label="{{ __('Orders flow from every channel through planning to the doorstep') }}">
            {{-- Lines meet at the interchange at 45°; the two outbound lines run parallel, offset, as on a transit map. --}}
            <g fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="10">
                <path d="M30 70 H170 L322 222" stroke="#16b3a5"/>
                <path d="M30 230 H320" stroke="#2f6bff"/>
                <path d="M30 360 H200 L322 238" stroke="#ffc20e"/>
                <path d="M340 238 H420 L492 310 H610" stroke="#9b6bff"/>
                <path d="M340 222 H400 L472 150 H610" stroke="#22b14c"/>
            </g>
            <g fill="#f4f2ec" stroke-width="5">
                <circle cx="30" cy="70" r="8" stroke="#16b3a5"/>
                <circle cx="30" cy="230" r="8" stroke="#2f6bff"/>
                <circle cx="30" cy="360" r="8" stroke="#ffc20e"/>
                <circle cx="610" cy="150" r="8" stroke="#22b14c"/>
                <circle cx="610" cy="310" r="8" stroke="#9b6bff"/>
                <circle cx="436" cy="186" r="7" stroke="#22b14c"/>
            </g>
            <circle cx="330" cy="230" r="24" fill="#0b1230" stroke="#f4f2ec" stroke-width="8"/>
            <g fill="#f4f2ec" font-family="Fira Sans Condensed" font-weight="600" font-size="15" letter-spacing="1.4">
                <text x="48" y="54">API</text>
                <text x="48" y="214">{{ mb_strtoupper(__('Panel')) }}</text>
                <text x="48" y="344">{{ mb_strtoupper(__('Excel import')) }}</text>
                <text x="350" y="296">{{ mb_strtoupper(__('Planning')) }}</text>
                <text x="520" y="134">{{ mb_strtoupper(__('Delivered')) }}</text>
                <text x="520" y="342">{{ mb_strtoupper(__('Return')) }}</text>
            </g>
        </svg>

        <div class="max-w-[46ch]">
            <p class="t-display leading-tight uppercase">{{ __('Every order on one line, from intake to the doorstep.') }}</p>
            <p class="mt-3 text-[var(--text-2)]">{{ __('Route planning, live courier tracking, proof of delivery and recipient updates, built for Azerbaijan.') }}</p>
        </div>
    </section>

    <main class="flex flex-col justify-center px-5 py-10 sm:px-12">
        <a href="{{ route('home') }}" class="mb-10 flex items-center gap-3 lg:hidden">
            <span class="interchange" aria-hidden="true"></span>
            <span class="font-display text-[20px] font-bold tracking-[0.04em]">OMNIDLV</span>
        </a>
        <div class="w-full max-w-[400px]">
            <h1 class="t-display uppercase">{{ $title }}</h1>
            {{ $slot }}
        </div>
        <div class="mt-10 flex gap-3 text-[13px] text-[var(--text-3)]">
            @foreach (['az' => 'AZ', 'en' => 'EN', 'ru' => 'RU', 'tr' => 'TR'] as $code => $name)
                <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" @class(['hover:text-[var(--text)]', 'text-[var(--text)] underline' => app()->getLocale() === $code])>{{ $name }}</a>
            @endforeach
        </div>
    </main>
</div>
</body>
</html>
