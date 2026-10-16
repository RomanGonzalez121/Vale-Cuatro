{{--
    La carta del mazo Afiche (nivel 33): un cartel constructivista. Una franja vertical del color del palo con el
    número enorme arriba y abajo y el nombre del palo a lo largo; el resto, papel, con los palos macizos o la
    figura armada con geometría. En el oro el número va en tinta: en naipe no se leería sobre el amarillo.
--}}
<rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="#efe8d8" stroke="var(--color-tinta)" stroke-width="1.5" />
<path d="M0.75 8.75A8 8 0 0 1 8.75 0.75H28V155.25H8.75A8 8 0 0 1 0.75 147.25Z" class="afiche-franja-{{ $palo }}" />
<text x="14.4" y="31" text-anchor="middle" class="afiche-numero afiche-numero-{{ $palo }}">{{ $numero }}</text>
<text x="14.4" y="31" text-anchor="middle" class="afiche-numero afiche-numero-{{ $palo }}" transform="rotate(180 14.4 78)">{{ $numero }}</text>
<text x="14.4" y="78" text-anchor="middle" dominant-baseline="central" class="afiche-palo afiche-numero-{{ $palo }}" transform="rotate(-90 14.4 78)">{{ $palo }}</text>
<path d="M28 0.75V155.25" stroke="var(--color-tinta)" stroke-width="1.5" />
@if ($figura())
    <use href="#{{ $simboloDeLaFigura() }}" width="100" height="156" class="tinta-{{ $palo }}" />
@endif
@foreach ($pintas() as [$x, $y, $ancho, $alto])
    <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" />
@endforeach
