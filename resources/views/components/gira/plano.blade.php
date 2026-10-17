@php
    /*
     | El plano de un barrio (App\View\Components\Gira\Plano). Cada parada es un botón: tocarla muestra su ficha
     | (la página guarda cuál está elegida en "elegida"). Los colores son los de una guía impresa y salen de las
     | variables --pl-* (app.css): de día, papel crema; de noche, la misma guía en tinta oscura.
     |
     | Lo que va dentro del marco se recorta contra él: el río, las diagonales y los edificios pueden pasarse.
     */
    $o = $orientacion;
    [$mx, $my, $mw, $mh] = $g['marco'];
    $plaza = $g['plaza'];
    $adoquines = $g['adoquines'];
    $media = $g['calle'] / 2;
    $quitar = array_map(fn (array $m) => $m[0].'-'.$m[1], $g['quitar']);
    $esPlaza = fn (int $col, int $fila) => $plaza !== null && $plaza[0] === $col && $plaza[1] === $fila;
    [$bx, $by, $bw, $bh] = $g['bar'];
    $jefe = $barrio['jefe'];
    $palo = $carta === null ? null : explode('-', $carta)[1];
    $numeroDeCarta = $carta === null ? null : explode('-', $carta)[0];
    $hastaCostado = $g['costadoHasta'] ?? $my + $mh;
@endphp
<svg viewBox="0 0 {{ $g['ancho'] }} {{ $g['alto'] }}" {{ $attributes->class('block w-full plano') }} role="group" aria-label="Plano de {{ $barrio['nombre'] }}"
    data-barrio="{{ $barrio['clave'] }}" data-sigue="{{ $sigue ?? 'completo' }}" data-tramo="{{ $tramo }}">
    <defs>
        <clipPath id="marco-{{ $o }}">
            <rect x="{{ $mx }}" y="{{ $my }}" width="{{ $mw }}" height="{{ $mh }}" />
        </clipPath>
        <pattern id="adoquines-{{ $o }}" width="7" height="10" patternUnits="userSpaceOnUse">
            <rect width="7" height="10" class="pl-adoquin-fondo" />
            <rect x="0.6" y="0.6" width="2.6" height="8.8" rx="1.2" class="pl-adoquin" stroke-width="0.4" />
            <rect x="3.8" y="-4.4" width="2.6" height="8.8" rx="1.2" class="pl-adoquin" stroke-width="0.4" />
            <rect x="3.8" y="5.6" width="2.6" height="8.8" rx="1.2" class="pl-adoquin" stroke-width="0.4" />
        </pattern>
        <pattern id="toldo-{{ $o }}" width="8" height="10" patternUnits="userSpaceOnUse">
            <rect width="4" height="10" class="pl-ladrillo" />
            <rect x="4" width="4" height="10" class="pl-toldo" />
        </pattern>
    </defs>

    {{-- El papel --}}
    <rect width="{{ $g['ancho'] }}" height="{{ $g['alto'] }}" class="pl-papel" />

    <g clip-path="url(#marco-{{ $o }})">
        <rect x="{{ $mx }}" y="{{ $my }}" width="{{ $mw }}" height="{{ $mh }}" class="pl-calle" />

        {{-- El río, si el barrio termina en el Riachuelo: el agua con sus ondas y la orilla --}}
        @if ($g['rio'] !== null)
            <rect x="{{ $mx }}" y="{{ $g['rio'] }}" width="{{ $mw }}" height="{{ $my + $mh - $g['rio'] }}" class="pl-agua" />
            <line x1="{{ $mx }}" y1="{{ $g['rio'] }}" x2="{{ $mx + $mw }}" y2="{{ $g['rio'] }}" class="pl-orilla" stroke-width="1.4" />
            @for ($k = 0; $k * 22 < $my + $mh - $g['rio'] - 10; $k++)
                @php($oy = $g['rio'] + 12 + $k * 22)
                <path d="M{{ $mx + ($k % 2) * 30 }} {{ $oy }} @for ($s = 0; $s < $mw / 60 + 1; $s++) q15 -4 30 0 q15 4 30 0 @endfor" fill="none" class="pl-onda" stroke-width="0.9" />
            @endfor
        @endif

        {{-- La avenida de abajo y la del costado, con su raya al medio --}}
        @if ($g['avenida'] !== null)
            <rect x="{{ $mx }}" y="{{ $g['avenida'] - 13 }}" width="{{ $mw }}" height="26" class="pl-avenida" />
            <line x1="{{ $mx }}" y1="{{ $g['avenida'] }}" x2="{{ $mx + $mw }}" y2="{{ $g['avenida'] }}" class="pl-raya" stroke-width="1.2" stroke-dasharray="8 7" />
        @endif
        <rect x="{{ $g['costado'] - 13 }}" y="{{ $my }}" width="26" height="{{ $hastaCostado - $my }}" class="pl-avenida" />
        <line x1="{{ $g['costado'] }}" y1="{{ $my }}" x2="{{ $g['costado'] }}" y2="{{ $hastaCostado }}" class="pl-raya" stroke-width="1.2" stroke-dasharray="8 7" />

        {{-- Una calle de adoquines, si el barrio la tiene --}}
        @if ($adoquines !== null && isset($g['verticales'][$adoquines]))
            @php($ax = $g['verticales'][$adoquines] - $media)
            <rect x="{{ $ax }}" y="{{ $my }}" width="{{ $g['calle'] }}" height="{{ $mh }}" fill="url(#adoquines-{{ $o }})" />
            <line x1="{{ $ax }}" y1="{{ $my }}" x2="{{ $ax }}" y2="{{ $my + $mh }}" class="pl-borde" stroke-width="0.8" />
            <line x1="{{ $ax + $g['calle'] }}" y1="{{ $my }}" x2="{{ $ax + $g['calle'] }}" y2="{{ $my + $mh }}" class="pl-borde" stroke-width="0.8" />
        @endif

        {{-- Las manzanas, con la división de los lotes como en el plano catastral; una puede ser la plaza --}}
        @foreach ($g['columnas'] as $col => [$x, $ancho])
            @foreach ($g['filas'] as $fila => [$y, $alto])
                @if (in_array($col.'-'.$fila, $quitar, true))
                    @continue
                @elseif ($esPlaza($col, $fila))
                    <rect x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" rx="3" class="pl-plaza" stroke-width="1" />
                    <path d="M{{ $x + 4 }} {{ $y + 4 }} L{{ $x + $ancho - 4 }} {{ $y + $alto - 4 }} M{{ $x + $ancho - 4 }} {{ $y + 4 }} L{{ $x + 4 }} {{ $y + $alto - 4 }}" class="pl-sendero" stroke-width="5" />
                    <circle cx="{{ $x + $ancho / 2 }}" cy="{{ $y + $alto / 2 }}" r="11" class="pl-calle" />
                    @foreach ([[0.14, 0.2], [0.86, 0.2], [0.14, 0.8], [0.86, 0.8], [0.5, 0.1], [0.5, 0.9]] as [$px, $py])
                        <circle cx="{{ $x + $ancho * $px }}" cy="{{ $y + $alto * $py }}" r="5" class="pl-arbol" stroke-width="0.7" />
                    @endforeach
                    <g transform="translate({{ $x + $ancho / 2 }} {{ $y + $alto / 2 }})" class="pl-trazo" stroke-width="1.2" fill="none">
                        <line x1="0" y1="7" x2="0" y2="-6" />
                        <path d="M-4 -6 h8 l-2 -5 h-4 z" class="pl-luz" />
                    </g>
                @else
                    <rect x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" rx="3" class="pl-manzana" stroke-width="1" />
                    <g class="pl-lote" stroke-width="0.6">
                        @foreach ([0.25, 0.5, 0.75] as $p)
                            <line x1="{{ $x + $ancho * $p }}" y1="{{ $y }}" x2="{{ $x + $ancho * $p }}" y2="{{ $y + 15 }}" />
                            <line x1="{{ $x + $ancho * $p }}" y1="{{ $y + $alto - 15 }}" x2="{{ $x + $ancho * $p }}" y2="{{ $y + $alto }}" />
                        @endforeach
                    </g>
                @endif
            @endforeach
        @endforeach
        @foreach ($g['bordes'] as [$x, $ancho])
            @foreach ($g['filas'] as [$y, $alto])
                <rect x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" class="pl-manzana" stroke-width="1" />
            @endforeach
        @endforeach
        @if ($g['abajo'] !== null)
            @foreach ($g['columnas'] as [$x, $ancho])
                <rect x="{{ $x }}" y="{{ $g['abajo'][0] }}" width="{{ $ancho }}" height="{{ $g['abajo'][1] }}" rx="3" class="pl-manzana" stroke-width="1" />
            @endforeach
        @endif

        {{-- Las calles anchas (Lisandro de la Torre, Corrientes) pasan por encima del borde de las manzanas --}}
        @foreach ($g['anchas'] as [$eje, $donde, $ancho])
            @if ($eje === 'v')
                <rect x="{{ $donde - $ancho / 2 }}" y="{{ $my }}" width="{{ $ancho }}" height="{{ $hastaCostado - $my }}" class="pl-avenida" />
                <line x1="{{ $donde }}" y1="{{ $my }}" x2="{{ $donde }}" y2="{{ $hastaCostado }}" class="pl-raya" stroke-width="1.2" stroke-dasharray="8 7" />
            @else
                <rect x="{{ $mx }}" y="{{ $donde - $ancho / 2 }}" width="{{ $mw }}" height="{{ $ancho }}" class="pl-avenida" />
                <line x1="{{ $mx }}" y1="{{ $donde }}" x2="{{ $mx + $mw }}" y2="{{ $donde }}" class="pl-raya" stroke-width="1.2" stroke-dasharray="8 7" />
            @endif
        @endforeach

        {{-- La diagonal (Caminito, Av. Sáenz): corta las manzanas como en el plano de verdad --}}
        @if ($g['diagonal'] !== null)
            @php([$dx1, $dy1, $dx2, $dy2, $dw] = $g['diagonal'])
            <line x1="{{ $dx1 }}" y1="{{ $dy1 }}" x2="{{ $dx2 }}" y2="{{ $dy2 }}" class="pl-borde" stroke-width="{{ $dw + 2 }}" />
            <line x1="{{ $dx1 }}" y1="{{ $dy1 }}" x2="{{ $dx2 }}" y2="{{ $dy2 }}" class="{{ $dw >= 18 ? 'pl-avenida-trazo' : 'pl-calle-trazo' }}" stroke-width="{{ $dw }}" />
            @if ($dw >= 18)
                <line x1="{{ $dx1 }}" y1="{{ $dy1 }}" x2="{{ $dx2 }}" y2="{{ $dy2 }}" class="pl-raya" stroke-width="1.2" stroke-dasharray="8 7" />
            @endif
        @endif

        {{-- Lo propio del barrio, dibujado en elevación --}}
        @foreach ($g['edificios'] as $edificio)
            <x-gira.edificio :de="$edificio" :o="$o" />
        @endforeach
    </g>

    {{-- Los nombres de las calles (y del agua) --}}
    @foreach ($g['nombres'] as $nombre)
        <text @class(['plano-calle', 'plano-agua' => ($nombre[4] ?? null) === 'agua']) transform="translate({{ $nombre[1] }} {{ $nombre[2] }}){{ $nombre[3] ? ' rotate('.$nombre[3].')' : '' }}">{{ $nombre[0] }}</text>
    @endforeach

    {{-- El bar del jefe, en la esquina: tocarlo muestra su cartel --}}
    <g class="parada parada-bar" tabindex="0" role="button" aria-label="{{ $jefe['bar'] }}: {{ $jefe['nombre'] }}, el jefe del barrio"
        @click="elegir({{ count($paradas) }})" @keydown.enter.prevent="elegir({{ count($paradas) }})" @keydown.space.prevent="elegir({{ count($paradas) }})"
        :aria-pressed="(elegida === {{ count($paradas) }}).toString()">
        <g transform="translate({{ $bx }} {{ $by }})" class="pl-trazo" stroke-width="1.2" stroke-linejoin="round">
            <rect x="0" y="0" width="{{ $bw }}" height="{{ $bh }}" class="pl-ladrillo" />
            <rect x="5" y="5" width="{{ $bw - 10 }}" height="15" class="pl-toldo" />
            <text x="{{ $bw / 2 }}" y="16" text-anchor="middle" class="plano-cartel" stroke="none" textLength="{{ $bw - 16 }}" lengthAdjust="spacingAndGlyphs">{{ $jefe['bar'] }}</text>
            <path d="M-3 22 h{{ $bw + 6 }} v8 @for ($k = 0; $k < 8; $k++) q-{{ ($bw + 6) / 16 }} 6 -{{ ($bw + 6) / 8 }} 0 @endfor z" fill="url(#toldo-{{ $o }})" />
            <rect x="{{ $bw * 0.1 }}" y="{{ $bh - 22 }}" width="{{ $bw * 0.23 }}" height="22" class="pl-vidriera" />
            <rect x="{{ $bw * 0.67 }}" y="{{ $bh - 22 }}" width="{{ $bw * 0.23 }}" height="22" class="pl-vidriera" />
            <rect x="{{ $bw * 0.41 }}" y="{{ $bh - 24 }}" width="{{ $bw * 0.19 }}" height="24" class="pl-puerta" />
            <rect class="parada-anillo pl-sin-relleno" x="-6" y="-6" width="{{ $bw + 12 }}" height="{{ $bh + 12 }}" rx="4" stroke-width="2" stroke-dasharray="5 4" />
        </g>
    </g>

    {{-- El recorrido: lo hecho es un fósforo; lo que falta, una línea de puntos. El último tramo hecho va aparte. --}}
    @if (str_contains($falta, ' '))
        <polyline points="{{ $falta }}" fill="none" class="pl-trazo" stroke-width="1.8" stroke-dasharray="0.1 7" stroke-linecap="round" />
    @endif
    @foreach (array_filter([$hecho, $tramo], fn ($puntos) => str_contains($puntos, ' ')) as $i => $puntos)
        <g @class(['plano-tramo' => $i === 1]) data-tramo-dibujo="{{ $i === 1 ? 'ultimo' : 'antes' }}">
            <polyline points="{{ $puntos }}" fill="none" class="pl-contorno" stroke-width="8.5" stroke-linecap="round" stroke-linejoin="round" />
            <polyline points="{{ $puntos }}" fill="none" class="pl-fosforo" stroke-width="5.5" stroke-linecap="round" stroke-linejoin="round" />
        </g>
    @endforeach

    {{-- Las paradas: chapas redondas. Pasadas en tinta con sus fósforos; la que sigue, con el borde encendido --}}
    @foreach ($paradas as $p)
        <g class="parada" data-estado="{{ $p['estado'] }}" data-parada="{{ $p['numero'] }}" tabindex="0" role="button"
            aria-label="Parada {{ $p['numero'] }}: {{ $p['titulo'] }}{{ match ($p['estado']) { 'pasada' => ', pasada con '.$p['fosforos'].' de 3 fósforos', 'sigue' => ', la que sigue', default => ', cerrada' } }}"
            @click="elegir({{ $p['numero'] }})" @keydown.enter.prevent="elegir({{ $p['numero'] }})" @keydown.space.prevent="elegir({{ $p['numero'] }})"
            :aria-pressed="(elegida === {{ $p['numero'] }}).toString()">
            <circle class="parada-anillo pl-sin-relleno" cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="19" stroke-width="2" stroke-dasharray="4 3" />
            @if ($p['estado'] === 'pasada')
                <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="12" class="pl-chapa-pasada" />
                <text x="{{ $p['x'] }}" y="{{ $p['y'] + 4.6 }}" text-anchor="middle" class="plano-numero pl-numero-pasada">{{ $p['jefe'] ? 'J' : $p['numero'] }}</text>
                @for ($i = 0; $i < 3; $i++)
                    <g transform="translate({{ $p['x'] + 15 + $i * 6 }} {{ $p['y'] - 15 }}) rotate(12)" opacity="{{ $i < $p['fosforos'] ? 1 : 0.28 }}">
                        <rect x="-1.2" y="0" width="2.4" height="12" class="pl-fosforo-palito" stroke-width="0.6" />
                        <circle cx="0" cy="0" r="2.3" class="pl-fosforo-cabeza" stroke-width="0.5" />
                    </g>
                @endfor
            @elseif ($p['estado'] === 'sigue')
                <g class="parada-cara">
                    <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="14" class="pl-chapa-sigue" stroke-width="3" />
                    <text x="{{ $p['x'] }}" y="{{ $p['y'] + 4.6 }}" text-anchor="middle" class="plano-numero pl-numero">{{ $p['jefe'] ? 'J' : $p['numero'] }}</text>
                </g>
                @if ($carta !== null)
                    {{-- Tu carta, parada donde vas. Va en su propio grupo: es la que camina hasta acá al volver. --}}
                    <g class="plano-marcador" aria-hidden="true">
                        <g transform="translate({{ $p['x'] + 9 }} {{ $p['y'] - 44 }}) rotate(8)">
                            <rect width="20" height="31" rx="2.5" class="pl-carta" stroke-width="1.2" />
                            <text x="3" y="8.5" class="plano-carta">{{ $numeroDeCarta }}</text>
                            <use href="#palo-{{ $palo }}" x="5" y="11" width="10" height="15" class="tinta-{{ $palo }}" />
                        </g>
                    </g>
                @endif
            @else
                <g opacity="0.6">
                    <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="12" class="pl-chapa-cerrada" stroke-width="1.4" />
                    <text x="{{ $p['x'] }}" y="{{ $p['y'] + 4.6 }}" text-anchor="middle" class="plano-numero pl-numero">{{ $p['jefe'] ? 'J' : $p['numero'] }}</text>
                </g>
            @endif
        </g>
    @endforeach

    {{-- El norte, con un fósforo: la cabeza apunta arriba --}}
    <g transform="translate({{ $g['norte'][0] }} {{ $g['norte'][1] }})" aria-hidden="true">
        <text x="0" y="-12" text-anchor="middle" class="plano-numero pl-numero" style="font-size: 10px">N</text>
        <rect x="-1.6" y="-4" width="3.2" height="24" class="pl-fosforo-palito" stroke-width="0.8" />
        <circle cx="0" cy="-4" r="3.6" class="pl-fosforo-cabeza" stroke-width="0.8" />
    </g>

    {{-- El doblez de la guía y su marco, con la grilla en el margen --}}
    <g opacity="0.55" aria-hidden="true">
        <line x1="{{ $g['ancho'] / 2 }}" y1="{{ $my }}" x2="{{ $g['ancho'] / 2 }}" y2="{{ $my + $mh }}" class="pl-doblez" stroke-width="1.4" />
        <line x1="{{ $g['ancho'] / 2 + 1.4 }}" y1="{{ $my }}" x2="{{ $g['ancho'] / 2 + 1.4 }}" y2="{{ $my + $mh }}" class="pl-doblez-luz" stroke-width="1.2" />
        <line x1="{{ $mx }}" y1="{{ $my + $mh / 2 }}" x2="{{ $mx + $mw }}" y2="{{ $my + $mh / 2 }}" class="pl-doblez" stroke-width="1.4" />
        <line x1="{{ $mx }}" y1="{{ $my + $mh / 2 + 1.4 }}" x2="{{ $mx + $mw }}" y2="{{ $my + $mh / 2 + 1.4 }}" class="pl-doblez-luz" stroke-width="1.2" />
    </g>
    <rect x="{{ $mx }}" y="{{ $my }}" width="{{ $mw }}" height="{{ $mh }}" class="pl-sin-relleno" stroke-width="1.6" />
    <rect x="{{ $mx - 5 }}" y="{{ $my - 5 }}" width="{{ $mw + 10 }}" height="{{ $mh + 10 }}" class="pl-sin-relleno" stroke-width="0.7" />
    @foreach ($g['letras'] as $i => $letra)
        <text x="{{ $g['columnas'][$i][0] + $g['columnas'][$i][1] / 2 }}" y="{{ $my - 9 }}" text-anchor="middle" class="plano-margen">{{ $letra }}</text>
    @endforeach
    @for ($i = 0; $i < $g['numeros']; $i++)
        <text x="{{ $mx - 13 }}" y="{{ ($g['filas'][$i][0] ?? $g['abajo'][0] ?? $my + $mh - 20) + ($g['filas'][$i][1] ?? $g['abajo'][1] ?? 0) / 2 + 3 }}" text-anchor="middle" class="plano-margen">{{ $i + 1 }}</text>
    @endfor
    <g transform="translate({{ $g['ancho'] - 138 }} {{ $g['alto'] - 11 }})" aria-hidden="true">
        <rect x="0" y="-2" width="92" height="4" class="pl-fosforo-palito" stroke-width="0.7" />
        <circle cx="92" cy="0" r="3.4" class="pl-fosforo-cabeza" stroke-width="0.7" />
        <text x="-6" y="3.5" text-anchor="end" class="plano-margen">Una cuadra</text>
    </g>
    <text x="{{ $mx }}" y="{{ $g['alto'] - 8 }}" class="plano-margen">Guía de la gira, página {{ array_search($barrio['clave'], array_column(\App\Juego\Gira::barrios(), 'clave')) + 1 }}</text>
</svg>
