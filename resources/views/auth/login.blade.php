<x-layouts::guest :title="__('Sign in')">
    <p class="mt-2 text-[var(--text-2)]">{{ __('Dispatchers and managers sign in with email. Couriers use the courier app.') }}</p>

    @if (session('status'))
        <div class="flash mt-6" role="status"><x-icon name="check" class="mt-0.5 text-ink-green" />{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-8 flex flex-col gap-5">
        @csrf
        <x-field :label="__('Email')" name="email">
            <x-input name="email" type="email" autocomplete="email" required autofocus />
        </x-field>
        <x-field :label="__('Password')" name="password">
            <x-input name="password" type="password" autocomplete="current-password" required />
        </x-field>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="flex items-center gap-3 text-[var(--text-2)]">
                <input type="checkbox" name="remember" value="1" class="check"> {{ __('Keep me signed in') }}
            </label>
            <a href="{{ route('password.request') }}" class="text-[var(--text-2)] underline hover:text-[var(--text)]">{{ __('Forgot your password?') }}</a>
        </div>
        <button class="btn btn-primary h-11 w-full">{{ __('Sign in') }}<x-icon name="arrow-right" :size="16" /></button>
    </form>

    <div class="rule my-8"></div>
    <p class="text-[var(--text-2)]">{{ __('Courier?') }}
        <a href="{{ route('courier.login') }}" class="text-[var(--text)] underline">{{ __('Open the courier app') }}</a></p>
</x-layouts::guest>
