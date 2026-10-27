<x-layouts::site :title="__('site.faq')">
    <p class="mt-3 max-w-[64ch] text-[var(--text-2)]">{{ __('site.faq_intro') }}</p>

    @if ($faqs->isEmpty())
        <p class="mt-8 text-[var(--text-2)]">{{ __('site.faq_empty') }}</p>
    @else
        <div class="mt-8 flex flex-col gap-3">
            @foreach ($faqs as $faq)
                <details class="plate group" @if ($loop->first) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-medium [&::-webkit-details-marker]:hidden">
                        {{ $faq->question }}
                        <x-icon name="chevron-down" :size="18" class="shrink-0 text-[var(--text-3)] transition-transform group-open:rotate-180" />
                    </summary>
                    <div class="flex flex-col gap-3 border-t border-[var(--hairline)] px-5 py-4 text-[var(--text-2)]">
                        @foreach (preg_split('/\R{2,}/', trim($faq->answer)) as $paragraph)
                            <p>{!! nl2br(e($paragraph)) !!}</p>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    @endif

    @if (config('services.support_email'))
        <p class="mt-8 text-[var(--text-2)]">{{ __('site.contact') }} <a href="mailto:{{ config('services.support_email') }}" class="text-[var(--text)] underline">{{ config('services.support_email') }}</a></p>
    @endif
</x-layouts::site>
