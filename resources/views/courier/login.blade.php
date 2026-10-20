<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="theme-porcelain h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <title>{{ __('Courier app') }} · OmniDLV</title>
    @vite(['resources/css/app.css'])
</head>
<body class="theme-porcelain flex min-h-full flex-col items-center justify-center gap-4 px-6 text-center">
    <span class="interchange" aria-hidden="true"></span>
    <h1 class="t-title">{{ __('Courier app') }}</h1>
    <p class="max-w-[36ch] text-[var(--text-2)]">{{ __('The courier app with SMS sign-in, navigation and proof of delivery is coming in the next release.') }}</p>
</body>
</html>
