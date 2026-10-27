@props(['company'])
@php
    $state = $company->state();
    [$ink, $form] = match ($state) {
        'active' => ['#22b14c', 'solid'],
        'trial' => ['#2f6bff', 'solid'],
        'suspended' => ['#e5484d', 'solid'],
        default => ['#8e99b8', 'dashed'],
    };
@endphp
<span {{ $attributes->class('status') }} data-form="{{ $form }}" style="--status-ink: {{ $ink }}">{{ __('admin.states.'.$state) }}</span>
