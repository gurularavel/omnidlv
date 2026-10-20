@props(['icon' => 'route', 'title', 'action' => null, 'actionLabel' => null])
<div {{ $attributes->class('flex flex-col items-center justify-center gap-3 px-6 py-14 text-center') }}>
    <span class="grid size-12 place-items-center rounded-full border-[1.5px] border-[var(--hairline-strong)] text-[var(--text-2)]">
        <x-icon :name="$icon" :size="22" />
    </span>
    <p class="t-title text-[16px]">{{ $title }}</p>
    @if (trim($slot))
        <p class="max-w-[46ch] text-[var(--text-2)]">{{ $slot }}</p>
    @endif
    @if ($action)
        <a href="{{ $action }}" class="btn btn-primary mt-2"><x-icon name="plus" :size="16" />{{ $actionLabel }}</a>
    @endif
</div>
