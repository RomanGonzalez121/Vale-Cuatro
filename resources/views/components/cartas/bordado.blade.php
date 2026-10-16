{{--
    La carta del mazo Bordado: un dechado de punto cruz. La tela de trama abierta con su dobladillo de vainilla, una
    guarda de rombos y el pespunte que encierra el campo, todo en el hilo del palo; el número, los palos y las figuras
    son crucecitas sobre la grilla de la tela, como los muestrarios que se bordaban para aprender.
--}}
@php($anchoDelNumero = strlen((string) $numero) * 8 - 2)
<use href="#bordado-tela" width="100" height="156" />
<g class="bordado-{{ $palo }}">
    <use href="#bordado-guarda" width="100" height="156" />
    @foreach ([0, 180] as $giro)
        <use href="#bordado-numero-{{ $numero }}" x="13" y="10.6" width="{{ $anchoDelNumero * 1.2 }}" height="12" transform="rotate({{ $giro }} 50 78)" />
    @endforeach
    @if ($figura())
        <use href="#{{ $simboloDeLaFigura() }}" width="100" height="156" />
    @endif
    @foreach ($pintas() as [$x, $y, $ancho, $alto])
        <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" />
    @endforeach
</g>
