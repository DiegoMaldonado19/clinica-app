{{-- La tarjeta de aviso del sitio publico. Con `scroll`, se desplaza a la vista al aparecer. --}}
@props(['tone' => 'info', 'scroll' => false])

<div role="{{ $tone === 'error' ? 'alert' : 'status' }}" @if ($scroll) x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })" @endif
    {{ $attributes->class([
        'flex gap-3 rounded-card border p-4 text-sm',
        'border-info/40 bg-info/10' => $tone === 'info',
        'border-warning/40 bg-warning/10' => $tone === 'warning',
        'border-error/40 bg-error/10' => $tone === 'error',
    ]) }}>
    <span aria-hidden="true" @class(['font-semibold', 'text-info' => $tone === 'info', 'text-warning' => $tone === 'warning', 'text-error' => $tone === 'error'])>{{ $tone === 'info' ? 'i' : '!' }}</span>
    <p>{{ $slot }}</p>
</div>
