{{--
    La carta del mazo Fileteado (nivel 37): la carta negra de un filete. El borde en oro, una voluta con su acanto en
    dos esquinas y el número en las otras dos, en oro con su sombra en Copa; los palos pintados con luz y sombra y las
    figuras como emblemas en un óvalo de banda.
--}}
<rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="#15191a" stroke="var(--color-tinta)" stroke-width="1.5" />
<rect x="4.5" y="4.5" width="91" height="147" rx="5" fill="none" stroke="#f0b429" stroke-width=".9" />
<rect x="6.6" y="6.6" width="86.8" height="142.8" rx="3.5" fill="none" stroke="#fbfaf5" stroke-width=".35" opacity=".6" />
<use href="#filete-carta-esquina" width="100" height="156" />
<use href="#filete-carta-esquina" width="100" height="156" transform="rotate(180 50 78)" />
@foreach ([0, 180] as $giro)
    <g transform="rotate({{ $giro }} 50 78)">
        <text x="15" y="25.6" text-anchor="middle" class="filete-numero filete-numero-sombra">{{ $numero }}</text>
        <text x="15" y="25" text-anchor="middle" class="filete-numero">{{ $numero }}</text>
    </g>
@endforeach
@if ($figura())
    <use href="#{{ $simboloDeLaFigura() }}" width="100" height="156" class="filete-{{ $palo }}" />
@endif
@foreach ($pintas() as [$x, $y, $ancho, $alto])
    <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" />
@endforeach
