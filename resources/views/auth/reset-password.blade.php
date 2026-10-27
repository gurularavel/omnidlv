<x-layouts::guest :title="__('Set a new password')">
    <form method="POST" action="{{ route('password.update') }}" class="mt-8 flex flex-col gap-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field :label="__('Email')" name="email">
            <x-input name="email" type="email" :value="$email" autocomplete="email" required />
        </x-field>
        <x-field :label="__('New password')" name="password" :hint="__('At least 8 characters.')">
            <x-input name="password" type="password" autocomplete="new-password" required autofocus />
        </x-field>
        <x-field :label="__('Repeat the password')" name="password_confirmation">
            <x-input name="password_confirmation" type="password" autocomplete="new-password" required />
        </x-field>
        <button class="btn btn-primary h-11 w-full">{{ __('Save password') }}<x-icon name="check" :size="16" /></button>
    </form>
</x-layouts::guest>
