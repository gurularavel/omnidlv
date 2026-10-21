@props(['title' => null, 'bare' => false])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="theme-porcelain h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Kuryer">
    <link rel="manifest" href="/courier.webmanifest">
    <link rel="icon" href="/icons/courier.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/courier.svg">
    <title>{{ $title ? $title.' · ' : '' }}{{ __('Courier') }} · OmniDLV</title>
    @vite(['resources/css/app.css', 'resources/js/courier.js'])
</head>
<body {{ $attributes->class('theme-porcelain courier min-h-full') }}>
    {{ $slot }}

    @auth
        @include('courier.partials.nav-sheet')
    @endauth

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/courier-sw.js', { scope: '/courier' }).catch(() => {}));
        }
    </script>
    @stack('scripts')
</body>
</html>
