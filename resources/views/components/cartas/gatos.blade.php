{{--
    La carta del mazo Gatos: los gatos del barrio. El índice es una cabeza de gato con el número adentro y el palo
    clásico chiquito debajo, para reconocerlo aunque los palos sean cosas de gato (el ovillo, la copa con leche, la
    espina y el rascador). Huellas tenues cruzan las cartas de número.
--}}
<rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="#f7f0e3" stroke="var(--color-tinta)" stroke-width="1.5" />
<rect x="5.5" y="5.5" width="89" height="145" rx="5" fill="none" stroke="#2a2622" stroke-width=".6" />
@unless ($figura())
    <use href="#gatos-huellas" width="100" height="156" />
@endunless
@foreach ([0, 180] as $giro)
    <g transform="rotate({{ $giro }} 50 78)">
        <use href="#gatos-indice" width="100" height="156" />
        <text x="17.6" y="23.6" text-anchor="middle" class="gatos-numero {{ $numero >= 10 ? 'gatos-numero-doble' : '' }}">{{ $numero }}</text>
        <use href="#palo-{{ $palo }}" x="13.6" y="30" width="8" height="12" class="tinta-{{ $palo }}" />
    </g>
@endforeach
@if ($figura())
    <use href="#{{ $simboloDeLaFigura() }}" width="100" height="156" />
@endif
@foreach ($pintas() as [$x, $y, $ancho, $alto])
    <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" />
@endforeach
