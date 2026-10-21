<x-layouts::courier :title="__('Sign in')">
    <main class="mx-auto flex min-h-screen max-w-[440px] flex-col px-5 pb-8 pt-[max(2rem,env(safe-area-inset-top))]">
        <div class="flex items-center gap-3">
            <span class="interchange" aria-hidden="true"></span>
            <span class="font-display text-[20px] font-bold tracking-[0.04em]">OMNIDLV</span>
            <span class="t-label ml-auto">{{ __('Courier') }}</span>
        </div>

        <div class="mt-auto pt-16">
            <h1 class="t-display uppercase">{{ __('Sign in') }}</h1>
            <p class="mt-2 text-[16px] text-[var(--text-2)]">{{ __('Enter the phone number your dispatcher registered. We will text you a 6-digit code.') }}</p>

            <form method="POST" action="{{ route('courier.login.send') }}" class="mt-8 flex flex-col gap-5">
                @csrf
                <x-field :label="__('Mobile phone')" name="phone">
                    <x-input name="phone" type="tel" data-phone inputmode="tel" autocomplete="tel" placeholder="050 123 45 67" required autofocus class="h-14 text-[20px]" />
                </x-field>
                <button class="btn btn-primary h-14 text-[16px]">{{ __('Send code') }}<x-icon name="arrow-right" :size="18" /></button>
            </form>
        </div>

        <div class="mt-auto flex gap-4 pt-10 text-[14px] text-[var(--text-3)]">
            @foreach (['az' => 'AZ', 'en' => 'EN', 'ru' => 'RU', 'tr' => 'TR'] as $code => $name)
                <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" class="py-2 {{ app()->getLocale() === $code ? 'text-[var(--text)] underline' : '' }}">{{ $name }}</a>
            @endforeach
        </div>
    </main>
</x-layouts::courier>
