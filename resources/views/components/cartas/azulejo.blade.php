@php($nombreDelPalo = ['oro' => 'Oro', 'copa' => 'Copa', 'espada' => 'Espada', 'basto' => 'Basto'][$palo])
{{--
    La carta del mazo Azulejo de subte: la pared de una estación. Azulejos blancos de ladrillito, el cartel con el
    número y el nombre del palo, como el nombre de la estación, y la franja de color que la identifica (cada palo es
    su línea). Las figuras son murales cerámicos pintados sobre azulejos cuadrados, con la juntura encima.
--}}
<use href="#azulejo-pared" width="100" height="156" />
<g class="azulejo-{{ $palo }}">
    <use href="#azulejo-carteles" width="100" height="156" />
    @foreach ([0, 180] as $giro)
        <g transform="rotate({{ $giro }} 50 78)">
            <text x="16.6" y="19.9" text-anchor="middle" class="azulejo-numero {{ $numero >= 10 ? 'azulejo-numero-doble' : '' }}">{{ $numero }}</text>
            <text x="57.4" y="18.9" text-anchor="middle" class="azulejo-estacion">{{ $nombreDelPalo }}</text>
        </g>
    @endforeach
    @if ($figura())
        <use href="#{{ $simboloDeLaFigura() }}" width="100" height="156" />
    @endif
    @foreach ($pintas() as [$x, $y, $ancho, $alto])
        <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" />
    @endforeach
</g>
