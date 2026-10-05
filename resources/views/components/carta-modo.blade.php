@props(['icono', 'nombre', 'renglones'])

@php
    /*
     | La carta de un modo de juego: un naipe del mismo mazo, con el ícono del modo
     | en el lugar del palo y el nombre impreso. El ícono va dibujado como fósforos
     | de verdad: el palito en Tinta y la cabeza en Copa, igual que en el tanteador.
     */
    [$trazos, $cabezas] = App\Identidad\Iconos::de($icono);
    $primerRenglon = count($renglones) === 1 ? 128 : 120;
@endphp

<svg {{ $attributes->class('carta') }} viewBox="0 0 100 156" role="img" aria-label="Carta del modo {{ $nombre }}" data-modo="{{ $icono }}">
    <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
    <path d="M9 9H91V147H9Z" fill="none" stroke="var(--color-tinta)" stroke-width="1" />

    <g transform="translate(18.8 27) scale(2.6)">
        <path d="{{ $trazos }}" fill="none" stroke="var(--color-tinta)" stroke-width="2" stroke-linejoin="miter" />
        @foreach ($cabezas as [$cx, $cy, $radio])
            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $radio }}" fill="var(--color-copa)" />
        @endforeach
    </g>

    @foreach ($renglones as $n => $renglon)
        <text x="50" y="{{ $primerRenglon + $n * 15 }}" text-anchor="middle" class="carta-modo-nombre">{{ $renglon }}</text>
    @endforeach
</svg>
