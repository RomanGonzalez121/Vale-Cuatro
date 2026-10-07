@props(['nivel', 'puesto' => true, 'modelo' => null])

{{--
    La dificultad del bot contada con fósforos, como los puntos del tanteador:
    todos los niveles usan el mismo cuadrado, el grupo de cuatro, con uno, dos,
    tres o cuatro puestos. Así los cuatro dibujos miden lo mismo y el último
    nivel es el que cierra el cuadrado, que es el isotipo.

    Son gruesos y de cabeza grande, como los de la carta de modo. Debajo va la
    marca de cada lugar: la de los fósforos que el nivel tiene, bien visible,
    para que se lea cuántos son aunque no esté elegido; la de los que le faltan,
    muy tenue. Con "modelo" (una expresión de Alpine) caen de a uno cuando pasa
    a ser cierta, y la cabeza de cada uno se enciende al llegar.

    Cada fósforo es [palito, cabeza x, cabeza y], en el orden en que se anotan.
--}}

@php
    $cuadrado = [
        ['M4 19.5V8', 4, 4.6],
        ['M8.5 4H20', 23.4, 4],
        ['M24 8.5V20', 24, 23.4],
        ['M19.5 24H8', 4.6, 24],
    ];
@endphp

<span {{ $attributes->class('nivel-fosforos flex') }} aria-hidden="true">
    <svg viewBox="0 0 28 28" class="h-[1.5em] w-[1.5em] flex-none overflow-visible">
        @foreach ($cuadrado as $i => [$trazo, $cx, $cy])
            <g @class(['fosforo-lugar', 'fosforo-falta' => $i >= $nivel->value]) fill="none" stroke-linecap="round">
                <path d="{{ $trazo }}" stroke-width="3" />
                <path d="M{{ $cx }} {{ $cy }}V{{ $cy }}" stroke-width="6.8" />
            </g>
        @endforeach

        @foreach (array_slice($cuadrado, 0, $nivel->value) as $i => [$trazo, $cx, $cy])
            <g @class(['fosforo', 'puesto' => $puesto]) @if ($modelo) :class="{ puesto: {{ $modelo }} }" @endif style="--orden: {{ $i }}">
                <path d="{{ $trazo }}" fill="none" stroke="var(--color-fosforo)" stroke-width="3" stroke-linecap="round" />
                <circle class="fosforo-cabeza" cx="{{ $cx }}" cy="{{ $cy }}" r="3.4" fill="var(--color-copa)" />
            </g>
        @endforeach
    </svg>
</span>
