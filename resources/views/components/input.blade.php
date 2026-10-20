@props(['name', 'value' => null, 'type' => 'text'])
@php $errorKey = str_replace(['[', ']'], ['.', ''], $name); @endphp
<input type="{{ $type }}" name="{{ $name }}" id="{{ $attributes->get('id', $errorKey) }}"
       value="{{ $type === 'password' ? '' : old($errorKey, $value) }}"
       @error($errorKey) aria-invalid="true" aria-describedby="{{ $errorKey }}-error" @enderror
       {{ $attributes->except('id')->class('input') }}>
