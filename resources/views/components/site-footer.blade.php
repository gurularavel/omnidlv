{{-- Footer on every surface: copyright and the public pages. --}}
<footer {{ $attributes->class('flex flex-wrap items-center gap-x-5 gap-y-2 text-[13px] text-[var(--text-3)]') }}>
    <span>{{ __('site.copyright', ['year' => 2026]) }}</span>
    <nav class="flex flex-wrap gap-x-4 gap-y-1" aria-label="{{ __('site.terms') }}">
        <a href="{{ route('site.faq') }}" class="hover:text-[var(--text)]">{{ __('site.faq') }}</a>
        <a href="{{ route('site.terms') }}" class="hover:text-[var(--text)]">{{ __('site.terms') }}</a>
        <a href="{{ route('site.cookies') }}" class="hover:text-[var(--text)]">{{ __('site.cookies') }}</a>
    </nav>
</footer>
