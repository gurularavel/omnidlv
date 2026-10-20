@props(['endsAt', 'final' => false])
@php
    $left = $final ? null : \App\Support\Ui::minutesLeft($endsAt);
    $urgency = \App\Support\Ui::urgency($left);
@endphp
<span {{ $attributes->class([
    't-display text-[22px] tabular-nums',
    'text-[var(--danger-text)]' => $urgency === 'late',
    'text-ink-amber' => $urgency === 'risk',
    'text-[var(--text-3)]' => $final,
]) }} title="{{ \App\Support\Ui::localTime($endsAt, 'd M H:i') }}">
    @if ($final)
        {{ \App\Support\Ui::localTime($endsAt) }}
    @else
        {{ \App\Support\Ui::minutes($left) }}
    @endif
</span>
