@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination') }}" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-[13px] text-[var(--text-3)]">
            {{ __(':from–:to of :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </p>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="btn btn-sm btn-icon" aria-disabled="true"><x-icon name="chevron-right" :size="16" class="rotate-180" /></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-sm btn-icon" aria-label="{{ __('Previous') }}"><x-icon name="chevron-right" :size="16" class="rotate-180" /></a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-[var(--text-3)]">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="btn btn-sm min-w-[30px] border-[var(--text)]" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="btn btn-sm btn-ghost min-w-[30px]">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-sm btn-icon" aria-label="{{ __('Next') }}"><x-icon name="chevron-right" :size="16" /></a>
            @else
                <span class="btn btn-sm btn-icon" aria-disabled="true"><x-icon name="chevron-right" :size="16" /></span>
            @endif
        </div>
    </nav>
@endif
