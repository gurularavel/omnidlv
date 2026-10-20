@props(['label', 'name', 'hint' => null, 'required' => false])
@php $errorKey = str_replace(['[', ']'], ['.', ''], $name); @endphp
<div {{ $attributes->only('class') }}>
    <label class="field-label t-label" for="{{ $attributes->get('id', $errorKey) }}">
        {{ $label }}@if ($required)<span class="text-[var(--danger-text)]" aria-hidden="true"> *</span>@endif
    </label>
    {{ $slot }}
    @if ($hint && ! $errors->has($errorKey))
        <p class="mt-1.5 text-[13px] text-[var(--text-3)]">{{ $hint }}</p>
    @endif
    @error($errorKey)
        <p class="field-error" id="{{ $errorKey }}-error">{{ $message }}</p>
    @enderror
</div>
