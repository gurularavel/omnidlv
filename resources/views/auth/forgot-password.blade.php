<x-layouts::guest :title="__('Reset password')">
    <p class="mt-2 text-[var(--text-2)]">{{ __('Enter the email you sign in with. We will send a link to set a new password.') }}</p>

    @if (session('status'))
        <div class="flash mt-6" role="status"><x-icon name="check" class="mt-0.5 text-ink-green" />{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 flex flex-col gap-5">
        @csrf
        <x-field :label="__('Email')" name="email">
            <x-input name="email" type="email" autocomplete="email" required autofocus />
        </x-field>
        <button class="btn btn-primary h-11 w-full">{{ __('Send reset link') }}<x-icon name="send" :size="16" /></button>
    </form>

    <div class="rule my-8"></div>
    <p class="text-[var(--text-2)]"><a href="{{ route('login') }}" class="text-[var(--text)] underline">{{ __('Back to sign in') }}</a></p>
    <p class="mt-2 text-[var(--text-2)]">{{ __('Couriers sign in with an SMS code and need no password.') }}</p>
</x-layouts::guest>
