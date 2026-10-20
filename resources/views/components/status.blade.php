@props(['category', 'label' => null])
@php
    $category = $category instanceof \App\Enums\StatusCategory ? $category : \App\Enums\StatusCategory::from($category);
    $style = \App\Support\Ui::statusStyle($category);
@endphp
<span {{ $attributes->class('status') }} data-form="{{ $style['form'] }}" style="--status-ink: {{ $style['ink'] }}">{{ $label ?? $category->label() }}</span>
