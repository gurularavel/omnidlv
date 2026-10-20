@props(['name', 'options' => [], 'value' => null, 'placeholder' => null])
@php
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $current = (string) old($errorKey, $value instanceof \BackedEnum ? $value->value : $value);
@endphp
<select name="{{ $name }}" id="{{ $attributes->get('id', $errorKey) }}"
        @error($errorKey) aria-invalid="true" aria-describedby="{{ $errorKey }}-error" @enderror
        {{ $attributes->except('id')->class('input') }}>
    @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
    @foreach ($options as $key => $label)
        <option value="{{ $key }}" @selected($current === (string) $key)>{{ $label }}</option>
    @endforeach
    {{ $slot }}
</select>
