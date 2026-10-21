<x-layouts::guest :title="__('Start your trial')">
    <p class="mt-2 text-[var(--text-2)]">{{ __('14 days with every Scale feature. No card needed.') }}</p>

    <form method="POST" action="{{ route('register') }}" class="mt-8 flex flex-col gap-5">
        @csrf
        <x-field :label="__('Company name')" name="company" required>
            <x-input name="company" autocomplete="organization" required autofocus />
        </x-field>
        <x-field :label="__('Your name')" name="name" required>
            <x-input name="name" autocomplete="name" required />
        </x-field>
        <x-field :label="__('Email')" name="email" required>
            <x-input name="email" type="email" autocomplete="email" required />
        </x-field>
        <x-field :label="__('Phone')" name="phone" :hint="__('Optional. For example 050 123 45 67')">
            <x-input name="phone" type="tel" data-phone autocomplete="tel" inputmode="tel" />
        </x-field>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field :label="__('Password')" name="password" required>
                <x-input name="password" type="password" autocomplete="new-password" required />
            </x-field>
            <x-field :label="__('Repeat password')" name="password_confirmation" required>
                <x-input name="password_confirmation" type="password" autocomplete="new-password" required />
            </x-field>
        </div>
        <label class="flex items-start gap-3 text-[var(--text-2)]">
            <input type="checkbox" name="terms" value="1" class="check mt-0.5" @checked(old('terms'))>
            <span>{{ __('I accept the terms of service and the privacy policy.') }}</span>
        </label>
        @error('terms')<p class="field-error -mt-3">{{ $message }}</p>@enderror
        <button class="btn btn-primary h-11 w-full">{{ __('Create company') }}<x-icon name="arrow-right" :size="16" /></button>
    </form>

    <div class="rule my-8"></div>
    <p class="text-[var(--text-2)]">{{ __('Already have an account?') }} <a href="{{ route('login') }}" class="text-[var(--text)] underline">{{ __('Sign in') }}</a></p>
</x-layouts::guest>
