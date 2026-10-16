{{--
    La carta del mazo Catalán (nivel 35): una baraja antigua de madera, coloreada a mano. Papel envejecido, un marco
    grueso de taco, los números en recuadros y los palos y las figuras con el color corrido del contorno.
--}}
<rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="#ece0c0" stroke="var(--color-tinta)" stroke-width="1.5" />
<rect x="5" y="5" width="90" height="146" rx="3" fill="currentColor" class="tinta-{{ $palo }}" opacity=".55" />
<rect x="8.2" y="8.2" width="83.6" height="139.6" rx="1.5" fill="#ece0c0" />
<use href="#catalan-papel" width="100" height="156" />
<rect x="5" y="5" width="90" height="146" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="2.2" />
<rect x="8.2" y="8.2" width="83.6" height="139.6" rx="1.5" fill="none" stroke="var(--color-tinta)" stroke-width=".7" />
<g>
    <rect x="8.2" y="8.2" width="15" height="17" fill="#ece0c0" stroke="var(--color-tinta)" stroke-width="1.2" />
    <text x="15.7" y="21.2" text-anchor="middle" class="carta-indice carta-indice-catalan">{{ $numero }}</text>
</g>
<g transform="rotate(180 50 78)">
    <rect x="8.2" y="8.2" width="15" height="17" fill="#ece0c0" stroke="var(--color-tinta)" stroke-width="1.2" />
    <text x="15.7" y="21.2" text-anchor="middle" class="carta-indice carta-indice-catalan">{{ $numero }}</text>
</g>
@if ($figura())
    <use href="#{{ $simboloDeLaFigura() }}" width="100" height="156" class="tinta-{{ $palo }}" />
@endif
@foreach ($pintas() as [$x, $y, $ancho, $alto])
    <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" class="tinta-{{ $palo }}" />
@endforeach
