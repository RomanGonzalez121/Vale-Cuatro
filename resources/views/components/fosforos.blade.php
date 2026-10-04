@props(['grupo' => 0, 'puntos' => 0, 'modelo' => null])

@php
    /*
     | Un grupo de cinco, como en el club: cuatro fósforos en cuadrado y uno
     | cruzado. Cada fósforo es [palito, cabeza x, cabeza y]. "grupo" dice cuál
     | es (el primero cuenta del 1 al 5, el segundo del 6 al 10). Con "modelo"
     | (una expresión de Alpine) los fósforos nuevos caen solos al sumar.
     */
    $fosforos = [
        ['M4 19.5V8', 4, 4.6],
        ['M8.5 4H20', 23.4, 4],
        ['M24 8.5V20', 24, 23.4],
        ['M19.5 24H8', 4.6, 24],
        ['M9 19 17.6 10.4', 19.8, 8.2],
    ];
@endphp

<svg {{ $attributes->class('flex-none') }} viewBox="0 0 28 28">
    @foreach ($fosforos as $i => [$trazo, $cx, $cy])
        @php($numero = $grupo * 5 + $i + 1)
        <g @class(['fosforo', 'puesto' => $puntos >= $numero]) @if ($modelo) :class="{ puesto: {{ $modelo }} >= {{ $numero }} }" @endif>
            <path d="{{ $trazo }}" fill="none" stroke="var(--color-fosforo)" stroke-width="2.4" stroke-linecap="round" />
            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="2.6" fill="var(--color-copa)" />
        </g>
    @endforeach
</svg>
