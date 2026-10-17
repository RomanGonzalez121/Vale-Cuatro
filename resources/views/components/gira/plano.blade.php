@php
    /*
     | El plano de un barrio (App\View\Components\Gira\Plano). Cada parada es un botón: tocarla muestra su ficha
     | (la página guarda cuál está elegida en "elegida"). Los colores son los de una guía impresa, fijos: el
     | plano no cambia con el modo de día y de noche, como un papel.
     */
    $o = $orientacion;
    $tinta = '#161a18';
    $papel = '#f2e8cf';
    $calle = '#faf4e4';
    $manzana = '#e7d3a1';
    $borde = '#c4a96f';
    [$mx, $my, $mw, $mh] = $g['marco'];
    $c = $barrio['calles'];
    $plaza = $barrio['plaza'] ?? null;
    $adoquines = $barrio['adoquines'] ?? null;
    $media = $g['calle'] / 2;
    $esPlaza = fn (int $col, int $fila) => $plaza !== null && $plaza[0] === $col && $plaza[1] === $fila;
    [$bx, $by, $bw, $bh] = $g['bar'];
    $jefe = $barrio['jefe'];
    $palo = $carta === null ? null : explode('-', $carta)[1];
    $numeroDeCarta = $carta === null ? null : explode('-', $carta)[0];
@endphp
<svg viewBox="0 0 {{ $g['ancho'] }} {{ $g['alto'] }}" {{ $attributes->class('block w-full plano') }} role="group" aria-label="Plano de {{ $barrio['nombre'] }}">
    <defs>
        <pattern id="adoquines-{{ $o }}" width="7" height="10" patternUnits="userSpaceOnUse">
            <rect width="7" height="10" fill="#dccb9f" />
            <rect x="0.6" y="0.6" width="2.6" height="8.8" rx="1.2" fill="#e9dbb3" stroke="#b9a273" stroke-width="0.4" />
            <rect x="3.8" y="-4.4" width="2.6" height="8.8" rx="1.2" fill="#e9dbb3" stroke="#b9a273" stroke-width="0.4" />
            <rect x="3.8" y="5.6" width="2.6" height="8.8" rx="1.2" fill="#e9dbb3" stroke="#b9a273" stroke-width="0.4" />
        </pattern>
        <pattern id="toldo-{{ $o }}" width="8" height="10" patternUnits="userSpaceOnUse">
            <rect width="4" height="10" fill="#7a2e26" />
            <rect x="4" width="4" height="10" fill="#f3e6c8" />
        </pattern>
    </defs>

    {{-- El papel y las calles --}}
    <rect width="{{ $g['ancho'] }}" height="{{ $g['alto'] }}" fill="{{ $papel }}" />
    <rect x="{{ $mx }}" y="{{ $my }}" width="{{ $mw }}" height="{{ $mh }}" fill="{{ $calle }}" />

    {{-- La avenida de abajo y la del costado, con su raya al medio --}}
    <rect x="{{ $mx }}" y="{{ $g['avenida'] - 13 }}" width="{{ $mw }}" height="26" fill="#efd593" />
    <line x1="{{ $mx }}" y1="{{ $g['avenida'] }}" x2="{{ $mx + $mw }}" y2="{{ $g['avenida'] }}" stroke="#d2b464" stroke-width="1.2" stroke-dasharray="8 7" />
    <rect x="{{ $g['costado'] - 13 }}" y="{{ $my }}" width="26" height="{{ $mh }}" fill="#efd593" />
    <line x1="{{ $g['costado'] }}" y1="{{ $my }}" x2="{{ $g['costado'] }}" y2="{{ $my + $mh }}" stroke="#d2b464" stroke-width="1.2" stroke-dasharray="8 7" />

    {{-- Una calle de adoquines, si el barrio la tiene --}}
    @if ($adoquines !== null && isset($g['verticales'][$adoquines]))
        @php($ax = $g['verticales'][$adoquines] - $media)
        <rect x="{{ $ax }}" y="{{ $my }}" width="{{ $g['calle'] }}" height="{{ $mh }}" fill="url(#adoquines-{{ $o }})" />
        <line x1="{{ $ax }}" y1="{{ $my }}" x2="{{ $ax }}" y2="{{ $my + $mh }}" stroke="{{ $borde }}" stroke-width="0.8" />
        <line x1="{{ $ax + $g['calle'] }}" y1="{{ $my }}" x2="{{ $ax + $g['calle'] }}" y2="{{ $my + $mh }}" stroke="{{ $borde }}" stroke-width="0.8" />
    @endif

    {{-- Las manzanas, con la división de los lotes como en el plano catastral; una es la plaza --}}
    @foreach ($g['columnas'] as $col => [$x, $ancho])
        @foreach ($g['filas'] as $fila => [$y, $alto])
            @if ($esPlaza($col, $fila))
                <rect x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" rx="3" fill="#c3cd9b" stroke="#98a46b" stroke-width="1" />
                <path d="M{{ $x + 4 }} {{ $y + 4 }} L{{ $x + $ancho - 4 }} {{ $y + $alto - 4 }} M{{ $x + $ancho - 4 }} {{ $y + 4 }} L{{ $x + 4 }} {{ $y + $alto - 4 }}" stroke="{{ $calle }}" stroke-width="5" />
                <circle cx="{{ $x + $ancho / 2 }}" cy="{{ $y + $alto / 2 }}" r="11" fill="{{ $calle }}" />
                @foreach ([[0.14, 0.2], [0.86, 0.2], [0.14, 0.8], [0.86, 0.8], [0.5, 0.1], [0.5, 0.9]] as [$px, $py])
                    <circle cx="{{ $x + $ancho * $px }}" cy="{{ $y + $alto * $py }}" r="5" fill="#8f9f62" stroke="{{ $tinta }}" stroke-width="0.7" />
                @endforeach
                <g transform="translate({{ $x + $ancho / 2 }} {{ $y + $alto / 2 }})" stroke="{{ $tinta }}" stroke-width="1.2" fill="none">
                    <line x1="0" y1="7" x2="0" y2="-6" />
                    <path d="M-4 -6 h8 l-2 -5 h-4 z" fill="#f0b429" />
                </g>
            @else
                <rect x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" rx="3" fill="{{ $manzana }}" stroke="{{ $borde }}" stroke-width="1" />
                <g stroke="{{ $borde }}" stroke-width="0.6" opacity="0.8">
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
            <rect x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" fill="{{ $manzana }}" stroke="{{ $borde }}" stroke-width="1" />
        @endforeach
    @endforeach
    @foreach ($g['columnas'] as [$x, $ancho])
        <rect x="{{ $x }}" y="{{ $g['abajo'][0] }}" width="{{ $ancho }}" height="{{ $g['abajo'][1] }}" rx="3" fill="{{ $manzana }}" stroke="{{ $borde }}" stroke-width="1" />
    @endforeach

    {{-- Lo propio de San Telmo: el mercado con su nave de hierro y la iglesia de dos torres --}}
    @if ($barrio['clave'] === 'san-telmo')
        <g transform="translate({{ $g['lugares']['mercado'][0] }} {{ $g['lugares']['mercado'][1] }})" stroke="{{ $tinta }}" stroke-width="1.2" stroke-linejoin="round">
            <rect x="0" y="22" width="74" height="32" fill="#f3e9cf" />
            <path d="M-4 22 L37 2 L78 22 Z" fill="#5f7a6a" />
            @foreach ([10, 26, 42, 58] as $x)
                <path d="M{{ $x }} 54 v-14 a3 3 0 0 1 6 0 v14" fill="#d9c597" />
            @endforeach
        </g>
        <g transform="translate({{ $g['lugares']['iglesia'][0] }} {{ $g['lugares']['iglesia'][1] }})" stroke="{{ $tinta }}" stroke-width="1.2" stroke-linejoin="round">
            <rect x="8" y="22" width="40" height="34" fill="#f3e9cf" />
            <path d="M14 22 q14 -20 28 0 z" fill="#d9c597" />
            <rect x="0" y="14" width="10" height="42" fill="#f3e9cf" />
            <rect x="46" y="14" width="10" height="42" fill="#f3e9cf" />
            <path d="M0 14 l5 -8 l5 8 M46 14 l5 -8 l5 8" fill="#d9c597" />
            <path d="M22 56 v-12 a6 6 0 0 1 12 0 v12" fill="#7a2e26" />
            <circle cx="28" cy="31" r="3.5" fill="none" />
        </g>
    @endif

    {{-- Los nombres de las calles --}}
    @foreach ($g['verticales'] as $i => $x)
        @if (isset($c['verticales'][$i]))
            <text class="plano-calle" transform="translate({{ $x + 3.5 }} {{ $o === 'acostado' ? ($i === 1 ? 340 : 118) : ($i === 1 ? 404 : 104) }}) rotate(-90)">{{ $c['verticales'][$i] }}</text>
        @endif
    @endforeach
    @foreach ($g['horizontales'] as $i => $y)
        @if (isset($c['horizontales'][$i]))
            <text class="plano-calle" x="{{ $o === 'acostado' ? ($i === 0 ? 186 : 330) : 36 }}" y="{{ $y + 3.5 }}">{{ $c['horizontales'][$i] }}</text>
        @endif
    @endforeach
    <text class="plano-calle" x="{{ $o === 'acostado' ? 196 : 190 }}" y="{{ $g['avenida'] + 3.5 }}">{{ $c['avenida'] }}</text>
    <text class="plano-calle" transform="translate({{ $g['costado'] + 4 }} {{ $o === 'acostado' ? 250 : 330 }}) rotate(-90)">{{ $c['costado'] }}</text>

    {{-- El bar del jefe, en la esquina: tocarlo muestra su cartel --}}
    <g class="parada parada-bar" tabindex="0" role="button" aria-label="{{ $jefe['bar'] }}: {{ $jefe['nombre'] }}, el jefe del barrio"
        @click="elegir({{ count($paradas) }})" @keydown.enter.prevent="elegir({{ count($paradas) }})" @keydown.space.prevent="elegir({{ count($paradas) }})"
        :aria-pressed="(elegida === {{ count($paradas) }}).toString()">
        <g transform="translate({{ $bx }} {{ $by }})" stroke="{{ $tinta }}" stroke-width="1.2" stroke-linejoin="round">
            <rect x="0" y="0" width="{{ $bw }}" height="{{ $bh }}" fill="#7a2e26" />
            <rect x="5" y="5" width="{{ $bw - 10 }}" height="15" fill="#f3e6c8" />
            <text x="{{ $bw / 2 }}" y="16" text-anchor="middle" class="plano-cartel" stroke="none" textLength="{{ $bw - 16 }}" lengthAdjust="spacingAndGlyphs">{{ $jefe['bar'] }}</text>
            <path d="M-3 22 h{{ $bw + 6 }} v8 @for ($k = 0; $k < 8; $k++) q-{{ ($bw + 6) / 16 }} 6 -{{ ($bw + 6) / 8 }} 0 @endfor z" fill="url(#toldo-{{ $o }})" />
            <rect x="{{ $bw * 0.1 }}" y="{{ $bh - 22 }}" width="{{ $bw * 0.23 }}" height="22" fill="#e8c98a" />
            <rect x="{{ $bw * 0.67 }}" y="{{ $bh - 22 }}" width="{{ $bw * 0.23 }}" height="22" fill="#e8c98a" />
            <rect x="{{ $bw * 0.41 }}" y="{{ $bh - 24 }}" width="{{ $bw * 0.19 }}" height="24" fill="#3a1714" />
            <rect class="parada-anillo" x="-6" y="-6" width="{{ $bw + 12 }}" height="{{ $bh + 12 }}" rx="4" fill="none" stroke="{{ $tinta }}" stroke-width="2" stroke-dasharray="5 4" />
        </g>
    </g>

    {{-- El recorrido: lo hecho es un fósforo; lo que falta, una línea de puntos --}}
    @if (str_contains($falta, ' '))
        <polyline points="{{ $falta }}" fill="none" stroke="{{ $tinta }}" stroke-width="1.8" stroke-dasharray="0.1 7" stroke-linecap="round" />
    @endif
    @if (str_contains($hecho, ' '))
        <polyline points="{{ $hecho }}" fill="none" stroke="{{ $tinta }}" stroke-width="8.5" stroke-linecap="round" stroke-linejoin="round" />
        <polyline points="{{ $hecho }}" fill="none" stroke="#e8c98a" stroke-width="5.5" stroke-linecap="round" stroke-linejoin="round" />
    @endif

    {{-- Las paradas: chapas redondas. Pasadas en tinta con sus fósforos; la que sigue, con el borde encendido --}}
    @foreach ($paradas as $p)
        <g class="parada" data-estado="{{ $p['estado'] }}" tabindex="0" role="button"
            aria-label="Parada {{ $p['numero'] }}: {{ $p['titulo'] }}{{ match ($p['estado']) { 'pasada' => ', pasada con '.$p['fosforos'].' de 3 fósforos', 'sigue' => ', la que sigue', default => ', cerrada' } }}"
            @click="elegir({{ $p['numero'] }})" @keydown.enter.prevent="elegir({{ $p['numero'] }})" @keydown.space.prevent="elegir({{ $p['numero'] }})"
            :aria-pressed="(elegida === {{ $p['numero'] }}).toString()">
            <circle class="parada-anillo" cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="19" fill="none" stroke="{{ $tinta }}" stroke-width="2" stroke-dasharray="4 3" />
            @if ($p['estado'] === 'pasada')
                <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="12" fill="{{ $tinta }}" />
                <text x="{{ $p['x'] }}" y="{{ $p['y'] + 4.6 }}" text-anchor="middle" class="plano-numero" fill="#fbfaf5">{{ $p['jefe'] ? 'J' : $p['numero'] }}</text>
                @for ($i = 0; $i < 3; $i++)
                    <g transform="translate({{ $p['x'] + 15 + $i * 6 }} {{ $p['y'] - 15 }}) rotate(12)" opacity="{{ $i < $p['fosforos'] ? 1 : 0.28 }}">
                        <rect x="-1.2" y="0" width="2.4" height="12" fill="#e8c98a" stroke="{{ $tinta }}" stroke-width="0.6" />
                        <circle cx="0" cy="0" r="2.3" fill="#c93327" stroke="{{ $tinta }}" stroke-width="0.5" />
                    </g>
                @endfor
            @elseif ($p['estado'] === 'sigue')
                <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="14" fill="#fbfaf5" stroke="#c93327" stroke-width="3" />
                <text x="{{ $p['x'] }}" y="{{ $p['y'] + 4.6 }}" text-anchor="middle" class="plano-numero" fill="{{ $tinta }}">{{ $p['jefe'] ? 'J' : $p['numero'] }}</text>
                @if ($carta !== null)
                    {{-- Tu carta, parada donde vas --}}
                    <g transform="translate({{ $p['x'] + 9 }} {{ $p['y'] - 44 }}) rotate(8)" aria-hidden="true">
                        <rect width="20" height="31" rx="2.5" fill="#fbfaf5" stroke="{{ $tinta }}" stroke-width="1.2" />
                        <text x="3" y="8.5" class="plano-carta">{{ $numeroDeCarta }}</text>
                        <use href="#palo-{{ $palo }}" x="5" y="11" width="10" height="15" class="tinta-{{ $palo }}" />
                    </g>
                @endif
            @else
                <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="12" fill="#fbfaf5" stroke="{{ $tinta }}" stroke-width="1.4" opacity="0.6" />
                <text x="{{ $p['x'] }}" y="{{ $p['y'] + 4.6 }}" text-anchor="middle" class="plano-numero" fill="{{ $tinta }}" opacity="0.6">{{ $p['jefe'] ? 'J' : $p['numero'] }}</text>
            @endif
        </g>
    @endforeach

    {{-- El norte, con un fósforo: la cabeza apunta arriba --}}
    <g transform="translate({{ $g['norte'][0] }} {{ $g['norte'][1] }})" aria-hidden="true">
        <text x="0" y="-12" text-anchor="middle" class="plano-numero" style="font-size: 10px">N</text>
        <rect x="-1.6" y="-4" width="3.2" height="24" fill="#e8c98a" stroke="{{ $tinta }}" stroke-width="0.8" />
        <circle cx="0" cy="-4" r="3.6" fill="#c93327" stroke="{{ $tinta }}" stroke-width="0.8" />
    </g>

    {{-- El doblez de la guía y su marco, con la grilla en el margen --}}
    <g opacity="0.55" aria-hidden="true">
        <line x1="{{ $g['ancho'] / 2 }}" y1="{{ $my }}" x2="{{ $g['ancho'] / 2 }}" y2="{{ $my + $mh }}" stroke="{{ $tinta }}" stroke-opacity="0.16" stroke-width="1.4" />
        <line x1="{{ $g['ancho'] / 2 + 1.4 }}" y1="{{ $my }}" x2="{{ $g['ancho'] / 2 + 1.4 }}" y2="{{ $my + $mh }}" stroke="#fffdf6" stroke-width="1.2" />
        <line x1="{{ $mx }}" y1="{{ $my + $mh / 2 }}" x2="{{ $mx + $mw }}" y2="{{ $my + $mh / 2 }}" stroke="{{ $tinta }}" stroke-opacity="0.16" stroke-width="1.4" />
        <line x1="{{ $mx }}" y1="{{ $my + $mh / 2 + 1.4 }}" x2="{{ $mx + $mw }}" y2="{{ $my + $mh / 2 + 1.4 }}" stroke="#fffdf6" stroke-width="1.2" />
    </g>
    <rect x="{{ $mx }}" y="{{ $my }}" width="{{ $mw }}" height="{{ $mh }}" fill="none" stroke="{{ $tinta }}" stroke-width="1.6" />
    <rect x="{{ $mx - 5 }}" y="{{ $my - 5 }}" width="{{ $mw + 10 }}" height="{{ $mh + 10 }}" fill="none" stroke="{{ $tinta }}" stroke-width="0.7" />
    @foreach ($g['letras'] as $i => $letra)
        <text x="{{ $g['columnas'][$i][0] + $g['columnas'][$i][1] / 2 }}" y="{{ $my - 9 }}" text-anchor="middle" class="plano-margen">{{ $letra }}</text>
    @endforeach
    @for ($i = 0; $i < $g['numeros']; $i++)
        <text x="{{ $mx - 13 }}" y="{{ ($g['filas'][$i][0] ?? $g['abajo'][0]) + ($g['filas'][$i][1] ?? $g['abajo'][1]) / 2 + 3 }}" text-anchor="middle" class="plano-margen">{{ $i + 1 }}</text>
    @endfor
    <g transform="translate({{ $g['ancho'] - 138 }} {{ $g['alto'] - 11 }})" aria-hidden="true">
        <rect x="0" y="-2" width="92" height="4" fill="#e8c98a" stroke="{{ $tinta }}" stroke-width="0.7" />
        <circle cx="92" cy="0" r="3.4" fill="#c93327" stroke="{{ $tinta }}" stroke-width="0.7" />
        <text x="-6" y="3.5" text-anchor="end" class="plano-margen">Una cuadra</text>
    </g>
    <text x="{{ $mx }}" y="{{ $g['alto'] - 8 }}" class="plano-margen">Guía de la gira, página {{ array_search($barrio['clave'], array_column(\App\Juego\Gira::barrios(), 'clave')) + 1 }}</text>
</svg>
