@props(['logro', 'escalon' => 0, 'fecha' => null, 'secreto' => false])

@php
    /*
     | Un sello de la libreta: un sello de goma entintado del color de su tema, con el nombre del logro arriba,
     | la fecha en que se ganó abajo, el ícono en el medio y un punto por escalón. Lo que todavía no se ganó es el
     | círculo punteado donde va a ir el sello; un secreto, además, no dice qué es.
     | Cada sello queda apenas torcido, siempre igual para el mismo logro, como se pone un sello a mano.
     */
    $ficha = App\Juego\Logros::ficha($logro);
    $tinta = App\Juego\Logros::TEMAS[$ficha['tema']]['tinta'];
    $deNoche = App\Juego\Logros::TEMAS[$ficha['tema']]['noche'];
    $giro = (crc32($logro) % 13) - 6;
    $nombre = mb_strtoupper($ficha['nombre']);
    $letra = min(9.5, round(118 / max(1, mb_strlen($nombre)), 1));
    $total = count($ficha['escalones']);
    // Fijo para el mismo sello: el final de la partida se vuelve a armar desde los eventos y tiene que dar idéntico.
    $id = 'sello-'.$logro.'-'.$escalon;
    $dia = $fecha ? mb_strtoupper(\Carbon\Carbon::parse($fecha)->locale('es')->translatedFormat('j M Y')) : null;
    $etiqueta = $escalon > 0
        ? 'Sello '.$ficha['nombre'].($total > 1 ? ', escalón '.$escalon.' de '.$total : '').($fecha ? ', del '.\Carbon\Carbon::parse($fecha)->locale('es')->translatedFormat('j \d\e F \d\e Y') : '')
        : ($secreto ? 'Sello secreto, todavía sin ganar' : 'Sello '.$ficha['nombre'].', todavía sin ganar');
    [$trazos, $cabezas] = App\Identidad\Iconos::de($ficha['icono']);
@endphp

<svg {{ $attributes->class(['sello', 'sello-vacio' => $escalon === 0]) }} viewBox="0 0 100 100" role="img" aria-label="{{ $etiqueta }}"
    style="--tinta: {{ $tinta }}; --tinta-noche: {{ $deNoche }}; --giro: {{ $giro }}deg">
    @if ($escalon > 0)
        <defs>
            <path id="{{ $id }}-arriba" d="M15 50A35 35 0 0 1 85 50" />
            <path id="{{ $id }}-abajo" d="M12 50A38 38 0 0 0 88 50" />
        </defs>
        <g class="sello-tinta" fill="none" stroke="currentColor">
            <circle cx="50" cy="50" r="46.5" stroke-width="3" />
            <circle cx="50" cy="50" r="42.5" stroke-width="1" />
            <circle cx="50" cy="50" r="23" stroke-width="1.5" />
        </g>
        <text class="sello-letra" font-size="{{ $letra }}" fill="currentColor"><textPath href="#{{ $id }}-arriba" startOffset="50%" text-anchor="middle">{{ $nombre }}</textPath></text>
        @if ($dia)
            <text class="sello-letra" font-size="6.5" fill="currentColor"><textPath href="#{{ $id }}-abajo" startOffset="50%" text-anchor="middle">{{ $dia }}</textPath></text>
        @endif
        {{-- Las dos estrellitas de los costados separan el nombre de la fecha, como en un sello de verdad. --}}
        <g fill="currentColor"><circle cx="9.5" cy="50" r="1.6" /><circle cx="90.5" cy="50" r="1.6" /></g>
        <g transform="translate(38 36)">
            <path d="{{ $trazos }}" fill="none" stroke="currentColor" stroke-width="2" />
            @foreach ($cabezas as [$cx, $cy, $radio])
                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $radio }}" fill="currentColor" />
            @endforeach
        </g>
        @if ($total > 1)
            @for ($k = 0; $k < $escalon; $k++)
                <circle cx="{{ 50 + ($k - ($escalon - 1) / 2) * 6 }}" cy="66" r="1.9" fill="currentColor" />
            @endfor
        @endif
    @else
        <circle cx="50" cy="50" r="45" fill="none" stroke="currentColor" stroke-width="1.4" stroke-dasharray="3.5 4" />
        @if ($secreto)
            <text x="50" y="58" text-anchor="middle" class="sello-letra" font-size="22" fill="currentColor">?</text>
        @else
            <g transform="translate(38 38)" opacity=".55">
                <path d="{{ $trazos }}" fill="none" stroke="currentColor" stroke-width="2" />
                @foreach ($cabezas as [$cx, $cy, $radio])
                    <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $radio }}" fill="currentColor" />
                @endforeach
            </g>
        @endif
    @endif
</svg>
