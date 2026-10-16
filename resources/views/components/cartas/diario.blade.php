@php
    // El epígrafe de cada foto, como en el diario de la tarde.
    $epigrafe = ['sota' => 'La sota, recién llegada.', 'caballo' => 'El caballo, del hipódromo.', 'rey' => 'El rey, en su mesa.'][$figura()] ?? null;
    $nombreDelPalo = ['oro' => 'Oros', 'copa' => 'Copas', 'espada' => 'Espadas', 'basto' => 'Bastos'][$palo];
@endphp
{{--
    La carta del mazo Diario (nivel 15): una página del diario de la tarde. Papel de diario y una sola tinta; la
    cabecera con el número y el palo como titular sobre el doble filete; filetes de columna; los palos en trama de
    puntos y las figuras como fotos en trama con su epígrafe.
--}}
<rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="#ebe6d8" stroke="var(--color-tinta)" stroke-width="1.5" />
@foreach ([0, 180] as $giro)
    <g transform="rotate({{ $giro }} 50 78)">
        <text x="9" y="22" class="diario-numero">{{ $numero }}</text>
        <text x="91" y="21" text-anchor="end" class="diario-titular">{{ $nombreDelPalo }}</text>
        <path d="M8 25.6H92" stroke="#1c1b19" stroke-width="1.3" />
        <path d="M8 27.6H92" stroke="#1c1b19" stroke-width=".45" />
    </g>
@endforeach
@if ($figura())
    <use href="#{{ $simboloDeLaFigura() }}" width="100" height="156" />
    <text x="14" y="119" class="diario-epigrafe">{{ $epigrafe }}</text>
@else
    <path d="M38 32V124M62 32V124" stroke="#1c1b19" stroke-width=".3" opacity=".45" />
@endif
@foreach ($pintas() as [$x, $y, $ancho, $alto])
    <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" />
@endforeach
