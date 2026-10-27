<x-layouts::site :title="__('site.terms')">
    <p class="mt-3 text-[13px] text-[var(--text-3)]">{{ __('site.updated', ['date' => '27.10.2026']) }}</p>
    <div class="legal mt-8 flex max-w-[72ch] flex-col gap-4 text-[var(--text-2)]">
        @include(app()->getLocale() === 'az' ? 'site.legal.terms-az' : 'site.legal.terms-en')
    </div>
</x-layouts::site>
