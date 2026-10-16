@php($nombreDelPalo = ['oro' => 'Oro', 'copa' => 'Copa', 'espada' => 'Espada', 'basto' => 'Basto'][$palo])
{{--
    La carta del mazo Murga: el estandarte de una murga porteña. Raso en el color hondo del palo, el cordón con
    mostacillas, el festón y el nombre arqueado ("Los de Copa"), con el número en su medallón. Los palos y las figuras
    son apliques de lentejuelas planas, como los que se cosen a mano en las levitas.
--}}
<rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" class="murga-raso-{{ $palo }}" stroke="var(--color-tinta)" stroke-width="1.5" />
<g class="murga-{{ $palo }}">
    <use href="#murga-estandarte" width="100" height="156" />
    @foreach ([0, 180] as $giro)
        <g transform="rotate({{ $giro }} 50 78)">
            <text x="17.6" y="23" text-anchor="middle" class="murga-numero {{ $numero >= 10 ? 'murga-numero-doble' : '' }}">{{ $numero }}</text>
            <text class="murga-nombre"><textPath href="#murga-arco" startOffset="50%" text-anchor="middle">Los de {{ $nombreDelPalo }}</textPath></text>
        </g>
    @endforeach
    @if ($figura())
        <use href="#{{ $simboloDeLaFigura() }}" width="100" height="156" />
    @endif
    @foreach ($pintas() as [$x, $y, $ancho, $alto])
        <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" />
    @endforeach
</g>
