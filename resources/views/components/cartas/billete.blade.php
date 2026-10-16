{{--
    La carta del mazo Billete (nivel 31): papel crema y guarda de guilloche en la tinta del palo, el número en dos
    medallones de roseta, los palos grabados y, en las figuras, un retrato de grabado en su óvalo con el palo en un
    medallón chico debajo. Las cartas de número llevan una roseta tenue de fondo, como la marca de agua.
--}}
<use href="#billete-guarda" width="100" height="156" class="billete-{{ $palo }}" />
@if ($figura())
    <use href="#{{ $simboloDeLaFigura() }}" width="100" height="156" class="billete-{{ $palo }}" />
    <circle cx="50" cy="131.25" r="8.6" fill="#f3ebd6" stroke="currentColor" stroke-width=".8" class="billete-{{ $palo }}" />
@else
    <use href="#billete-roseta" width="100" height="156" class="billete-{{ $palo }}" />
@endif
<use href="#billete-medallones" width="100" height="156" class="billete-{{ $palo }}" />
<text x="19" y="23.2" text-anchor="middle" class="carta-indice carta-indice-billete">{{ $numero }}</text>
<text x="19" y="23.2" text-anchor="middle" class="carta-indice carta-indice-billete" transform="rotate(180 50 78)">{{ $numero }}</text>
@foreach ($pintas() as [$x, $y, $ancho, $alto])
    <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" />
@endforeach
