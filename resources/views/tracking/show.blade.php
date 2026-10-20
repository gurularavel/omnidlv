@php
    $t = $tracking;
    $brand = $t['company']['primary_color'] ?? '#e21d2d';
    $steps = [
        ['created', __('Order received')],
        ['assigned', __('Courier assigned')],
        ['on_delivery', __('On the way')],
        ['completed', __('Delivered')],
    ];
    $order = ['created' => 0, 'pool' => 0, 'assigned' => 1, 'in_progress' => 1, 'on_delivery' => 2, 'completed' => 3, 'failed' => 3, 'cancelled' => 3];
    $reached = $order[$t['status']['category']] ?? 0;
    $problem = in_array($t['status']['category'], ['failed', 'cancelled'], true);
    $tz = $task->company->timezone;
    $fmt = fn ($time) => $time ? \Illuminate\Support\Carbon::parse($time)->setTimezone($tz)->translatedFormat('d M, H:i') : null;
    $courierPoint = $t['courier']['courier_location'] ?? null;
    $mapConfig = [
        'destination' => [$t['destination_lat'], $t['destination_lng']],
        'courier' => $courierPoint ? [$courierPoint['lat'], $courierPoint['lng']] : null,
        'ink' => $brand,
        'code' => $t['tracking_code'],
        'live' => ! $t['is_final'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="theme-porcelain h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <meta name="robots" content="noindex">
    <title>{{ __('Order :code', ['code' => $t['tracking_code']]) }} · {{ $t['company']['name'] }}</title>
    @vite(['resources/css/app.css', 'resources/js/tracking.js'])
</head>
<body class="theme-porcelain min-h-full" style="--action: {{ $brand }}">
<div class="mx-auto flex min-h-full max-w-[560px] flex-col px-4 pb-10 pt-5">
    <header class="mb-5 flex items-center gap-3">
        @if ($t['company']['brand_logo'])
            <img src="{{ $t['company']['brand_logo'] }}" alt="{{ $t['company']['name'] }}" class="h-9 max-w-[160px] object-contain">
        @else
            <span class="font-display text-[20px] font-bold uppercase tracking-[0.04em]">{{ $t['company']['short_name'] ?? $t['company']['name'] }}</span>
        @endif
        <span class="t-code ml-auto text-[13px] text-[var(--text-2)]">{{ $t['tracking_code'] }}</span>
    </header>

    <section class="mb-4">
        <p class="t-label mb-1">{{ $t['type_slug'] === 'pickup' ? __('Your pickup') : __('Your order') }}</p>
        <h1 class="font-display text-[34px] font-bold uppercase leading-none tracking-[0.02em]" @if ($problem) style="color:#c8102e" @endif>{{ $t['status']['name'] }}</h1>
        @if ($t['status']['description'])<p class="mt-2 text-[var(--text-2)]">{{ $t['status']['description'] }}</p>@endif
        @unless ($t['is_final'])
            <p class="mt-2 text-[16px]">{{ __('Expected by :time', ['time' => $fmt($t['ends_at'])]) }}</p>
        @endunless
    </section>

    {{-- Progress as a line with stations. --}}
    <ol class="relative mb-6 grid grid-cols-4 gap-0 pt-2" aria-label="{{ __('Delivery progress') }}">
        <span class="absolute left-[12.5%] right-[12.5%] top-[17px] h-[6px] rounded-full bg-[var(--hairline)]"></span>
        <span class="absolute left-[12.5%] top-[17px] h-[6px] rounded-full transition-all duration-700" style="width: {{ $reached * 25 }}%; background: {{ $problem ? '#c8102e' : $brand }}"></span>
        @foreach ($steps as $i => [$key, $label])
            <li class="relative flex flex-col items-center gap-2 text-center">
                <span @class(['station size-5 border-[4px]', 'is-done' => $i < $reached || ($i === $reached && $t['is_final'])])
                      style="color: {{ $i <= $reached ? ($problem && $i === 3 ? '#c8102e' : $brand) : '#b9c0d0' }}"></span>
                <span class="text-[12px] leading-tight {{ $i <= $reached ? 'text-[var(--text)]' : 'text-[var(--text-3)]' }}">{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    @if ($t['destination_lat'] || $courierPoint)
        <div x-data class="mb-5 h-[260px] overflow-hidden rounded-[var(--radius-plate)] border-[1.5px] border-[var(--hairline)]"
             data-tracking-map="{{ json_encode($mapConfig) }}"></div>
    @endif

    <section class="plate mb-4">
        <dl class="divide-y divide-[var(--hairline)]">
            @if ($t['courier'])
                <div class="flex items-center gap-3 p-4">
                    <span class="grid size-10 place-items-center rounded-lg font-display font-semibold text-white" style="background: {{ $brand }}">{{ mb_substr($t['courier']['name'], 0, 1) }}</span>
                    <div><dt class="t-label">{{ __('Courier') }}</dt><dd class="font-medium">{{ $t['courier']['name'] }}</dd></div>
                </div>
            @endif
            <div class="p-4"><dt class="t-label">{{ __('Address') }}</dt>
                <dd class="mt-1">{{ $t['destination_address'] }}
                    @if ($t['destination_address_apartment'])<span class="text-[var(--text-2)]">, {{ __('apt') }} {{ $t['destination_address_apartment'] }}</span>@endif</dd></div>
            <div class="p-4"><dt class="t-label">{{ __('Recipient') }}</dt><dd class="mt-1">{{ $t['destination_name'] ?? '—' }} · {{ $t['destination_phone'] }}</dd></div>
        </dl>
    </section>

    <section class="plate mb-4">
        <h2 class="t-label border-b border-[var(--hairline)] px-4 py-3 text-[var(--text)]">{{ __('History') }}</h2>
        <ol class="px-4 py-3">
            @foreach (array_reverse($t['status_logs']->all()) as $log)
                <li class="flex justify-between gap-4 py-1.5"><span>{{ $log['name'] }}</span><span class="text-[var(--text-2)]">{{ $fmt($log['created_at']) }}</span></li>
            @endforeach
        </ol>
    </section>

    @if ($t['can_rate'])
        <section class="plate p-4" x-data="{ rating: 0 }">
            <h2 class="t-title text-[16px]">{{ __('How was the delivery?') }}</h2>
            <form method="POST" action="{{ route('tracking.rate', $t['tracking_code']) }}" class="mt-3 flex flex-col gap-3">
                @csrf
                <input type="hidden" name="rating" :value="rating">
                <div class="flex gap-2" role="radiogroup" aria-label="{{ __('Rating') }}">
                    @for ($i = 1; $i <= 5; $i++)
                        <button type="button" role="radio" :aria-checked="rating === {{ $i }}" @click="rating = {{ $i }}" class="grid size-11 place-items-center rounded-md border-[1.5px] border-[var(--hairline-strong)]"
                                :style="rating >= {{ $i }} ? 'background: {{ $brand }}; border-color: {{ $brand }}; color: #fff' : ''" aria-label="{{ $i }}">
                            <x-icon name="star" :size="20" />
                        </button>
                    @endfor
                </div>
                <textarea name="comment" class="input" maxlength="1000" placeholder="{{ __('Anything we should know? (optional)') }}" aria-label="{{ __('Comment') }}"></textarea>
                <button class="btn btn-primary" :disabled="rating === 0">{{ __('Send') }}</button>
            </form>
        </section>
    @elseif ($t['rating'])
        <p class="text-center text-[var(--text-2)]">{{ __('Thank you for your feedback.') }}</p>
    @endif

    <footer class="mt-auto pt-8 text-center text-[12px] text-[var(--text-3)]">{{ __('Delivery tracking by') }} OmniDLV</footer>
</div>
</body>
</html>
