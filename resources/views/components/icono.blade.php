@props(['nombre', 'titulo' => null])

@php
    // Los trazos de cada ícono están en App\Identidad\Iconos, porque también los dibuja la carta de modo.
    [$trazos, $cabezas] = App\Identidad\Iconos::de($nombre);
@endphp

<svg {{ $attributes->class('icono shrink-0') }} viewBox="0 0 24 24" width="24" height="24" fill="none"
    @if ($titulo) role="img" aria-label="{{ $titulo }}" @else aria-hidden="true" @endif>
    <path d="{{ $trazos }}" stroke="currentColor" stroke-width="2" stroke-linejoin="miter" />
    @foreach ($cabezas as [$cx, $cy, $radio])
        <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $radio }}" fill="currentColor" />
    @endforeach
</svg>
