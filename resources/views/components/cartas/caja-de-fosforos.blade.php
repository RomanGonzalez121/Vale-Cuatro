@php
    $marca = ['sota' => 'La Sota', 'caballo' => 'El Caballo', 'rey' => 'El Rey'][$figura()] ?? null;
@endphp
{{--
    La carta del mazo Caja de fósforos (nivel 19): la tapa de una caja de fósforos de almacén. El raspador en los dos
    costados, la etiqueta en el color del palo con el número en un sello, el panel crema con su guarda de puntos y,
    en las figuras, la marca de la caja: una viñeta redonda con su cinta.
--}}
<rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="#d8c3a0" stroke="var(--color-tinta)" stroke-width="1.5" />
<use href="#fosforos-raspadores" width="100" height="156" />
<rect x="8.5" y="4" width="83" height="148" rx="2" class="fosforos-etiqueta-{{ $palo }}" stroke="#1c1612" stroke-width="1" />
<rect x="12.5" y="22" width="75" height="112" rx="1" fill="#f5ecd6" stroke="#1c1612" stroke-width="1" />
<use href="#fosforos-guarda" width="100" height="156" />
@foreach ([0, 180] as $giro)
    <g transform="rotate({{ $giro }} 50 78)">
        <circle cx="20" cy="13" r="7" fill="#f5ecd6" stroke="#1c1612" stroke-width="1" />
        <text x="20" y="16.6" text-anchor="middle" class="fosforos-numero">{{ $numero }}</text>
        <text x="86" y="15.6" text-anchor="end" class="fosforos-leyenda fosforos-leyenda-{{ $palo }}">contenido aprox. 40</text>
    </g>
@endforeach
@if ($figura())
    <use href="#{{ $simboloDeLaFigura() }}" width="100" height="156" class="tinta-{{ $palo }}" />
    <text x="50" y="112.6" text-anchor="middle" class="fosforos-marca fosforos-marca-{{ $palo }}">{{ $marca }}</text>
@endif
@foreach ($pintas() as [$x, $y, $ancho, $alto])
    <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" />
@endforeach
