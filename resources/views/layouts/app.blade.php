@props(['title' => null, 'fullBleed' => false])
@php
    $nav = [
        ['dashboard', 'map-pinned', __('Live monitor'), 'dashboard'],
        ['tasks.index', 'list-checks', __('Tasks'), 'tasks.*'],
        ['couriers.index', 'truck', __('Couriers'), 'couriers.*'],
        ['hubs.index', 'warehouse', __('Hubs'), 'hubs.*'],
        ['teams.index', 'users', __('Teams'), 'teams.*'],
    ];
    $admin = [
        ['integration.keys', 'plug', __('Integration'), 'integration.*'],
        ['sms.index', 'message-square-text', __('SMS log'), 'sms.*'],
        ['settings.edit', 'settings-2', __('Settings'), 'settings.*'],
    ];
    $canManage = $currentRole?->canManage();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b1230">
    <title>{{ $title ? $title.' · ' : '' }}OmniDLV</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full" x-data="{ nav: false }">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 btn btn-primary">{{ __('Skip to content') }}</a>

<div class="flex min-h-full">
    {{-- Navigation rail --}}
    <div x-show="nav" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-night-950/70 lg:hidden" @click="nav = false"></div>
    <nav aria-label="{{ __('Main') }}"
         class="fixed inset-y-0 left-0 z-40 flex w-[236px] -translate-x-full flex-col border-r-[1.5px] border-[var(--outline)] bg-night-900 px-3 py-5 transition-transform duration-300 ease-[var(--ease-out-expo)] lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
         :class="nav && 'translate-x-0'">
        <a href="{{ route('dashboard') }}" class="mb-7 flex items-center gap-3 px-2">
            <span class="interchange" aria-hidden="true"></span>
            <span>
                <span class="block font-display text-[20px] font-bold leading-none tracking-[0.04em]">OMNIDLV</span>
                <span class="t-label mt-1 block text-[10px] text-[var(--text-3)]">{{ Str::limit($currentCompany->name, 24) }}</span>
            </span>
        </a>

        <div class="flex flex-col gap-1">
            @foreach ($nav as [$route, $icon, $label, $pattern])
                <a href="{{ route($route) }}" class="nav-link" @if (request()->routeIs($pattern)) aria-current="page" @endif>
                    <x-icon :name="$icon" />{{ $label }}
                </a>
            @endforeach
        </div>

        @if ($canManage)
            <div class="rule my-4"></div>
            <div class="flex flex-col gap-1">
                @foreach ($admin as [$route, $icon, $label, $pattern])
                    <a href="{{ route($route) }}" class="nav-link" @if (request()->routeIs($pattern)) aria-current="page" @endif>
                        <x-icon :name="$icon" />{{ $label }}
                    </a>
                @endforeach
            </div>
        @endif

        <div class="mt-auto flex flex-col gap-4 px-2 pt-6">
            <div x-data="liveStatus" class="flex flex-col gap-1">
                <span class="t-label flex items-center gap-2">
                    <span class="size-2.5 rounded-full" :class="connected ? 'bg-ink-green' : 'bg-ink-amber'"></span>
                    {{ __('System status') }}
                </span>
                <span class="text-[13px] text-[var(--text-2)]" x-text="connected ? @js(__('Live updates on')) : @js(__('Refreshing every 30 s'))">{{ __('Live updates on') }}</span>
            </div>
            <div class="rule"></div>
            <div x-data="clock(@js($currentCompany->timezone))">
                <span class="t-value block" x-text="time">{{ now($currentCompany->timezone)->format('H:i') }}</span>
                <span class="t-label text-[10px] text-[var(--text-3)]">{{ __('Local time') }} · {{ $currentCompany->timezone }}</span>
            </div>
            <div class="rule"></div>
            <div class="flex items-center justify-between gap-2">
                <span class="min-w-0">
                    <span class="block truncate text-[13px]">{{ auth()->user()->name }}</span>
                    <span class="t-label text-[10px] text-[var(--text-3)]">{{ $currentRole->label() }}</span>
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
                <form action="{{ route('tasks.index') }}" class="relative hidden md:block" role="search">
                    <x-icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-3)]" />
                    <input name="q" value="{{ request('q') }}" class="input h-[38px] w-[280px] pl-9" placeholder="{{ __('Tracking code, phone, address') }}" aria-label="{{ __('Search tasks') }}">
                </form>
                <div x-data="{ open: false }" class="relative">
                    <button class="btn btn-ghost btn-sm" @click="open = !open" :aria-expanded="open" aria-haspopup="true">{{ strtoupper(app()->getLocale()) }}<x-icon name="chevron-down" :size="14" /></button>
                    <div x-show="open" x-cloak @click.outside="open = false" x-transition.origin.top.right class="plate absolute right-0 top-10 z-30 flex w-36 flex-col p-1.5 shadow-[0_12px_32px_rgb(0_0_0/0.4)]">
                        @foreach (['az' => 'Azərbaycan', 'en' => 'English', 'ru' => 'Русский', 'tr' => 'Türkçe'] as $code => $name)
                            <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" class="rounded px-3 py-2 hover:bg-[var(--surface-hover)]" @if (app()->getLocale() === $code) aria-current="true" @endif>{{ $name }}</a>
                        @endforeach
                    </div>
                </div>
                {{ $actions ?? '' }}
            </div>
        </header>

        <main id="main" @class(['flex-1', 'p-4 lg:p-7' => ! $fullBleed])>
            @if (session('status'))
                <div class="flash mb-5" role="status"><x-icon name="check" class="mt-0.5 text-ink-green" />{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="flash mb-5 border-ink-scarlet/60" role="alert"><x-icon name="circle-alert" class="mt-0.5 text-[var(--danger-text)]" />{{ session('error') }}</div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>

<script>
    window.OmniDLV = @js([
        'companyId' => $currentCompany->id,
        'locale' => app()->getLocale(),
        'tiles' => config('services.map.tiles'),
        'attribution' => config('services.map.attribution'),
    ]);
</script>
@stack('scripts')
</body>
</html>
