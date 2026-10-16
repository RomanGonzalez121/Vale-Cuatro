@props(['grupo' => 0, 'puntos' => 0, 'modelo' => null, 'hasta' => null, 'forma' => 'fabrica'])

@php
    /*
     | Un grupo de cinco, como en el club: cuatro fósforos en cuadrado y uno
     | cruzado. Cada fósforo es [palito, cabeza x, cabeza y]. "grupo" dice cuál
     | es (el primero cuenta del 1 al 5, el segundo del 6 al 10). Con "modelo"
     | (una expresión de Alpine) los fósforos nuevos caen solos al sumar.
     | Debajo va la marca de los que faltan, muy tenue: así se leen los grupos
     | de cinco y dónde se separan las malas de las buenas.
     | Con "hasta", la cuenta termina en ese número: los lugares que le siguen no se dibujan.
     |
     | "forma" es cómo anota quien mira (la eligió en su Colección): con fósforos, con palotes de
     | lápiz o con tiza. Los palotes y la tiza son el mismo cuadrado con su diagonal, a mano: cada
     | trazo se corre y se curva apenas, siempre igual para el mismo lugar.
     */
    $fosforos = [
        ['M4 19.5V8', 4, 4.6],
        ['M8.5 4H20', 23.4, 4],
        ['M24 8.5V20', 24, 23.4],
        ['M19.5 24H8', 4.6, 24],
        ['M9 19 17.6 10.4', 19.8, 8.2],
    ];

    $aMano = $forma !== 'fabrica';

    /*
     | Con porotos, cada punto es un poroto acostado sobre su lado del cuadrado, y el quinto, el que
     | cierra el grupo, es un garbanzo en el medio: como se cambian cinco porotos por un garbanzo.
     | Cada poroto es [cx, cy, giro]; el giro se corre un poco por lugar, porque se tiran con la mano.
     */
    $porotos = [[5, 14, 92], [14, 5, 4], [23, 14, 86], [14, 23, 178]];

    if ($aMano && $forma !== 'porotos') {
        $lados = [[[5, 23], [5, 5]], [[5, 5], [23, 5]], [[23, 5], [23, 23]], [[23, 23], [5, 23]], [[6.5, 21.5], [21.5, 6.5]]];
        $temblor = $forma === 'tiza' ? 1.1 : 0.9;
        $pulso = fn (string $clave) => ((crc32($clave) % 2001) / 1000) - 1;
        $trazo = function (int $i) use ($lados, $pulso, $temblor, $grupo, $forma): string {
            [[$x1, $y1], [$x2, $y2]] = $lados[$i];
            $k = "{$forma}-{$grupo}-{$i}";
            $x1 += $pulso("$k-a") * $temblor;
            $y1 += $pulso("$k-b") * $temblor;
            $x2 += $pulso("$k-c") * $temblor;
            $y2 += $pulso("$k-d") * $temblor;
            $cx = ($x1 + $x2) / 2 + $pulso("$k-e") * $temblor * 1.4;
            $cy = ($y1 + $y2) / 2 + $pulso("$k-f") * $temblor * 1.4;

            return sprintf('M%.1f %.1fQ%.1f %.1f %.1f %.1f', $x1, $y1, $cx, $cy, $x2, $y2);
        };
    }
@endphp

<svg {{ $attributes->class('flex-none') }} viewBox="0 0 28 28">
    <g class="fosforo-lugar" fill="none" stroke-width="2" stroke-linecap="round">
        @foreach ($fosforos as $i => [$lugar])
            @if ($hasta === null || $grupo * 5 + $i + 1 <= $hasta)
                @if ($forma === 'porotos')
                    @if ($i < 4)
                        <ellipse cx="{{ $porotos[$i][0] }}" cy="{{ $porotos[$i][1] }}" rx="4" ry="2.5" transform="rotate({{ $porotos[$i][2] }} {{ $porotos[$i][0] }} {{ $porotos[$i][1] }})" stroke-width="1.2" />
                    @else
                        <circle cx="14" cy="14" r="3.4" stroke-width="1.2" />
                    @endif
                @else
                    <path d="{{ $aMano ? sprintf('M%s %sL%s %s', ...array_merge(...$lados[$i])) : $lugar }}" />
                @endif
            @endif
        @endforeach
    </g>

    @foreach ($fosforos as $i => [$palito, $cx, $cy])
        @php($numero = $grupo * 5 + $i + 1)
        <g @class(['fosforo', 'a-mano' => $aMano, 'puesto' => $puntos >= $numero]) @if ($modelo) :class="{ puesto: {{ $modelo }} >= {{ $numero }} }" @endif>
            @if ($forma === 'porotos' && $i < 4)
                @php([$px, $py, $giro] = $porotos[$i])
                <g transform="rotate({{ $giro }} {{ $px }} {{ $py }})">
                    <ellipse cx="{{ $px }}" cy="{{ $py }}" rx="4.2" ry="2.7" fill="#6b2c22" />
                    <path d="M{{ $px - 1.6 }} {{ $py - 0.9 }}Q{{ $px }} {{ $py - 0.1 }} {{ $px + 1.6 }} {{ $py - 0.9 }}" fill="none" stroke="#efe6d2" stroke-width="0.8" stroke-linecap="round" />
                </g>
            @elseif ($forma === 'porotos')
                <circle cx="14" cy="14" r="3.6" fill="#d9b878" stroke="#8a6a3a" stroke-width="0.9" />
                <path d="M12.6 12.2Q14.4 11.9 15.4 13.4" fill="none" stroke="#8a6a3a" stroke-width="0.8" stroke-linecap="round" />
            @elseif ($forma === 'palotes')
                <path d="{{ $trazo($i) }}" fill="none" stroke="#3f4447" stroke-width="1.8" stroke-linecap="round" />
            @elseif ($forma === 'tiza')
                <path d="{{ $trazo($i) }}" fill="none" stroke="#ecebe4" stroke-width="2.6" stroke-linecap="round" stroke-dasharray="11 0.9 7 0.7 30 1" opacity=".93" />
            @else
                <path d="{{ $palito }}" fill="none" stroke="var(--color-fosforo)" stroke-width="2.4" stroke-linecap="round" />
                <circle class="fosforo-cabeza" cx="{{ $cx }}" cy="{{ $cy }}" r="2.6" fill="var(--color-copa)" />
            @endif
        </g>
    @endforeach
</svg>
