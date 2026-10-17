@props(['de', 'o'])

@php
    /*
     | Un edificio o lugar del plano de un barrio, dibujado en elevación como en una guía ilustrada: tinta plana,
     | contorno en tinta y los colores del plano (--pl-*), que cambian de día a de noche. "de" es la entrada de
     | planos-de-gira.php: el tipo, dónde va y, según el tipo, su tamaño o cuántos son.
     */
    [$tipo, $x, $y] = $de;
    $w = $de[3] ?? null;
    $h = $de[4] ?? null;
@endphp

<g transform="translate({{ $x }} {{ $y }})" class="pl-trazo" stroke-width="1.2" stroke-linejoin="round" aria-hidden="true">
    @if ($tipo === 'mercado')
        {{-- San Telmo: el mercado con su nave de hierro --}}
        <rect x="0" y="22" width="74" height="32" class="pl-fachada" />
        <path d="M-4 22 L37 2 L78 22 Z" class="pl-techo" />
        @foreach ([10, 26, 42, 58] as $ax)
            <path d="M{{ $ax }} 54 v-14 a3 3 0 0 1 6 0 v14" class="pl-fachada-2" />
        @endforeach
    @elseif ($tipo === 'iglesia')
        {{-- San Telmo: la iglesia de dos torres --}}
        <rect x="8" y="22" width="40" height="34" class="pl-fachada" />
        <path d="M14 22 q14 -20 28 0 z" class="pl-fachada-2" />
        <rect x="0" y="14" width="10" height="42" class="pl-fachada" />
        <rect x="46" y="14" width="10" height="42" class="pl-fachada" />
        <path d="M0 14 l5 -8 l5 8 M46 14 l5 -8 l5 8" class="pl-fachada-2" />
        <path d="M22 56 v-12 a6 6 0 0 1 12 0 v12" class="pl-ladrillo" />
        <circle cx="28" cy="31" r="3.5" class="pl-sin-relleno" />
    @elseif ($tipo === 'cafe')
        {{-- Boedo: el café de la esquina, con su toldo a rayas y el cartel --}}
        <rect x="0" y="10" width="56" height="38" class="pl-fachada" />
        <rect x="9" y="0" width="38" height="11" class="pl-toldo" />
        <text x="28" y="8.6" text-anchor="middle" class="plano-cartel" stroke="none">Café</text>
        <path d="M-2 14 h60 v5 @for ($k = 0; $k < 6; $k++) q-5 4 -10 0 @endfor z" fill="url(#toldo-{{ $o }})" />
        <rect x="5" y="27" width="13" height="21" class="pl-vidriera" />
        <rect x="38" y="27" width="13" height="21" class="pl-vidriera" />
        <rect x="22" y="25" width="12" height="23" class="pl-puerta" />
    @elseif ($tipo === 'cancha')
        {{-- Boedo: la cancha del barrio, con sus tribunas --}}
        <rect x="2" y="2" width="{{ $w - 4 }}" height="{{ $h - 4 }}" rx="10" class="pl-tribuna" />
        @for ($k = 1; $k < 4; $k++)
            <rect x="{{ 2 + $k * 3 }}" y="{{ 2 + $k * 3 }}" width="{{ $w - 4 - $k * 6 }}" height="{{ $h - 4 - $k * 6 }}" rx="{{ 10 - $k * 2 }}" class="pl-sin-relleno pl-escalon" stroke-width="0.6" />
        @endfor
        <rect x="14" y="14" width="{{ $w - 28 }}" height="{{ $h - 28 }}" class="pl-cesped" />
        <g class="pl-linea" stroke-width="1.1" fill="none">
            <rect x="19" y="19" width="{{ $w - 38 }}" height="{{ $h - 38 }}" />
            <line x1="19" y1="{{ $h / 2 }}" x2="{{ $w - 19 }}" y2="{{ $h / 2 }}" />
            <circle cx="{{ $w / 2 }}" cy="{{ $h / 2 }}" r="{{ min($w, $h) * 0.12 }}" />
            <rect x="{{ $w * 0.3 }}" y="19" width="{{ $w * 0.4 }}" height="{{ $h * 0.1 }}" />
            <rect x="{{ $w * 0.3 }}" y="{{ $h - 19 - $h * 0.1 }}" width="{{ $w * 0.4 }}" height="{{ $h * 0.1 }}" />
        </g>
    @elseif ($tipo === 'conventillos')
        {{-- La Boca: casas de chapa pintadas cada una de un color, como en Caminito --}}
        @for ($k = 0; $k < ($de[3] ?? 2); $k++)
            <g transform="translate({{ $k * 16 }} 0)">
                <rect x="0" y="3" width="16" height="23" class="pl-casa-{{ ($k + $x) % 4 }}" />
                <path d="M-1 4 L8 -1 L17 4" class="pl-sin-relleno" stroke-width="1.4" />
                <rect x="4" y="7" width="8" height="6" class="pl-vidriera" stroke-width="0.8" />
                <line x1="2" y1="15" x2="14" y2="15" stroke-width="0.8" />
                <rect x="5" y="16" width="6" height="10" class="pl-puerta" stroke-width="0.8" />
            </g>
        @endfor
    @elseif ($tipo === 'transbordador')
        {{-- La Boca: el puente transbordador, de hierro, con su barquilla colgada sobre el río --}}
        @foreach ([0, 50] as $tx)
            <rect x="{{ $tx }}" y="0" width="7" height="96" class="pl-hierro" />
            @for ($k = 0; $k < 8; $k++)
                <path d="M{{ $tx }} {{ 10 + $k * 11 }} l7 11 M{{ $tx + 7 }} {{ 10 + $k * 11 }} l-7 11" class="pl-reticula" stroke-width="0.6" />
            @endfor
        @endforeach
        <rect x="-5" y="0" width="67" height="10" class="pl-hierro" />
        @for ($k = 0; $k < 9; $k++)
            <path d="M{{ -5 + $k * 7.4 }} 0 l7.4 10 M{{ 2.4 + $k * 7.4 }} 0 l-7.4 10" class="pl-reticula" stroke-width="0.6" />
        @endfor
        <line x1="23" y1="10" x2="23" y2="44" stroke-width="0.8" />
        <line x1="34" y1="10" x2="34" y2="44" stroke-width="0.8" />
        <rect x="18" y="44" width="21" height="11" class="pl-fachada" />
    @elseif ($tipo === 'barco')
        {{-- Un barco amarrado en el Riachuelo --}}
        <rect x="11" y="-1" width="18" height="9" class="pl-fachada" />
        <rect x="31" y="-8" width="5" height="16" class="pl-ladrillo" />
        <path d="M0 8 h48 l-6 9 h-36 z" class="pl-casco" />
        <line x1="4" y1="12" x2="44" y2="12" class="pl-linea" stroke-width="0.8" />
    @elseif ($tipo === 'iglesia-gotica')
        {{-- Pompeya: la iglesia neogótica, de una sola torre con aguja --}}
        <rect x="4" y="44" width="64" height="40" class="pl-fachada" />
        <path d="M4 44 l8 -10 h48 l8 10 z" class="pl-techo" />
        <rect x="24" y="18" width="24" height="66" class="pl-fachada-2" />
        <path d="M24 18 L36 -10 L48 18 Z" class="pl-techo" />
        <line x1="36" y1="-10" x2="36" y2="-17" />
        <line x1="32.5" y1="-14" x2="39.5" y2="-14" />
        <circle cx="36" cy="38" r="6" class="pl-vidriera" />
        <path d="M30 84 v-14 l6 -8 l6 8 v14" class="pl-ladrillo" />
        <path d="M11 78 v-14 l3.5 -5 l3.5 5 v14 M54 78 v-14 l3.5 -5 l3.5 5 v14" class="pl-vidriera" stroke-width="0.9" />
    @elseif ($tipo === 'paredon')
        {{-- Pompeya: el paredón ("paredón y después"), de ladrillo, a lo largo de la orilla --}}
        <rect x="0" y="0" width="{{ $w }}" height="7" class="pl-ladrillo" stroke-width="0.8" />
        <line x1="0" y1="3.5" x2="{{ $w }}" y2="3.5" class="pl-junta" stroke-width="0.5" />
        @for ($k = 1; $k * 9 < $w; $k++)
            <line x1="{{ $k * 9 }}" y1="{{ $k % 2 ? 0 : 3.5 }}" x2="{{ $k * 9 }}" y2="{{ $k % 2 ? 3.5 : 7 }}" class="pl-junta" stroke-width="0.5" />
        @endfor
    @elseif ($tipo === 'puente')
        {{-- Pompeya: el Puente Alsina, la avenida que cruza el Riachuelo, con su baranda --}}
        @php($dx = $de[3] - $x)
        @php($dy = $de[4] - $y)
        <line x1="0" y1="0" x2="{{ $dx }}" y2="{{ $dy }}" stroke-width="20" stroke-linecap="butt" />
        <line x1="0" y1="0" x2="{{ $dx }}" y2="{{ $dy }}" class="pl-avenida-trazo" stroke-width="16" stroke-linecap="butt" />
        <line x1="0" y1="0" x2="{{ $dx }}" y2="{{ $dy }}" class="pl-baranda" stroke-width="19" stroke-dasharray="1.2 5" stroke-linecap="butt" />
        <line x1="0" y1="0" x2="{{ $dx }}" y2="{{ $dy }}" class="pl-avenida-trazo" stroke-width="13" stroke-linecap="butt" />
    @elseif ($tipo === 'farol')
        {{-- Un farol de esquina: el del Café del Farol Roto --}}
        <line x1="0" y1="0" x2="0" y2="28" stroke-width="1.6" />
        <path d="M-5 0 h10 l-2 -7 h-6 z" class="pl-luz" />
        <line x1="-3" y1="28" x2="3" y2="28" stroke-width="1.6" />
    @elseif ($tipo === 'recova')
        {{-- Mataderos: la recova en "U" con El Resero en la plazoleta del medio --}}
        @php($ala = 16)
        <rect x="{{ $ala }}" y="{{ $ala }}" width="{{ $w - 2 * $ala }}" height="{{ $h - $ala }}" class="pl-plazoleta" stroke="none" />
        <path d="M0 0 H{{ $w }} V{{ $h }} H{{ $w - $ala }} V{{ $ala }} H{{ $ala }} V{{ $h }} H0 Z" class="pl-fachada" />
        @for ($k = 0; ($k + 1) * 9 <= $w - 2 * $ala; $k++)
            <path d="M{{ $ala + 1 + $k * 9 }} {{ $ala }} v-5 a3.5 3.5 0 0 1 7 0 v5" class="pl-arco" stroke-width="0.8" />
        @endfor
        @for ($k = 0; ($k + 1) * 9 <= $h - $ala; $k++)
            <path d="M{{ $ala }} {{ $ala + 1 + $k * 9 }} h-5 a3.5 3.5 0 0 0 0 7 h5 M{{ $w - $ala }} {{ $ala + 1 + $k * 9 }} h5 a3.5 3.5 0 0 1 0 7 h-5" class="pl-arco" stroke-width="0.8" />
        @endfor
        <g transform="translate({{ $w / 2 }} {{ $ala + ($h - $ala) * 0.58 }})">
            <rect x="-7" y="6" width="14" height="7" class="pl-fachada-2" />
            <path d="M-9 4 q1 -7 8 -6 h5 l3 -4 l2 1 l-1 5 q1 4 -2 4 z" class="pl-bronce" stroke-width="0.9" />
            <path d="M-7 4 v3 M-3 4 v3 M3 4 v3 M6 4 v3" stroke-width="1" />
            <circle cx="-1" cy="-7" r="2" class="pl-bronce" stroke-width="0.8" />
            <line x1="-1" y1="-5" x2="-1" y2="-1" stroke-width="1.4" />
        </g>
    @elseif ($tipo === 'corrales')
        {{-- Mataderos: los corrales de la hacienda, divididos por cercos de madera --}}
        <rect x="0" y="0" width="{{ $w }}" height="{{ $h }}" rx="3" class="pl-tierra" />
        <g class="pl-cerco" stroke-width="1.3" fill="none">
            @foreach ([1, 2] as $k)
                <line x1="{{ $w * $k / 3 }}" y1="3" x2="{{ $w * $k / 3 }}" y2="{{ $h - 3 }}" />
            @endforeach
            @foreach ([1, 2, 3] as $k)
                <line x1="3" y1="{{ $h * $k / 4 }}" x2="{{ $w - 3 }}" y2="{{ $h * $k / 4 }}" />
            @endforeach
            <rect x="3" y="3" width="{{ $w - 6 }}" height="{{ $h - 6 }}" />
        </g>
        @foreach ([0, 1, 2, 3] as $c)
            @foreach ([0, 1, 2, 3, 4] as $f)
                <circle cx="{{ 3 + ($w - 6) * $c / 3 }}" cy="{{ 3 + ($h - 6) * $f / 4 }}" r="1.6" class="pl-cerco-poste" stroke="none" />
            @endforeach
        @endforeach
        <path d="M{{ $w / 3 + 4 }} {{ $h - 3 }} l{{ $w / 3 - 8 }} -8 M{{ $w / 3 + 4 }} {{ $h - 11 }} l{{ $w / 3 - 8 }} 8" class="pl-cerco" stroke-width="1.1" />
    @elseif ($tipo === 'mercado-abasto')
        {{-- Abasto: el mercado Art Déco, con su corona escalonada y los grandes arcos sobre Corrientes --}}
        <rect x="0" y="{{ $h * 0.2 }}" width="{{ $w }}" height="{{ $h * 0.8 }}" rx="9" class="pl-fachada" />
        <path d="M{{ $w * 0.34 }} {{ $h * 0.2 }} V{{ $h * 0.1 }} H{{ $w * 0.39 }} V0 H{{ $w * 0.61 }} V{{ $h * 0.1 }} H{{ $w * 0.66 }} V{{ $h * 0.2 }} Z" class="pl-fachada-2" />
        <text x="{{ $w / 2 }}" y="{{ $h * 0.16 }}" text-anchor="middle" class="plano-cartel" stroke="none">Mercado</text>
        @foreach ([0.14, 0.32, 0.5, 0.68, 0.86] as $cx)
            @php($aw = $w * 0.11)
            <path d="M{{ $w * $cx - $aw / 2 }} {{ $h }} V{{ $h * 0.5 }} a{{ $aw / 2 }} {{ $aw / 2 }} 0 0 1 {{ $aw }} 0 V{{ $h }}" class="pl-arco" />
            <line x1="{{ $w * $cx }}" y1="{{ $h * 0.5 - $aw / 2 }}" x2="{{ $w * $cx }}" y2="{{ $h }}" class="pl-reticula" stroke-width="0.6" />
        @endforeach
        @foreach ([0.23, 0.41, 0.59, 0.77] as $px)
            <line x1="{{ $w * $px }}" y1="{{ $h * 0.24 }}" x2="{{ $w * $px }}" y2="{{ $h }}" stroke-width="0.7" />
        @endforeach
    @elseif ($tipo === 'cajones')
        {{-- Abasto: cajones de fruta apilados en la vereda --}}
        @foreach ([[0, 12], [24, 12], [12, 0]] as [$cx, $cy])
            <g transform="translate({{ $cx }} {{ $cy }})">
                <rect x="0" y="0" width="22" height="12" class="pl-madera" />
                <line x1="0" y1="6" x2="22" y2="6" stroke-width="0.6" />
                @foreach ([4, 10, 16] as $fx)
                    <circle cx="{{ $fx + 1 }}" cy="-1.5" r="2.6" class="pl-fruta" stroke-width="0.6" />
                @endforeach
            </g>
        @endforeach
    @endif
</g>
