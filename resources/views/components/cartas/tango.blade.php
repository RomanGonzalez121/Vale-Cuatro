{{--
    La carta del mazo Tango (nivel 9): la tapa de una partitura de tango. Papel crema en un marco bordó, el número
    sobre un pentagrama, los palos en silueta negra con el detalle del color del palo, y las figuras en silueta: el
    bandoneonista, la pareja bailando y el cantor de galera.
--}}
<rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="#6b1f24" stroke="var(--color-tinta)" stroke-width="1.5" />
<rect x="6" y="6" width="88" height="144" rx="2.5" fill="#f2e9db" />
<rect x="7.8" y="7.8" width="84.4" height="140.4" rx="1.5" fill="none" stroke="#141414" stroke-width=".7" />
@if ($figura())
    <use href="#tango-escenario" width="100" height="156" />
    <use href="#{{ $simboloDeLaFigura() }}" width="100" height="156" class="tango-{{ $palo }}" />
    {{-- En las figuras, el número lleva su fondo de papel para leerse sobre los rayos del escenario. --}}
    <rect x="8.4" y="11" width="26" height="15" fill="#f2e9db" />
    <rect x="8.4" y="11" width="26" height="15" fill="#f2e9db" transform="rotate(180 50 78)" />
@endif
<use href="#tango-pentagrama" width="100" height="156" />
@foreach ([0, 180] as $giro)
    <text x="21" y="24" text-anchor="middle" class="tango-numero" transform="rotate({{ $giro }} 50 78)">{{ $numero }}</text>
@endforeach

@foreach ($pintas() as [$x, $y, $ancho, $alto])
    <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" class="tango-{{ $palo }}" />
@endforeach
