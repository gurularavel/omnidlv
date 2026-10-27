@props(['title' => null])
@php
    $nav = [
        ['admin.dashboard', 'chart-column', __('admin.nav.overview'), 'admin.dashboard'],
        ['admin.companies.index', 'building-2', __('admin.nav.companies'), 'admin.companies.*'],
        ['admin.users.index', 'users', __('admin.nav.users'), 'admin.users.*'],
        ['admin.plans.index', 'layers', __('admin.nav.plans'), 'admin.plans.*'],
        ['admin.faq.index', 'message-square-text', __('admin.nav.faq'), 'admin.faq.*'],
        ['admin.mail.edit', 'mail', __('admin.nav.mail'), 'admin.mail.*'],
    ];
    $ownCompany = auth()->user()->employments()->withoutGlobalScopes()->where('is_active', true)->where('role_slug', '<>', 'courier')->exists();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b1230">
    <meta name="robots" content="noindex">
    <title>{{ $title ? $title.' · ' : '' }}OmniDLV Admin</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full" x-data="{ nav: false }">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 btn btn-primary">{{ __('Skip to content') }}</a>

<div class="flex min-h-full">
    <div x-show="nav" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-night-950/70 lg:hidden" @click="nav = false"></div>
    <nav aria-label="{{ __('Main') }}"
         class="fixed inset-y-0 left-0 z-40 flex w-[236px] -translate-x-full flex-col border-r-[1.5px] border-[var(--outline)] bg-night-900 px-3 py-5 transition-transform duration-300 ease-[var(--ease-out-expo)] lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
         :class="nav && 'translate-x-0'">
        <a href="{{ route('admin.dashboard') }}" class="mb-7 flex items-center gap-3 px-2">
            <span class="interchange" aria-hidden="true"></span>
            <span>
                <span class="block font-display text-[20px] font-bold leading-none tracking-[0.04em]">OMNIDLV</span>
                <span class="t-label mt-1 block text-[10px] text-ink-amber">{{ __('admin.platform') }}</span>
            </span>
        </a>

        <div class="flex flex-col gap-1">
            @foreach ($nav as [$route, $icon, $label, $pattern])
                <a href="{{ route($route) }}" class="nav-link" @if (request()->routeIs($pattern)) aria-current="page" @endif>
                    <x-icon :name="$icon" />{{ $label }}
                </a>
            @endforeach
        </div>

        @if ($ownCompany)
            <div class="rule my-4"></div>
            <a href="{{ route('dashboard') }}" class="nav-link"><x-icon name="arrow-left" />{{ __('admin.nav.to_panel') }}</a>
        @endif

        <div class="mt-auto flex flex-col gap-4 px-2 pt-6">
            <div class="rule"></div>
            <div class="flex items-center justify-between gap-2">
                <span class="min-w-0">
                    <span class="block truncate text-[13px]">{{ auth()->user()->name }}</span>
                    <span class="t-label text-[10px] text-[var(--text-3)]">{{ __('admin.superadmin') }}</span>
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-ghost btn-icon btn-sm" title="{{ __('Sign out') }}" aria-label="{{ __('Sign out') }}"><x-icon name="log-out" :size="16" /></button>
                </form>
            </div>
        </div>
    </nav>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="app-header sticky top-0 z-20 flex h-16 items-center gap-4 border-b-[1.5px] border-[var(--outline)] bg-night-900/95 px-4 backdrop-blur-sm lg:px-7">
            <button class="btn btn-ghost btn-icon lg:hidden" @click="nav = true" aria-label="{{ __('Open menu') }}"><x-icon name="menu" /></button>
            <h1 class="t-title truncate">{{ $title }}</h1>
            <div class="ml-auto flex items-center gap-2">
                <div x-data="{ open: false }" class="relative">
                    <button class="btn btn-ghost btn-sm" @click="open = !open" :aria-expanded="open" aria-haspopup="true">{{ strtoupper(app()->getLocale()) }}<x-icon name="chevron-down" :size="14" /></button>
                    <div x-show="open" x-cloak @click.outside="open = false" x-transition.origin.top.right class="plate absolute right-0 top-10 z-30 flex w-36 flex-col p-1.5 shadow-[0_12px_32px_rgb(0_0_0/0.4)]">
                        @foreach (['az' => 'Azərbaycan', 'en' => 'English'] as $code => $name)
                            <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" class="rounded px-3 py-2 hover:bg-[var(--surface-hover)]" @if (app()->getLocale() === $code) aria-current="true" @endif>{{ $name }}</a>
                        @endforeach
                    </div>
                </div>
                {{ $actions ?? '' }}
            </div>
        </header>

        <main id="main" class="flex-1 p-4 lg:p-7">
            @if (session('status'))
                <div class="flash mb-5" role="status"><x-icon name="check" class="mt-0.5 text-ink-green" />{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="flash mb-5 border-ink-scarlet/60" role="alert"><x-icon name="circle-alert" class="mt-0.5 text-[var(--danger-text)]" />{{ session('error') }}</div>
            @endif
            @if ($credentials = session('credentials'))
                <div class="flash mb-5 border-ink-amber/60" role="status">
                    <x-icon name="key-round" class="mt-0.5 text-ink-amber" />
                    <span>
                        {{ __('admin.credentials.intro') }}
                        <span class="mt-2 block font-mono text-[14px]">{{ $credentials['email'] }} · <span class="select-all">{{ $credentials['password'] }}</span></span>
                    </span>
                </div>
            @endif
            {{ $slot }}
        </main>
        <x-site-footer class="border-t border-[var(--hairline)] px-4 py-4 lg:px-7" />
    </div>
</div>
<x-cookie-consent />
@stack('scripts')
</body>
</html>
