@props(['nombre', 'puntos' => 0, 'modelo' => null, 'rotulos' => false])

@php
    /*
     | Un grupo de cinco, como en el club: cuatro fósforos en cuadrado y uno cruzado.
     | Tres grupos de malas y tres de buenas. Cada fósforo es [palito, cabeza x, cabeza y].
     | Con "modelo" (una expresión de Alpine) los fósforos nuevos caen solos al sumar.
     */
    $grupo = [
        ['M4 19.5V8', 4, 4.6],
        ['M8.5 4H20', 23.4, 4],
        ['M24 8.5V20', 24, 23.4],
        ['M19.5 24H8', 4.6, 24],
        ['M9 19 17.6 10.4', 19.8, 8.2],
    ];
@endphp

<div {{ $attributes->class('sobre-pano bg-pano-hondo text-naipe') }}>
    <p class="flex items-baseline gap-2 leading-none">
        <span class="text-sm font-semibold">{{ $nombre }}</span>
        <span class="text-2xl font-black tabular-nums text-oro" @if ($modelo) x-text="{{ $modelo }}" @endif>{{ $puntos }}</span>
        <span class="sr-only">puntos</span>
    </p>

    <div class="mt-2 flex items-center gap-[0.2em]" aria-hidden="true">
        @for ($g = 0; $g < 6; $g++)
            @if ($g === 3)
                <span class="mx-[0.25em] h-[1.3em] w-px bg-naipe/45"></span>
            @endif
            <svg viewBox="0 0 28 28" class="size-[1.45em] flex-none">
                @foreach ($grupo as $i => [$palito, $cx, $cy])
                    @php($numero = $g * 5 + $i + 1)
                    <g @class(['fosforo', 'puesto' => $puntos >= $numero]) @if ($modelo) :class="{ puesto: {{ $modelo }} >= {{ $numero }} }" @endif>
                        <path d="{{ $palito }}" fill="none" stroke="var(--color-fosforo)" stroke-width="2.4" stroke-linecap="round" />
                        <circle cx="{{ $cx }}" cy="{{ $cy }}" r="2.6" fill="var(--color-copa)" />
                    </g>
                @endforeach
            </svg>
        @endfor
    </div>

    @if ($rotulos)
        <p class="mt-1.5 flex text-xs text-naipe/80" aria-hidden="true">
            <span class="w-[calc(1.45em*3*1.5)]">malas</span>
            <span>buenas</span>
        </p>
    @endif
</div>
