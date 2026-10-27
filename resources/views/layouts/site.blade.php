@props(['title'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1230">
    <title>{{ $title }} · OmniDLV</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-full flex-col">
<header class="border-b-[1.5px] border-[var(--outline)] bg-night-900">
    <div class="mx-auto flex h-16 max-w-[880px] items-center gap-4 px-4 sm:px-6">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <span class="interchange" aria-hidden="true"></span>
            <span class="font-display text-[20px] font-bold tracking-[0.04em]">OMNIDLV</span>
        </a>
        <div class="ml-auto flex items-center gap-3 text-[13px]">
            @foreach (['az' => 'AZ', 'en' => 'EN', 'ru' => 'RU', 'tr' => 'TR'] as $code => $name)
                <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" @class(['hidden text-[var(--text-3)] hover:text-[var(--text)] sm:inline', 'text-[var(--text)] underline' => app()->getLocale() === $code])>{{ $name }}</a>
            @endforeach
            <a href="{{ route('login') }}" class="btn btn-sm">{{ __('site.sign_in') }}</a>
        </div>
    </div>
</header>

<main class="mx-auto w-full max-w-[880px] flex-1 px-4 py-10 sm:px-6">
    <h1 class="t-display uppercase">{{ $title }}</h1>
    {{ $slot }}
</main>

<div class="border-t-[1.5px] border-[var(--outline)]">
    <x-site-footer class="mx-auto max-w-[880px] px-4 py-6 sm:px-6" />
</div>
<x-cookie-consent />
</body>
</html>
