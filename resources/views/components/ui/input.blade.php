{{-- Etiqueta, campo, y ayuda o error. Los demas atributos (type, wire:model, autocomplete...) van al <input>. --}}
@props(['name', 'label', 'help' => null, 'size' => 'lg'])

@php
    $id = $attributes->get('id', $name);
    $text = $size === 'lg' ? 'text-sm' : 'text-xs';
@endphp

<div>
    <label for="{{ $id }}" class="block {{ $text }} font-medium">{{ $label }}</label>
    <input id="{{ $id }}" name="{{ $name }}" @if ($help) aria-describedby="{{ $id }}-help" @endif
        {{ $attributes->except('id')->class([
            'mt-1 w-full border bg-white',
            'h-12 rounded-card px-4' => $size === 'lg',
            'h-11 rounded-btn px-3' => $size === 'md',
            'border-error' => $errors->has($name),
            'border-line' => ! $errors->has($name),
        ]) }}>
    @error($name)
        <p class="mt-1 {{ $text }} text-error">{{ $message }}</p>
    @else
        @if ($help)<p id="{{ $id }}-help" class="mt-1 {{ $text }} text-muted">{{ $help }}</p>@endif
    @enderror
</div>
