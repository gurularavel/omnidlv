<x-layouts::courier :title="__('Code')">
    <main class="mx-auto flex min-h-screen max-w-[440px] flex-col px-5 pb-8 pt-[max(2rem,env(safe-area-inset-top))]">
        <a href="{{ route('courier.login') }}" class="flex items-center gap-2 py-2 text-[var(--text-2)]">
            <x-icon name="chevron-right" :size="18" class="rotate-180" />{{ __('Change number') }}
        </a>

        <div class="mt-auto pt-12">
            <h1 class="t-display uppercase">{{ __('Enter the code') }}</h1>
            <p class="mt-2 text-[16px] text-[var(--text-2)]">{{ __('If :phone is registered, a code is on its way. It works for 5 minutes.', ['phone' => $phone]) }}</p>

            @if ($demoCode)
                {{-- Demo SMS mode only: nothing is texted, so the code is shown here. --}}
                <div class="mt-6 rounded-[var(--radius-plate)] border-[1.5px] border-dashed border-ink-amber p-4">
                    <p class="t-label text-[var(--text)]">{{ __('Demo SMS') }}</p>
                    <p class="mt-1 text-[var(--text-2)]">{{ __('SMS sending is not connected yet. Your code:') }}</p>
                    <p class="t-display mt-2 tracking-[0.3em]">{{ $demoCode }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('courier.verify.submit') }}" class="mt-8 flex flex-col gap-5">
                @csrf
                <input type="hidden" name="phone" value="{{ $phone }}">
                <x-field :label="__('6-digit code')" name="code">
                    <x-input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" maxlength="6" required autofocus
                             class="h-16 text-center font-display text-[32px] tracking-[0.4em]" />
                </x-field>
                <button class="btn btn-primary h-14 text-[16px]">{{ __('Sign in') }}<x-icon name="arrow-right" :size="18" /></button>
            </form>

            <form method="POST" action="{{ route('courier.login.send') }}" class="mt-4">
                @csrf
                <input type="hidden" name="phone" value="{{ $phone }}">
                <button class="btn btn-ghost w-full">{{ __('Send a new code') }}</button>
            </form>
        </div>
    </main>
</x-layouts::courier>
