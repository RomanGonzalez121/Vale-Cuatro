{{--
    La carta del mazo Criollo (nivel 39, el más alto): la baraja de la pampa. Guarda pampa arriba y abajo; en la
    esquina, un recuadro con el número y el palo clásico en chico, porque acá los palos son objetos de campo (el
    patacón, el mate, el facón y el rebenque) y así nunca se confunden; figuras de almanaque sobre el horizonte.
--}}
<rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="#f0e6d0" stroke="var(--color-tinta)" stroke-width="1.5" />
<rect x="5" y="5" width="90" height="146" rx="1.5" fill="none" stroke="#2a1f17" stroke-width="1" />
<use href="#criollo-guarda" width="100" height="156" />
@foreach ([0, 180] as $giro)
    <g transform="rotate({{ $giro }} 50 78)">
        <rect x="7" y="7" width="24" height="17" rx="1" fill="#f0e6d0" stroke="#2a1f17" stroke-width="1" />
        <text x="15" y="20.4" text-anchor="middle" class="carta-indice carta-indice-criollo">{{ $numero }}</text>
        <use href="#palo-{{ $palo }}" x="22.6" y="9.6" width="7.2" height="10.8" />
    </g>
@endforeach
@if ($figura())
    <use href="#{{ $simboloDeLaFigura() }}" width="100" height="156" class="tinta-{{ $palo }}" />
@endif
@foreach ($pintas() as [$x, $y, $ancho, $alto])
    <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" />
@endforeach
