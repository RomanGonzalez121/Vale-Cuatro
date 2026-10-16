@php
    $delPalo = ['oro' => 'de oro', 'copa' => 'de copa', 'espada' => 'de espada', 'basto' => 'de basto'][$palo];
@endphp
{{--
    La carta del mazo Tipográfico (nivel 29): un afiche de imprenta de tipos de madera, como los de los bailes del
    club de barrio. Papel de afiche y dos tintas: el número enorme en negro con su segunda tinta (el color del palo)
    corrida, como un taco mal registrado; "de copa" entre filetes; los palos como tacos macizos y las figuras como
    clisés, en el medio del afiche.
--}}
<rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="#efe6cc" stroke="var(--color-tinta)" stroke-width="1.5" />
@foreach ([0, 180] as $giro)
    <g transform="rotate({{ $giro }} 50 78)">
        <text x="10.6" y="33.6" class="tipografico-numero {{ $numero >= 10 ? 'tipografico-numero-doble' : '' }} tipografico-segunda-{{ $palo }}">{{ $numero }}</text>
        <text x="9" y="32" class="tipografico-numero {{ $numero >= 10 ? 'tipografico-numero-doble' : '' }}">{{ $numero }}</text>
        <path d="M44 20.5H91" stroke="#191714" stroke-width="1.6" />
        <path d="M44 22.7H91" stroke="#191714" stroke-width=".5" />
        <text x="91" y="31.6" text-anchor="end" class="tipografico-palo">{{ $delPalo }}</text>
        <path d="M44 35H91" stroke="#191714" stroke-width=".5" />
        <path d="M9 39H91" stroke="#191714" stroke-width="1.2" />
    </g>
@endforeach
@if ($figura())
    <use href="#figura-{{ $figura() }}-clise" y="6" width="100" height="156" class="tipografico-segunda-color-{{ $palo }}" />
@endif
@foreach ($pintas() as [$x, $y, $ancho, $alto])
    <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" />
@endforeach
