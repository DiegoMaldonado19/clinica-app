{{-- El boton del sitio publico. `href` lo vuelve enlace; `variant` y `size` cubren los usos actuales. --}}
@props(['href' => null, 'variant' => 'primary', 'size' => 'lg'])

@php
    $classes = [
        'inline-flex items-center justify-center rounded-btn font-medium disabled:opacity-60',
        'bg-primary text-white hover:bg-primary/90' => $variant === 'primary',
        'border border-primary bg-white text-primary hover:bg-primary hover:text-white' => $variant === 'outline',
        'min-h-12 px-6' => $size === 'lg',
        'min-h-11 px-4' => $size === 'md',
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button'])->class($classes) }}>{{ $slot }}</button>
@endif
