@props(['de' => null])

@php
    // El sello que va al lado del apodo. Sin distintivo elegido no se dibuja nada.
    $dibujo = $de === null ? null : (App\Identidad\Distintivos::TODOS[$de] ?? null);
    $nombre = $de === null ? null : (App\Juego\Premios::POR_NIVEL['distintivo'][$de]['nombre'] ?? null);
@endphp

@if ($dibujo)
    <svg {{ $attributes->class('distintivo inline-block size-[1em] shrink-0 align-[-0.12em]') }} viewBox="0 0 24 24" width="24" height="24" fill="none" role="img" aria-label="Distintivo: {{ $nombre }}">
        <path d="{{ $dibujo[0] }}" stroke="currentColor" stroke-width="2" />
        @foreach ($dibujo[1] as [$cx, $cy, $radio])
            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $radio }}" fill="currentColor" />
        @endforeach
    </svg>
@endif
