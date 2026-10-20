<x-layouts::guest :title="__('Sign in')">
    <p class="mt-2 text-[var(--text-2)]">{{ __('Dispatchers and managers sign in with email. Couriers use the courier app.') }}</p>

    <form method="POST" action="{{ route('login') }}" class="mt-8 flex flex-col gap-5">
        @csrf
        <x-field :label="__('Email')" name="email">
            <x-input name="email" type="email" autocomplete="email" required autofocus />
        </x-field>
        <x-field :label="__('Password')" name="password">
            <x-input name="password" type="password" autocomplete="current-password" required />
        </x-field>
        <label class="flex items-center gap-3 text-[var(--text-2)]">
            <input type="checkbox" name="remember" value="1" class="check"> {{ __('Keep me signed in') }}
        </label>
        <button class="btn btn-primary h-11 w-full">{{ __('Sign in') }}<x-icon name="arrow-right" :size="16" /></button>
    </form>

    <div class="rule my-8"></div>
    <p class="text-[var(--text-2)]">{{ __('New to OmniDLV?') }}
        <a href="{{ route('register') }}" class="text-[var(--text)] underline">{{ __('Start a 14-day trial') }}</a></p>
    <p class="mt-2 text-[var(--text-2)]">{{ __('Courier?') }}
        <a href="{{ route('courier.login') }}" class="text-[var(--text)] underline">{{ __('Open the courier app') }}</a></p>
</x-layouts::guest>
