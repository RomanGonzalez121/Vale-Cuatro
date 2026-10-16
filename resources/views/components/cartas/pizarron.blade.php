{{--
    La carta del mazo Pizarrón (nivel 26): la pizarra de un bodegón. Marco de madera, pizarra verde oscura con
    borrones, el número escrito a tiza sobre su subrayado, y todo dibujado a tiza: los palos con su relleno de tiza
    de color y las figuras como los dibujitos de la pizarra (el mozo con la bandeja, un caballito y el rey).
--}}
<rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="#7a5236" stroke="var(--color-tinta)" stroke-width="1.5" />
<rect x="5" y="5" width="90" height="146" rx="2" fill="#2b3330" />
<use href="#pizarron-marco" width="100" height="156" />
@foreach ([0, 180] as $giro)
    <g transform="rotate({{ $giro }} 50 78)">
        <text x="11" y="23" class="pizarron-numero">{{ $numero }}</text>
        <path d="M10 26.5Q20 24.6 31 26.2" fill="none" stroke="#ece9df" stroke-width=".8" stroke-linecap="round" opacity=".8" />
    </g>
@endforeach
@if ($figura())
    <use href="#figura-{{ $figura() }}-tiza-{{ $palo }}" width="100" height="156" />
@endif
@foreach ($pintas() as [$x, $y, $ancho, $alto])
    <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" />
@endforeach
