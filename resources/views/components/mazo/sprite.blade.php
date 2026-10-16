{{--
    Piezas del mazo que se dibujan una sola vez por página y se reutilizan con <use>:
    los cuatro palos, las tres figuras, los dorsos y el isotipo de fósforos.
--}}
@php
    // Solo los dorsos que usa la página (App\View\DorsosEnUso).
    $dorsos = app(\App\View\DorsosEnUso::class);
@endphp

<svg width="0" height="0" class="absolute" aria-hidden="true" focusable="false">
    <defs>
        <pattern id="trama-dorso" width="9" height="9" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
            <path d="M0 0H9M0 0V9" stroke="var(--color-tinta)" stroke-width="1.6" />
        </pattern>

        {{-- El dorso "Fósforos": dos fósforos por baldosa, uno cabeza arriba y otro cabeza abajo. Palito en Tinta y cabeza en Copa. --}}
        <pattern id="trama-fosforos" x="8" y="8" width="14" height="35" patternUnits="userSpaceOnUse">
            <path d="M3.5 7V30M10.5 5V28" stroke="var(--color-tinta)" stroke-width="2" stroke-linecap="round" />
            <circle cx="3.5" cy="5.5" r="2.7" fill="var(--color-copa)" />
            <circle cx="10.5" cy="29.5" r="2.7" fill="var(--color-copa)" />
        </pattern>

        {{--
            El dorso de fábrica: un isotipo chico por baldosa de 14, palito en Tinta y cabeza en Copa, como los
            fósforos del tanteador. La grilla queda centrada en la carta, así el dorso es igual dado vuelta.
        --}}
        <pattern id="trama-isotipos" x="1" y="1" width="14" height="14" patternUnits="userSpaceOnUse">
            <g transform="translate(2 2) scale(0.357)">
                <path d="M4 19.5V8M8.5 4H20M24 8.5V20M19.5 24H8" fill="none" stroke="var(--color-tinta)" stroke-width="2.6" stroke-linecap="round" />
                <g fill="var(--color-copa)">
                    <circle cx="4" cy="4.6" r="2.9" />
                    <circle cx="23.4" cy="4" r="2.9" />
                    <circle cx="24" cy="23.4" r="2.9" />
                    <circle cx="4.6" cy="24" r="2.9" />
                </g>
            </g>
        </pattern>

        {{--
            Los dorsos de la tanda 3, en la tradición de los dorsos del siglo XIX: una trama chica y pareja que
            no distrae. Las tres grillas quedan centradas en la carta, así el dorso es igual dado vuelta.
            Damero: casillas de 7 en Espada, doce por veinte (las dos cuentas pares, así la esquina de arriba
            y la de abajo tienen el mismo color). Lunares: puntos en Copa, en tresbolillo, uno justo en el centro.
        --}}
        <pattern id="trama-damero" x="8" y="8" width="14" height="14" patternUnits="userSpaceOnUse">
            <path d="M0 0H7V7H0ZM7 7H14V14H7Z" fill="var(--color-espada)" />
        </pattern>

        <pattern id="trama-lunares" x="0" y="-2" width="10" height="10" patternUnits="userSpaceOnUse">
            <g fill="var(--color-copa)">
                <circle cx="0" cy="0" r="2.3" />
                <circle cx="10" cy="0" r="2.3" />
                <circle cx="0" cy="10" r="2.3" />
                <circle cx="10" cy="10" r="2.3" />
                <circle cx="5" cy="5" r="2.3" />
            </g>
        </pattern>

        <pattern id="trama-esterilla" x="50" y="78" width="12" height="12" patternUnits="userSpaceOnUse"><path d="M3.5 0H8.5L12 3.5V8.5L8.5 12H3.5L0 8.5V3.5Z" fill="none" stroke="#8f6a35" stroke-width="1.3" transform="translate(-6 -6)" /><path d="M3.5 0H8.5L12 3.5V8.5L8.5 12H3.5L0 8.5V3.5Z" fill="none" stroke="#8f6a35" stroke-width="1.3" transform="translate(6 6)" /><path d="M3.5 0H8.5L12 3.5V8.5L8.5 12H3.5L0 8.5V3.5Z" fill="none" stroke="#8f6a35" stroke-width="1.3" transform="translate(6 -6)" /><path d="M3.5 0H8.5L12 3.5V8.5L8.5 12H3.5L0 8.5V3.5Z" fill="none" stroke="#8f6a35" stroke-width="1.3" transform="translate(-6 6)" /></pattern>

        <pattern id="trama-cuadros" x="50" y="78" width="12" height="12" patternUnits="userSpaceOnUse" patternTransform="rotate(45 50 78)"><rect x="-2.2" y="-6" width="4.4" height="24" fill="var(--color-espada)" opacity=".7" transform="translate(0 0)" /><rect x="9.8" y="-6" width="4.4" height="24" fill="var(--color-espada)" opacity=".7" /><rect x="-6" y="-2.2" width="24" height="4.4" fill="var(--color-espada)" opacity=".7" /><rect x="-6" y="9.8" width="24" height="4.4" fill="var(--color-espada)" opacity=".7" /><path d="M6 0V12M0 6H12" stroke="var(--color-tinta)" stroke-width="0.8" /></pattern>

        <pattern id="trama-aros" x="50" y="78" width="12" height="12" patternUnits="userSpaceOnUse"><circle cx="0" cy="0" r="6" fill="none" stroke="var(--color-basto)" stroke-width="1.1" /><circle cx="12" cy="0" r="6" fill="none" stroke="var(--color-basto)" stroke-width="1.1" /><circle cx="0" cy="12" r="6" fill="none" stroke="var(--color-basto)" stroke-width="1.1" /><circle cx="12" cy="12" r="6" fill="none" stroke="var(--color-basto)" stroke-width="1.1" /><circle cx="6" cy="6" r="6" fill="none" stroke="var(--color-basto)" stroke-width="1.1" /></pattern>

        <pattern id="trama-estrella" x="50" y="78" width="16" height="16" patternUnits="userSpaceOnUse"><path d="M0 -6.2L1.47 -3.55L4.38 -4.38L3.55 -1.47L6.2 0L3.55 1.47L4.38 4.38L1.47 3.55L0 6.2L-1.47 3.55L-4.38 4.38L-3.55 1.47L-6.2 0L-3.55 -1.47L-4.38 -4.38L-1.47 -3.55Z" fill="var(--color-copa)" /><circle cx="0" cy="0" r="1.3" fill="var(--color-naipe)" /><path d="M16 -6.2L17.47 -3.55L20.38 -4.38L19.55 -1.47L22.2 0L19.55 1.47L20.38 4.38L17.47 3.55L16 6.2L14.53 3.55L11.62 4.38L12.45 1.47L9.8 0L12.45 -1.47L11.62 -4.38L14.53 -3.55Z" fill="var(--color-copa)" /><circle cx="16" cy="0" r="1.3" fill="var(--color-naipe)" /><path d="M0 9.8L1.47 12.45L4.38 11.62L3.55 14.53L6.2 16L3.55 17.47L4.38 20.38L1.47 19.55L0 22.2L-1.47 19.55L-4.38 20.38L-3.55 17.47L-6.2 16L-3.55 14.53L-4.38 11.62L-1.47 12.45Z" fill="var(--color-copa)" /><circle cx="0" cy="16" r="1.3" fill="var(--color-naipe)" /><path d="M16 9.8L17.47 12.45L20.38 11.62L19.55 14.53L22.2 16L19.55 17.47L20.38 20.38L17.47 19.55L16 22.2L14.53 19.55L11.62 20.38L12.45 17.47L9.8 16L12.45 14.53L11.62 11.62L14.53 12.45Z" fill="var(--color-copa)" /><circle cx="16" cy="16" r="1.3" fill="var(--color-naipe)" /><path d="M8 5.6 10.4 8 8 10.4 5.6 8Z" fill="var(--color-tinta)" /></pattern>

        <clipPath id="recorte-dorso">
            <rect x="8" y="8" width="84" height="140" rx="3" />
        </clipPath>
    </defs>

    <symbol id="isotipo" viewBox="0 0 28 28">
        <g fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
            <path d="M4 19.5V8M8.5 4H20M24 8.5V20M19.5 24H8" />
        </g>
        <g fill="currentColor">
            <circle cx="4" cy="4.6" r="2.4" />
            <circle cx="23.4" cy="4" r="2.4" />
            <circle cx="24" cy="23.4" r="2.4" />
            <circle cx="4.6" cy="24" r="2.4" />
        </g>
    </symbol>

    <symbol id="palo-oro" viewBox="0 0 20 30">
        <circle cx="10" cy="15" r="9" fill="var(--color-oro)" stroke="var(--color-tinta)" stroke-width="1.1" />
        <circle cx="10" cy="15" r="5.2" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" />
        <circle cx="10" cy="15" r="1.7" fill="var(--color-tinta)" />
    </symbol>

    <symbol id="palo-copa" viewBox="0 0 20 30">
        <g fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="1.1" stroke-linejoin="round">
            <path d="M3 4.5H17V11A7 7 0 0 1 3 11Z" />
            <path d="M8.5 18H11.5V23H8.5Z" />
            <path d="M4.5 27 7 23H13L15.5 27Z" />
        </g>
        <path d="M3 8.5H17" stroke="var(--color-tinta)" stroke-width="1.1" />
    </symbol>

    <symbol id="palo-espada" viewBox="0 0 20 30">
        <g fill="var(--color-espada)" stroke="var(--color-tinta)" stroke-width="1.1" stroke-linejoin="round">
            <path d="M10 1 12.8 5.5V19.5H7.2V5.5Z" />
            <path d="M3.5 19.5H16.5V22.5H3.5Z" />
            <path d="M8.6 22.5H11.4V26H8.6Z" />
            <circle cx="10" cy="27.6" r="1.9" />
        </g>
        <path d="M10 6V17" stroke="var(--color-tinta)" stroke-width="1.1" />
    </symbol>

    <symbol id="palo-basto" viewBox="0 0 20 30">
        <path d="M7.6 28.5 5.5 7A4.5 4.5 0 0 1 14.5 7L12.4 28.5Z" fill="var(--color-basto)" stroke="var(--color-tinta)" stroke-width="1.1" stroke-linejoin="round" />
        <g fill="var(--color-tinta)">
            <circle cx="8.6" cy="9" r="1.1" />
            <circle cx="11.6" cy="14.5" r="1.1" />
            <circle cx="9.4" cy="20.5" r="1.1" />
        </g>
    </symbol>

    {{--
        Las figuras, de cuerpo entero y paradas sobre el marco de abajo. Se dibujan
        sobre el lienzo completo de la carta (100 x 156). Usan currentColor: la
        tinta del palo llega desde la carta. El palo va aparte, en la mano.
        Cada una tiene una silueta distinta para reconocerse en chico: la sota es
        flaca y de pie, el caballo es ancho, el rey tiene túnica hasta el piso.
    --}}
    <symbol id="figura-sota" viewBox="0 0 100 156">
        <g stroke="var(--color-tinta)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round">
            <path d="M31 112H39V141H31ZM42 112H50V141H42Z" fill="var(--color-naipe)" />
            <path d="M28 141H40V147H28ZM41 141H54V147H41Z" fill="var(--color-tinta)" />
            <path d="M27 67Q40 59 53 67L58 114H22Z" fill="currentColor" />
            <path d="M24.5 93H55.5" fill="none" />
            <path d="M51 67 71 72 70 80 51 78Z" fill="currentColor" />
            <circle cx="73" cy="76.5" r="3.4" fill="var(--color-naipe)" />
            <circle cx="40" cy="49" r="10.5" fill="var(--color-naipe)" />
            <path d="M28.5 46Q40 27 51.5 46Z" fill="currentColor" />
            <path d="M49 39 58 27" fill="none" />
        </g>
        <g fill="var(--color-tinta)">
            <circle cx="36.5" cy="51" r="1.3" />
            <circle cx="43.5" cy="51" r="1.3" />
        </g>
    </symbol>

    <symbol id="figura-caballo" viewBox="0 0 100 156">
        <g stroke="var(--color-tinta)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round">
            <path d="M21 96Q8 99 11 122 16 108 21 106Z" fill="var(--color-naipe)" />
            <path d="M22 114H28.5V147H22ZM32 114H38.5V147H32ZM48 114H54.5V147H48ZM58 114H64.5V147H58Z" fill="var(--color-naipe)" />
            <path d="M22 142H28.5V147H22ZM32 142H38.5V147H32ZM48 142H54.5V147H48ZM58 142H64.5V147H58Z" fill="var(--color-tinta)" />
            <path d="M20 104Q20 92 32 92H56Q65 92 65 104V117H20Z" fill="var(--color-naipe)" />
            <path d="M51 94 58 70 57 60 63 66Q76 70 80 82L78 88 70 87 65 80V100Z" fill="var(--color-naipe)" />
            <path d="M57 72 52 70M58 79 52.5 78M58.5 86 53 86" fill="none" />
            <path d="M37 90 35 110H43L45 90Z" fill="currentColor" />
            <path d="M32 93 34 67Q41 61 48 67L50 93Z" fill="currentColor" />
            <path d="M46 68 64 54 68 60 49 77Z" fill="currentColor" />
            <circle cx="67.5" cy="56" r="3.2" fill="var(--color-naipe)" />
            <circle cx="41" cy="52" r="9" fill="var(--color-naipe)" />
            <path d="M31 50Q41 34 51 50Z" fill="currentColor" />
        </g>
        <g fill="var(--color-tinta)">
            <circle cx="44.5" cy="53.5" r="1.3" />
            <circle cx="70" cy="75" r="1.4" />
        </g>
    </symbol>

    <symbol id="figura-rey" viewBox="0 0 100 156">
        <g transform="translate(2 0)" stroke="var(--color-tinta)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round">
            <path d="M15 147 27 68Q40 60 53 68L65 147Z" fill="currentColor" />
            <path d="M40 78V147M16.5 137H63.5" fill="none" />
            <path d="M27 68Q40 83 53 68 40 60 27 68Z" fill="var(--color-naipe)" />
            <path d="M51 70 71 74 70 82 52 81Z" fill="currentColor" />
            <circle cx="73" cy="78.5" r="3.4" fill="var(--color-naipe)" />
            <circle cx="40" cy="50" r="10.5" fill="var(--color-naipe)" />
            <path d="M32 55Q40 70 48 55Z" fill="var(--color-tinta)" />
            <path d="M28.5 45V27L34.5 35 40 24 45.5 35 51.5 27V45Z" fill="currentColor" />
        </g>
        <g transform="translate(2 0)" fill="var(--color-tinta)">
            <circle cx="36.5" cy="50" r="1.3" />
            <circle cx="43.5" cy="50" r="1.3" />
        </g>
    </symbol>

    {{--
        El dorso de fábrica, "Isotipos" (elegido por Román el 15 de octubre de 2026): la trama de isotipos con
        el marco doble de las caras y el logo grande en un medallón, así se reconoce aun en chico.
    --}}
    @if ($dorsos->hace('fabrica'))
    <symbol id="dorso" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="11.5" y="11.5" width="77" height="133" rx="1.5" fill="url(#trama-isotipos)" stroke="var(--color-tinta)" stroke-width="0.8" />
        <circle cx="50" cy="78" r="19" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <g transform="translate(37 65) scale(0.93)">
            <path d="M4 19.5V8M8.5 4H20M24 8.5V20M19.5 24H8" fill="none" stroke="var(--color-tinta)" stroke-width="2.2" stroke-linecap="round" />
            <g fill="var(--color-copa)">
                <circle cx="4" cy="4.6" r="2.4" />
                <circle cx="23.4" cy="4" r="2.4" />
                <circle cx="24" cy="23.4" r="2.4" />
                <circle cx="4.6" cy="24" r="2.4" />
            </g>
        </g>
    </symbol>
    @endif

    {{-- "Rombos": el dorso de fábrica hasta el 15 de octubre de 2026, que pasó a ser un premio del nivel 3. --}}
    @if ($dorsos->hace('rombos'))
    <symbol id="dorso-rombos" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="url(#trama-dorso)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <circle cx="50" cy="78" r="18" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <use href="#isotipo" x="37" y="65" width="26" height="26" style="color: var(--color-tinta)" />
    </symbol>
    @endif

    {{--
        Los dorsos que se ganan en la escalera del club. Los tres van en las tintas del mazo, planas, y se
        distinguen por la forma, no por el color: rayas de fósforos, un bloque de tinta y una ronda de anillos.
    --}}
    @if ($dorsos->hace('fosforos'))
    <symbol id="dorso-fosforos" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="url(#trama-fosforos)" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif

    @if ($dorsos->hace('tinta'))
    <symbol id="dorso-tinta" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="var(--color-tinta)" />
        <rect x="14.5" y="14.5" width="71" height="127" rx="1.5" fill="none" stroke="var(--color-naipe)" stroke-width="1.2" />
        <use href="#isotipo" x="28" y="56" width="44" height="44" style="color: var(--color-naipe)" />
    </symbol>
    @endif

    @if ($dorsos->hace('ronda'))
    <symbol id="dorso-ronda" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <g clip-path="url(#recorte-dorso)" fill="none" stroke="var(--color-tinta)" stroke-width="4.5">
            <circle cx="50" cy="78" r="9" />
            <circle cx="50" cy="78" r="20" />
            <circle cx="50" cy="78" r="31" />
            <circle cx="50" cy="78" r="42" />
            <circle cx="50" cy="78" r="53" />
            <circle cx="50" cy="78" r="64" />
            <circle cx="50" cy="78" r="75" />
            <circle cx="50" cy="78" r="86" />
        </g>
        <circle cx="50" cy="78" r="3.4" fill="var(--color-tinta)" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif

    @if ($dorsos->hace('damero'))
    <symbol id="dorso-damero" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="url(#trama-damero)" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif

    @if ($dorsos->hace('lunares'))
    <symbol id="dorso-lunares" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="11.5" y="11.5" width="77" height="133" rx="1.5" fill="url(#trama-lunares)" stroke="var(--color-tinta)" stroke-width="0.8" />
    </symbol>
    @endif

    @php
        /*
         | Zigzag: arriba las puntas miran para arriba y abajo para abajo, y en el medio una raya doble en Copa.
         | Cada renglón de abajo es el de arriba dado vuelta, así el dorso es igual girado media vuelta.
         */
        $renglon = function (float $y, int $hacia): string {
            $puntos = [];
            for ($k = 0; $k <= 14; $k++) {
                $puntos[] = sprintf('%.1f %.1f', 11.5 + 5.5 * $k, $y - ($k % 2) * 4 * $hacia);
            }

            return 'M'.implode('L', $puntos);
        };
        $zigzag = '';
        foreach ([19, 26.5, 34, 41.5, 49, 56.5, 64] as $y) {
            $zigzag .= $renglon($y, 1).$renglon(156 - $y, -1);
        }
    @endphp
    @if ($dorsos->hace('zigzag'))
    <symbol id="dorso-zigzag" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <g clip-path="url(#recorte-dorso)">
            <path d="{{ $zigzag }}" fill="none" stroke="var(--color-tinta)" stroke-width="2" stroke-linejoin="miter" />
        </g>
        <path d="M8 74.5H92M8 81.5H92" stroke="var(--color-copa)" stroke-width="2" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif

    {{-- "Ramitas": los cuatro palos chicos repetidos, derechos arriba y dados vuelta abajo, como una figura. --}}
    @if ($dorsos->hace('ramitas'))
    <symbol id="dorso-ramitas" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <g clip-path="url(#recorte-dorso)"><use href="#palo-oro" x="11" y="12" width="9" height="13.5" /><use href="#palo-copa" x="32" y="12" width="9" height="13.5" /><use href="#palo-espada" x="53" y="12" width="9" height="13.5" /><use href="#palo-basto" x="74" y="12" width="9" height="13.5" /><use href="#palo-oro" x="21.5" y="28" width="9" height="13.5" /><use href="#palo-copa" x="42.5" y="28" width="9" height="13.5" /><use href="#palo-espada" x="63.5" y="28" width="9" height="13.5" /><use href="#palo-basto" x="11" y="44" width="9" height="13.5" /><use href="#palo-oro" x="32" y="44" width="9" height="13.5" /><use href="#palo-copa" x="53" y="44" width="9" height="13.5" /><use href="#palo-espada" x="74" y="44" width="9" height="13.5" /><use href="#palo-basto" x="21.5" y="60" width="9" height="13.5" /><use href="#palo-oro" x="42.5" y="60" width="9" height="13.5" /><use href="#palo-copa" x="63.5" y="60" width="9" height="13.5" /><g transform="rotate(180 50 78)"><use href="#palo-oro" x="11" y="12" width="9" height="13.5" /><use href="#palo-copa" x="32" y="12" width="9" height="13.5" /><use href="#palo-espada" x="53" y="12" width="9" height="13.5" /><use href="#palo-basto" x="74" y="12" width="9" height="13.5" /><use href="#palo-oro" x="21.5" y="28" width="9" height="13.5" /><use href="#palo-copa" x="42.5" y="28" width="9" height="13.5" /><use href="#palo-espada" x="63.5" y="28" width="9" height="13.5" /><use href="#palo-basto" x="11" y="44" width="9" height="13.5" /><use href="#palo-oro" x="32" y="44" width="9" height="13.5" /><use href="#palo-copa" x="53" y="44" width="9" height="13.5" /><use href="#palo-espada" x="74" y="44" width="9" height="13.5" /><use href="#palo-basto" x="21.5" y="60" width="9" height="13.5" /><use href="#palo-oro" x="42.5" y="60" width="9" height="13.5" /><use href="#palo-copa" x="63.5" y="60" width="9" height="13.5" /></g></g><path d="M8 78H92" stroke="var(--color-tinta)" stroke-width="1" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif

    {{-- "Ondas": escamas en Basto, como el agua de un grabado; arriba y abajo miran hacia el centro. --}}
    @if ($dorsos->hace('ondas'))
    <symbol id="dorso-ondas" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <g clip-path="url(#recorte-dorso)"><path d="M-6 16a6 6 0 0 0 12 0M6 16a6 6 0 0 0 12 0M18 16a6 6 0 0 0 12 0M30 16a6 6 0 0 0 12 0M42 16a6 6 0 0 0 12 0M54 16a6 6 0 0 0 12 0M66 16a6 6 0 0 0 12 0M78 16a6 6 0 0 0 12 0M90 16a6 6 0 0 0 12 0M0 24a6 6 0 0 0 12 0M12 24a6 6 0 0 0 12 0M24 24a6 6 0 0 0 12 0M36 24a6 6 0 0 0 12 0M48 24a6 6 0 0 0 12 0M60 24a6 6 0 0 0 12 0M72 24a6 6 0 0 0 12 0M84 24a6 6 0 0 0 12 0M96 24a6 6 0 0 0 12 0M-6 32a6 6 0 0 0 12 0M6 32a6 6 0 0 0 12 0M18 32a6 6 0 0 0 12 0M30 32a6 6 0 0 0 12 0M42 32a6 6 0 0 0 12 0M54 32a6 6 0 0 0 12 0M66 32a6 6 0 0 0 12 0M78 32a6 6 0 0 0 12 0M90 32a6 6 0 0 0 12 0M0 40a6 6 0 0 0 12 0M12 40a6 6 0 0 0 12 0M24 40a6 6 0 0 0 12 0M36 40a6 6 0 0 0 12 0M48 40a6 6 0 0 0 12 0M60 40a6 6 0 0 0 12 0M72 40a6 6 0 0 0 12 0M84 40a6 6 0 0 0 12 0M96 40a6 6 0 0 0 12 0M-6 48a6 6 0 0 0 12 0M6 48a6 6 0 0 0 12 0M18 48a6 6 0 0 0 12 0M30 48a6 6 0 0 0 12 0M42 48a6 6 0 0 0 12 0M54 48a6 6 0 0 0 12 0M66 48a6 6 0 0 0 12 0M78 48a6 6 0 0 0 12 0M90 48a6 6 0 0 0 12 0M0 56a6 6 0 0 0 12 0M12 56a6 6 0 0 0 12 0M24 56a6 6 0 0 0 12 0M36 56a6 6 0 0 0 12 0M48 56a6 6 0 0 0 12 0M60 56a6 6 0 0 0 12 0M72 56a6 6 0 0 0 12 0M84 56a6 6 0 0 0 12 0M96 56a6 6 0 0 0 12 0M-6 64a6 6 0 0 0 12 0M6 64a6 6 0 0 0 12 0M18 64a6 6 0 0 0 12 0M30 64a6 6 0 0 0 12 0M42 64a6 6 0 0 0 12 0M54 64a6 6 0 0 0 12 0M66 64a6 6 0 0 0 12 0M78 64a6 6 0 0 0 12 0M90 64a6 6 0 0 0 12 0M0 72a6 6 0 0 0 12 0M12 72a6 6 0 0 0 12 0M24 72a6 6 0 0 0 12 0M36 72a6 6 0 0 0 12 0M48 72a6 6 0 0 0 12 0M60 72a6 6 0 0 0 12 0M72 72a6 6 0 0 0 12 0M84 72a6 6 0 0 0 12 0M96 72a6 6 0 0 0 12 0" fill="none" stroke="var(--color-basto)" stroke-width="1.4" /><g transform="rotate(180 50 78)"><path d="M-6 16a6 6 0 0 0 12 0M6 16a6 6 0 0 0 12 0M18 16a6 6 0 0 0 12 0M30 16a6 6 0 0 0 12 0M42 16a6 6 0 0 0 12 0M54 16a6 6 0 0 0 12 0M66 16a6 6 0 0 0 12 0M78 16a6 6 0 0 0 12 0M90 16a6 6 0 0 0 12 0M0 24a6 6 0 0 0 12 0M12 24a6 6 0 0 0 12 0M24 24a6 6 0 0 0 12 0M36 24a6 6 0 0 0 12 0M48 24a6 6 0 0 0 12 0M60 24a6 6 0 0 0 12 0M72 24a6 6 0 0 0 12 0M84 24a6 6 0 0 0 12 0M96 24a6 6 0 0 0 12 0M-6 32a6 6 0 0 0 12 0M6 32a6 6 0 0 0 12 0M18 32a6 6 0 0 0 12 0M30 32a6 6 0 0 0 12 0M42 32a6 6 0 0 0 12 0M54 32a6 6 0 0 0 12 0M66 32a6 6 0 0 0 12 0M78 32a6 6 0 0 0 12 0M90 32a6 6 0 0 0 12 0M0 40a6 6 0 0 0 12 0M12 40a6 6 0 0 0 12 0M24 40a6 6 0 0 0 12 0M36 40a6 6 0 0 0 12 0M48 40a6 6 0 0 0 12 0M60 40a6 6 0 0 0 12 0M72 40a6 6 0 0 0 12 0M84 40a6 6 0 0 0 12 0M96 40a6 6 0 0 0 12 0M-6 48a6 6 0 0 0 12 0M6 48a6 6 0 0 0 12 0M18 48a6 6 0 0 0 12 0M30 48a6 6 0 0 0 12 0M42 48a6 6 0 0 0 12 0M54 48a6 6 0 0 0 12 0M66 48a6 6 0 0 0 12 0M78 48a6 6 0 0 0 12 0M90 48a6 6 0 0 0 12 0M0 56a6 6 0 0 0 12 0M12 56a6 6 0 0 0 12 0M24 56a6 6 0 0 0 12 0M36 56a6 6 0 0 0 12 0M48 56a6 6 0 0 0 12 0M60 56a6 6 0 0 0 12 0M72 56a6 6 0 0 0 12 0M84 56a6 6 0 0 0 12 0M96 56a6 6 0 0 0 12 0M-6 64a6 6 0 0 0 12 0M6 64a6 6 0 0 0 12 0M18 64a6 6 0 0 0 12 0M30 64a6 6 0 0 0 12 0M42 64a6 6 0 0 0 12 0M54 64a6 6 0 0 0 12 0M66 64a6 6 0 0 0 12 0M78 64a6 6 0 0 0 12 0M90 64a6 6 0 0 0 12 0M0 72a6 6 0 0 0 12 0M12 72a6 6 0 0 0 12 0M24 72a6 6 0 0 0 12 0M36 72a6 6 0 0 0 12 0M48 72a6 6 0 0 0 12 0M60 72a6 6 0 0 0 12 0M72 72a6 6 0 0 0 12 0M84 72a6 6 0 0 0 12 0M96 72a6 6 0 0 0 12 0" fill="none" stroke="var(--color-basto)" stroke-width="1.4" /></g></g>
        <rect x="8" y="8" width="84" height="140" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif

    {{-- "Tanteador": oscuro como la barra del tanteador, con grupos de cinco en dos columnas y la raya de malas y buenas. --}}
    @if ($dorsos->hace('tanteador'))
    <symbol id="dorso-tanteador" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="#153f32" /><g clip-path="url(#recorte-dorso)"><g transform="translate(17.5 11) scale(0.7)"><path d="M4 19.5V8M8.5 4H20M24 8.5V20M19.5 24H8M9 19 17.6 10.4" fill="none" stroke="#e8c98a" stroke-width="2.4" stroke-linecap="round" /><g fill="var(--color-copa)"><circle cx="4" cy="4.6" r="2.6" /><circle cx="23.4" cy="4" r="2.6" /><circle cx="24" cy="23.4" r="2.6" /><circle cx="4.6" cy="24" r="2.6" /><circle cx="19.8" cy="8.2" r="2.6" /></g></g><g transform="translate(17.5 33) scale(0.7)"><path d="M4 19.5V8M8.5 4H20M24 8.5V20M19.5 24H8M9 19 17.6 10.4" fill="none" stroke="#e8c98a" stroke-width="2.4" stroke-linecap="round" /><g fill="var(--color-copa)"><circle cx="4" cy="4.6" r="2.6" /><circle cx="23.4" cy="4" r="2.6" /><circle cx="24" cy="23.4" r="2.6" /><circle cx="4.6" cy="24" r="2.6" /><circle cx="19.8" cy="8.2" r="2.6" /></g></g><g transform="translate(17.5 55) scale(0.7)"><path d="M4 19.5V8M8.5 4H20M24 8.5V20M19.5 24H8M9 19 17.6 10.4" fill="none" stroke="#e8c98a" stroke-width="2.4" stroke-linecap="round" /><g fill="var(--color-copa)"><circle cx="4" cy="4.6" r="2.6" /><circle cx="23.4" cy="4" r="2.6" /><circle cx="24" cy="23.4" r="2.6" /><circle cx="4.6" cy="24" r="2.6" /><circle cx="19.8" cy="8.2" r="2.6" /></g></g><g transform="translate(61.5 11) scale(0.7)"><path d="M4 19.5V8M8.5 4H20M24 8.5V20M19.5 24H8M9 19 17.6 10.4" fill="none" stroke="#e8c98a" stroke-width="2.4" stroke-linecap="round" /><g fill="var(--color-copa)"><circle cx="4" cy="4.6" r="2.6" /><circle cx="23.4" cy="4" r="2.6" /><circle cx="24" cy="23.4" r="2.6" /><circle cx="4.6" cy="24" r="2.6" /><circle cx="19.8" cy="8.2" r="2.6" /></g></g><g transform="translate(61.5 33) scale(0.7)"><path d="M4 19.5V8M8.5 4H20M24 8.5V20M19.5 24H8M9 19 17.6 10.4" fill="none" stroke="#e8c98a" stroke-width="2.4" stroke-linecap="round" /><g fill="var(--color-copa)"><circle cx="4" cy="4.6" r="2.6" /><circle cx="23.4" cy="4" r="2.6" /><circle cx="24" cy="23.4" r="2.6" /><circle cx="4.6" cy="24" r="2.6" /><circle cx="19.8" cy="8.2" r="2.6" /></g></g><g transform="translate(61.5 55) scale(0.7)"><path d="M4 19.5V8M8.5 4H20M24 8.5V20M19.5 24H8M9 19 17.6 10.4" fill="none" stroke="#e8c98a" stroke-width="2.4" stroke-linecap="round" /><g fill="var(--color-copa)"><circle cx="4" cy="4.6" r="2.6" /><circle cx="23.4" cy="4" r="2.6" /><circle cx="24" cy="23.4" r="2.6" /><circle cx="4.6" cy="24" r="2.6" /><circle cx="19.8" cy="8.2" r="2.6" /></g></g><g transform="rotate(180 50 78)"><g transform="translate(17.5 11) scale(0.7)"><path d="M4 19.5V8M8.5 4H20M24 8.5V20M19.5 24H8M9 19 17.6 10.4" fill="none" stroke="#e8c98a" stroke-width="2.4" stroke-linecap="round" /><g fill="var(--color-copa)"><circle cx="4" cy="4.6" r="2.6" /><circle cx="23.4" cy="4" r="2.6" /><circle cx="24" cy="23.4" r="2.6" /><circle cx="4.6" cy="24" r="2.6" /><circle cx="19.8" cy="8.2" r="2.6" /></g></g><g transform="translate(17.5 33) scale(0.7)"><path d="M4 19.5V8M8.5 4H20M24 8.5V20M19.5 24H8M9 19 17.6 10.4" fill="none" stroke="#e8c98a" stroke-width="2.4" stroke-linecap="round" /><g fill="var(--color-copa)"><circle cx="4" cy="4.6" r="2.6" /><circle cx="23.4" cy="4" r="2.6" /><circle cx="24" cy="23.4" r="2.6" /><circle cx="4.6" cy="24" r="2.6" /><circle cx="19.8" cy="8.2" r="2.6" /></g></g><g transform="translate(17.5 55) scale(0.7)"><path d="M4 19.5V8M8.5 4H20M24 8.5V20M19.5 24H8M9 19 17.6 10.4" fill="none" stroke="#e8c98a" stroke-width="2.4" stroke-linecap="round" /><g fill="var(--color-copa)"><circle cx="4" cy="4.6" r="2.6" /><circle cx="23.4" cy="4" r="2.6" /><circle cx="24" cy="23.4" r="2.6" /><circle cx="4.6" cy="24" r="2.6" /><circle cx="19.8" cy="8.2" r="2.6" /></g></g><g transform="translate(61.5 11) scale(0.7)"><path d="M4 19.5V8M8.5 4H20M24 8.5V20M19.5 24H8M9 19 17.6 10.4" fill="none" stroke="#e8c98a" stroke-width="2.4" stroke-linecap="round" /><g fill="var(--color-copa)"><circle cx="4" cy="4.6" r="2.6" /><circle cx="23.4" cy="4" r="2.6" /><circle cx="24" cy="23.4" r="2.6" /><circle cx="4.6" cy="24" r="2.6" /><circle cx="19.8" cy="8.2" r="2.6" /></g></g><g transform="translate(61.5 33) scale(0.7)"><path d="M4 19.5V8M8.5 4H20M24 8.5V20M19.5 24H8M9 19 17.6 10.4" fill="none" stroke="#e8c98a" stroke-width="2.4" stroke-linecap="round" /><g fill="var(--color-copa)"><circle cx="4" cy="4.6" r="2.6" /><circle cx="23.4" cy="4" r="2.6" /><circle cx="24" cy="23.4" r="2.6" /><circle cx="4.6" cy="24" r="2.6" /><circle cx="19.8" cy="8.2" r="2.6" /></g></g><g transform="translate(61.5 55) scale(0.7)"><path d="M4 19.5V8M8.5 4H20M24 8.5V20M19.5 24H8M9 19 17.6 10.4" fill="none" stroke="#e8c98a" stroke-width="2.4" stroke-linecap="round" /><g fill="var(--color-copa)"><circle cx="4" cy="4.6" r="2.6" /><circle cx="23.4" cy="4" r="2.6" /><circle cx="24" cy="23.4" r="2.6" /><circle cx="4.6" cy="24" r="2.6" /><circle cx="19.8" cy="8.2" r="2.6" /></g></g></g></g><path d="M50 14V142" stroke="var(--color-naipe)" stroke-width="1" opacity=".45" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif

    {{-- "Esterilla": la paja de Viena de las sillas de café, octógonos de caña. La grilla está centrada en la carta. --}}
    @if ($dorsos->hace('esterilla'))
    <symbol id="dorso-esterilla" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="11.5" y="11.5" width="77" height="133" rx="1.5" fill="url(#trama-esterilla)" stroke="var(--color-tinta)" stroke-width="0.8" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif

    {{-- "Guarda griega": la greca de las barajas viejas entre dos filetes, alrededor de un centro limpio con un filete en Copa. --}}
    @if ($dorsos->hace('guarda-griega'))
    <symbol id="dorso-guarda-griega" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <g clip-path="url(#recorte-dorso)"><path d="M14 13H86M14 27H86" stroke="var(--color-tinta)" stroke-width="1" /><path d="M2 9V1H10V7H5V4H8M14 9V1H22V7H17V4H20M26 9V1H34V7H29V4H32M38 9V1H46V7H41V4H44M50 9V1H58V7H53V4H56M62 9V1H70V7H65V4H68" transform="translate(14 15)" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" /><g transform="rotate(180 50 78)"><path d="M14 13H86M14 27H86" stroke="var(--color-tinta)" stroke-width="1" /><path d="M2 9V1H10V7H5V4H8M14 9V1H22V7H17V4H20M26 9V1H34V7H29V4H32M38 9V1H46V7H41V4H44M50 9V1H58V7H53V4H56M62 9V1H70V7H65V4H68" transform="translate(14 15)" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" /></g></g><g transform="translate(13 31) rotate(90) translate(0 -14)"><path d="M0 1H94M0 13H94" stroke="var(--color-tinta)" stroke-width="1" /><path d="M2 9V1H10V7H5V4H8M14 9V1H22V7H17V4H20M26 9V1H34V7H29V4H32M38 9V1H46V7H41V4H44M50 9V1H58V7H53V4H56M62 9V1H70V7H65V4H68M74 9V1H82V7H77V4H80" transform="translate(0 2)" fill="none" stroke="var(--color-tinta)" stroke-width="1.4" /></g><g transform="rotate(180 50 78)"><g transform="translate(13 31) rotate(90) translate(0 -14)"><path d="M0 1H94M0 13H94" stroke="var(--color-tinta)" stroke-width="1" /><path d="M2 9V1H10V7H5V4H8M14 9V1H22V7H17V4H20M26 9V1H34V7H29V4H32M38 9V1H46V7H41V4H44M50 9V1H58V7H53V4H56M62 9V1H70V7H65V4H68M74 9V1H82V7H77V4H80" transform="translate(0 2)" fill="none" stroke="var(--color-tinta)" stroke-width="1.4" /></g></g><rect x="30" y="34" width="40" height="88" rx="1.5" fill="none" stroke="var(--color-copa)" stroke-width="1" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif

    {{-- "Abanicos": medios círculos con sus varillas, como la marquetería Art Déco; arriba y abajo se abren hacia el centro. --}}
    @if ($dorsos->hace('abanicos'))
    <symbol id="dorso-abanicos" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <g clip-path="url(#recorte-dorso)"><path d="M-6 24A8 8 0 0 1 10 24ZM2 24L8.93 20M2 24L6 17.07M2 24L2 16M2 24L-2 17.07M2 24L-4.93 20" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M10 24A8 8 0 0 1 26 24ZM18 24L24.93 20M18 24L22 17.07M18 24L18 16M18 24L14 17.07M18 24L11.07 20" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M26 24A8 8 0 0 1 42 24ZM34 24L40.93 20M34 24L38 17.07M34 24L34 16M34 24L30 17.07M34 24L27.07 20" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M42 24A8 8 0 0 1 58 24ZM50 24L56.93 20M50 24L54 17.07M50 24L50 16M50 24L46 17.07M50 24L43.07 20" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M58 24A8 8 0 0 1 74 24ZM66 24L72.93 20M66 24L70 17.07M66 24L66 16M66 24L62 17.07M66 24L59.07 20" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M74 24A8 8 0 0 1 90 24ZM82 24L88.93 20M82 24L86 17.07M82 24L82 16M82 24L78 17.07M82 24L75.07 20" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M90 24A8 8 0 0 1 106 24ZM98 24L104.93 20M98 24L102 17.07M98 24L98 16M98 24L94 17.07M98 24L91.07 20" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M2 40A8 8 0 0 1 18 40ZM10 40L16.93 36M10 40L14 33.07M10 40L10 32M10 40L6 33.07M10 40L3.07 36" fill="none" stroke="var(--color-copa)" stroke-width="1.1" /><path d="M18 40A8 8 0 0 1 34 40ZM26 40L32.93 36M26 40L30 33.07M26 40L26 32M26 40L22 33.07M26 40L19.07 36" fill="none" stroke="var(--color-copa)" stroke-width="1.1" /><path d="M34 40A8 8 0 0 1 50 40ZM42 40L48.93 36M42 40L46 33.07M42 40L42 32M42 40L38 33.07M42 40L35.07 36" fill="none" stroke="var(--color-copa)" stroke-width="1.1" /><path d="M50 40A8 8 0 0 1 66 40ZM58 40L64.93 36M58 40L62 33.07M58 40L58 32M58 40L54 33.07M58 40L51.07 36" fill="none" stroke="var(--color-copa)" stroke-width="1.1" /><path d="M66 40A8 8 0 0 1 82 40ZM74 40L80.93 36M74 40L78 33.07M74 40L74 32M74 40L70 33.07M74 40L67.07 36" fill="none" stroke="var(--color-copa)" stroke-width="1.1" /><path d="M82 40A8 8 0 0 1 98 40ZM90 40L96.93 36M90 40L94 33.07M90 40L90 32M90 40L86 33.07M90 40L83.07 36" fill="none" stroke="var(--color-copa)" stroke-width="1.1" /><path d="M-6 56A8 8 0 0 1 10 56ZM2 56L8.93 52M2 56L6 49.07M2 56L2 48M2 56L-2 49.07M2 56L-4.93 52" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M10 56A8 8 0 0 1 26 56ZM18 56L24.93 52M18 56L22 49.07M18 56L18 48M18 56L14 49.07M18 56L11.07 52" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M26 56A8 8 0 0 1 42 56ZM34 56L40.93 52M34 56L38 49.07M34 56L34 48M34 56L30 49.07M34 56L27.07 52" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M42 56A8 8 0 0 1 58 56ZM50 56L56.93 52M50 56L54 49.07M50 56L50 48M50 56L46 49.07M50 56L43.07 52" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M58 56A8 8 0 0 1 74 56ZM66 56L72.93 52M66 56L70 49.07M66 56L66 48M66 56L62 49.07M66 56L59.07 52" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M74 56A8 8 0 0 1 90 56ZM82 56L88.93 52M82 56L86 49.07M82 56L82 48M82 56L78 49.07M82 56L75.07 52" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M90 56A8 8 0 0 1 106 56ZM98 56L104.93 52M98 56L102 49.07M98 56L98 48M98 56L94 49.07M98 56L91.07 52" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M2 72A8 8 0 0 1 18 72ZM10 72L16.93 68M10 72L14 65.07M10 72L10 64M10 72L6 65.07M10 72L3.07 68" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M18 72A8 8 0 0 1 34 72ZM26 72L32.93 68M26 72L30 65.07M26 72L26 64M26 72L22 65.07M26 72L19.07 68" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M34 72A8 8 0 0 1 50 72ZM42 72L48.93 68M42 72L46 65.07M42 72L42 64M42 72L38 65.07M42 72L35.07 68" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M50 72A8 8 0 0 1 66 72ZM58 72L64.93 68M58 72L62 65.07M58 72L58 64M58 72L54 65.07M58 72L51.07 68" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M66 72A8 8 0 0 1 82 72ZM74 72L80.93 68M74 72L78 65.07M74 72L74 64M74 72L70 65.07M74 72L67.07 68" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M82 72A8 8 0 0 1 98 72ZM90 72L96.93 68M90 72L94 65.07M90 72L90 64M90 72L86 65.07M90 72L83.07 68" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><g transform="rotate(180 50 78)"><path d="M-6 24A8 8 0 0 1 10 24ZM2 24L8.93 20M2 24L6 17.07M2 24L2 16M2 24L-2 17.07M2 24L-4.93 20" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M10 24A8 8 0 0 1 26 24ZM18 24L24.93 20M18 24L22 17.07M18 24L18 16M18 24L14 17.07M18 24L11.07 20" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M26 24A8 8 0 0 1 42 24ZM34 24L40.93 20M34 24L38 17.07M34 24L34 16M34 24L30 17.07M34 24L27.07 20" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M42 24A8 8 0 0 1 58 24ZM50 24L56.93 20M50 24L54 17.07M50 24L50 16M50 24L46 17.07M50 24L43.07 20" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M58 24A8 8 0 0 1 74 24ZM66 24L72.93 20M66 24L70 17.07M66 24L66 16M66 24L62 17.07M66 24L59.07 20" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M74 24A8 8 0 0 1 90 24ZM82 24L88.93 20M82 24L86 17.07M82 24L82 16M82 24L78 17.07M82 24L75.07 20" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M90 24A8 8 0 0 1 106 24ZM98 24L104.93 20M98 24L102 17.07M98 24L98 16M98 24L94 17.07M98 24L91.07 20" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M2 40A8 8 0 0 1 18 40ZM10 40L16.93 36M10 40L14 33.07M10 40L10 32M10 40L6 33.07M10 40L3.07 36" fill="none" stroke="var(--color-copa)" stroke-width="1.1" /><path d="M18 40A8 8 0 0 1 34 40ZM26 40L32.93 36M26 40L30 33.07M26 40L26 32M26 40L22 33.07M26 40L19.07 36" fill="none" stroke="var(--color-copa)" stroke-width="1.1" /><path d="M34 40A8 8 0 0 1 50 40ZM42 40L48.93 36M42 40L46 33.07M42 40L42 32M42 40L38 33.07M42 40L35.07 36" fill="none" stroke="var(--color-copa)" stroke-width="1.1" /><path d="M50 40A8 8 0 0 1 66 40ZM58 40L64.93 36M58 40L62 33.07M58 40L58 32M58 40L54 33.07M58 40L51.07 36" fill="none" stroke="var(--color-copa)" stroke-width="1.1" /><path d="M66 40A8 8 0 0 1 82 40ZM74 40L80.93 36M74 40L78 33.07M74 40L74 32M74 40L70 33.07M74 40L67.07 36" fill="none" stroke="var(--color-copa)" stroke-width="1.1" /><path d="M82 40A8 8 0 0 1 98 40ZM90 40L96.93 36M90 40L94 33.07M90 40L90 32M90 40L86 33.07M90 40L83.07 36" fill="none" stroke="var(--color-copa)" stroke-width="1.1" /><path d="M-6 56A8 8 0 0 1 10 56ZM2 56L8.93 52M2 56L6 49.07M2 56L2 48M2 56L-2 49.07M2 56L-4.93 52" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M10 56A8 8 0 0 1 26 56ZM18 56L24.93 52M18 56L22 49.07M18 56L18 48M18 56L14 49.07M18 56L11.07 52" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M26 56A8 8 0 0 1 42 56ZM34 56L40.93 52M34 56L38 49.07M34 56L34 48M34 56L30 49.07M34 56L27.07 52" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M42 56A8 8 0 0 1 58 56ZM50 56L56.93 52M50 56L54 49.07M50 56L50 48M50 56L46 49.07M50 56L43.07 52" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M58 56A8 8 0 0 1 74 56ZM66 56L72.93 52M66 56L70 49.07M66 56L66 48M66 56L62 49.07M66 56L59.07 52" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M74 56A8 8 0 0 1 90 56ZM82 56L88.93 52M82 56L86 49.07M82 56L82 48M82 56L78 49.07M82 56L75.07 52" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M90 56A8 8 0 0 1 106 56ZM98 56L104.93 52M98 56L102 49.07M98 56L98 48M98 56L94 49.07M98 56L91.07 52" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M2 72A8 8 0 0 1 18 72ZM10 72L16.93 68M10 72L14 65.07M10 72L10 64M10 72L6 65.07M10 72L3.07 68" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M18 72A8 8 0 0 1 34 72ZM26 72L32.93 68M26 72L30 65.07M26 72L26 64M26 72L22 65.07M26 72L19.07 68" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M34 72A8 8 0 0 1 50 72ZM42 72L48.93 68M42 72L46 65.07M42 72L42 64M42 72L38 65.07M42 72L35.07 68" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M50 72A8 8 0 0 1 66 72ZM58 72L64.93 68M58 72L62 65.07M58 72L58 64M58 72L54 65.07M58 72L51.07 68" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M66 72A8 8 0 0 1 82 72ZM74 72L80.93 68M74 72L78 65.07M74 72L74 64M74 72L70 65.07M74 72L67.07 68" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /><path d="M82 72A8 8 0 0 1 98 72ZM90 72L96.93 68M90 72L94 65.07M90 72L90 64M90 72L86 65.07M90 72L83.07 68" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" /></g></g>
        <rect x="8" y="8" width="84" height="140" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif

    {{-- "Guarda pampa": rombos escalonados en franjas, como la guarda de un poncho; la forma geométrica, sin copiar ningún tejido. --}}
    @if ($dorsos->hace('guarda-pampa'))
    <symbol id="dorso-guarda-pampa" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <g clip-path="url(#recorte-dorso)"><path d="M8 12H92M8 40H92" stroke="var(--color-tinta)" stroke-width="1.2" /><path d="M14 14V17H11V20H8V23H5V26H2V29H5V32H8V35H11V38H14V35H17V32H20V29H23V26H26V23H23V20H20V17H17V14H14Z" fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="0.9" /><path d="M14 21.6V23.8H11.8V26.0H9.600000000000001V28.2H11.8V30.4H14.0V28.2H16.2V26.0H18.4V23.8H16.2V21.6H14.0Z" fill="var(--color-naipe)" /><path d="M38 14V17H35V20H32V23H29V26H26V29H29V32H32V35H35V38H38V35H41V32H44V29H47V26H50V23H47V20H44V17H41V14H38Z" fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="0.9" /><path d="M38 21.6V23.8H35.8V26.0H33.599999999999994V28.2H35.8V30.4H38.0V28.2H40.2V26.0H42.400000000000006V23.8H40.2V21.6H38.0Z" fill="var(--color-naipe)" /><path d="M62 14V17H59V20H56V23H53V26H50V29H53V32H56V35H59V38H62V35H65V32H68V29H71V26H74V23H71V20H68V17H65V14H62Z" fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="0.9" /><path d="M62 21.6V23.8H59.8V26.0H57.599999999999994V28.2H59.8V30.4H62.0V28.2H64.2V26.0H66.4V23.8H64.2V21.6H62.0Z" fill="var(--color-naipe)" /><path d="M86 14V17H83V20H80V23H77V26H74V29H77V32H80V35H83V38H86V35H89V32H92V29H95V26H98V23H95V20H92V17H89V14H86Z" fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="0.9" /><path d="M86 21.6V23.8H83.8V26.0H81.6V28.2H83.8V30.4H86.0V28.2H88.2V26.0H90.4V23.8H88.2V21.6H86.0Z" fill="var(--color-naipe)" /><path d="M8 42H92M8 70H92" stroke="var(--color-tinta)" stroke-width="1.2" /><path d="M14 44V47H11V50H8V53H5V56H2V59H5V62H8V65H11V68H14V65H17V62H20V59H23V56H26V53H23V50H20V47H17V44H14Z" fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="0.9" /><path d="M14 51.6V53.800000000000004H11.8V56.00000000000001H9.600000000000001V58.20000000000001H11.8V60.40000000000001H14.0V58.20000000000001H16.2V56.00000000000001H18.4V53.800000000000004H16.2V51.6H14.0Z" fill="var(--color-naipe)" /><path d="M38 44V47H35V50H32V53H29V56H26V59H29V62H32V65H35V68H38V65H41V62H44V59H47V56H50V53H47V50H44V47H41V44H38Z" fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="0.9" /><path d="M38 51.6V53.800000000000004H35.8V56.00000000000001H33.599999999999994V58.20000000000001H35.8V60.40000000000001H38.0V58.20000000000001H40.2V56.00000000000001H42.400000000000006V53.800000000000004H40.2V51.6H38.0Z" fill="var(--color-naipe)" /><path d="M62 44V47H59V50H56V53H53V56H50V59H53V62H56V65H59V68H62V65H65V62H68V59H71V56H74V53H71V50H68V47H65V44H62Z" fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="0.9" /><path d="M62 51.6V53.800000000000004H59.8V56.00000000000001H57.599999999999994V58.20000000000001H59.8V60.40000000000001H62.0V58.20000000000001H64.2V56.00000000000001H66.4V53.800000000000004H64.2V51.6H62.0Z" fill="var(--color-naipe)" /><path d="M86 44V47H83V50H80V53H77V56H74V59H77V62H80V65H83V68H86V65H89V62H92V59H95V56H98V53H95V50H92V47H89V44H86Z" fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="0.9" /><path d="M86 51.6V53.800000000000004H83.8V56.00000000000001H81.6V58.20000000000001H83.8V60.40000000000001H86.0V58.20000000000001H88.2V56.00000000000001H90.4V53.800000000000004H88.2V51.6H86.0Z" fill="var(--color-naipe)" /><g transform="rotate(180 50 78)"><path d="M8 12H92M8 40H92" stroke="var(--color-tinta)" stroke-width="1.2" /><path d="M14 14V17H11V20H8V23H5V26H2V29H5V32H8V35H11V38H14V35H17V32H20V29H23V26H26V23H23V20H20V17H17V14H14Z" fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="0.9" /><path d="M14 21.6V23.8H11.8V26.0H9.600000000000001V28.2H11.8V30.4H14.0V28.2H16.2V26.0H18.4V23.8H16.2V21.6H14.0Z" fill="var(--color-naipe)" /><path d="M38 14V17H35V20H32V23H29V26H26V29H29V32H32V35H35V38H38V35H41V32H44V29H47V26H50V23H47V20H44V17H41V14H38Z" fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="0.9" /><path d="M38 21.6V23.8H35.8V26.0H33.599999999999994V28.2H35.8V30.4H38.0V28.2H40.2V26.0H42.400000000000006V23.8H40.2V21.6H38.0Z" fill="var(--color-naipe)" /><path d="M62 14V17H59V20H56V23H53V26H50V29H53V32H56V35H59V38H62V35H65V32H68V29H71V26H74V23H71V20H68V17H65V14H62Z" fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="0.9" /><path d="M62 21.6V23.8H59.8V26.0H57.599999999999994V28.2H59.8V30.4H62.0V28.2H64.2V26.0H66.4V23.8H64.2V21.6H62.0Z" fill="var(--color-naipe)" /><path d="M86 14V17H83V20H80V23H77V26H74V29H77V32H80V35H83V38H86V35H89V32H92V29H95V26H98V23H95V20H92V17H89V14H86Z" fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="0.9" /><path d="M86 21.6V23.8H83.8V26.0H81.6V28.2H83.8V30.4H86.0V28.2H88.2V26.0H90.4V23.8H88.2V21.6H86.0Z" fill="var(--color-naipe)" /><path d="M8 42H92M8 70H92" stroke="var(--color-tinta)" stroke-width="1.2" /><path d="M14 44V47H11V50H8V53H5V56H2V59H5V62H8V65H11V68H14V65H17V62H20V59H23V56H26V53H23V50H20V47H17V44H14Z" fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="0.9" /><path d="M14 51.6V53.800000000000004H11.8V56.00000000000001H9.600000000000001V58.20000000000001H11.8V60.40000000000001H14.0V58.20000000000001H16.2V56.00000000000001H18.4V53.800000000000004H16.2V51.6H14.0Z" fill="var(--color-naipe)" /><path d="M38 44V47H35V50H32V53H29V56H26V59H29V62H32V65H35V68H38V65H41V62H44V59H47V56H50V53H47V50H44V47H41V44H38Z" fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="0.9" /><path d="M38 51.6V53.800000000000004H35.8V56.00000000000001H33.599999999999994V58.20000000000001H35.8V60.40000000000001H38.0V58.20000000000001H40.2V56.00000000000001H42.400000000000006V53.800000000000004H40.2V51.6H38.0Z" fill="var(--color-naipe)" /><path d="M62 44V47H59V50H56V53H53V56H50V59H53V62H56V65H59V68H62V65H65V62H68V59H71V56H74V53H71V50H68V47H65V44H62Z" fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="0.9" /><path d="M62 51.6V53.800000000000004H59.8V56.00000000000001H57.599999999999994V58.20000000000001H59.8V60.40000000000001H62.0V58.20000000000001H64.2V56.00000000000001H66.4V53.800000000000004H64.2V51.6H62.0Z" fill="var(--color-naipe)" /><path d="M86 44V47H83V50H80V53H77V56H74V59H77V62H80V65H83V68H86V65H89V62H92V59H95V56H98V53H95V50H92V47H89V44H86Z" fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="0.9" /><path d="M86 51.6V53.800000000000004H83.8V56.00000000000001H81.6V58.20000000000001H83.8V60.40000000000001H86.0V58.20000000000001H88.2V56.00000000000001H90.4V53.800000000000004H88.2V51.6H86.0Z" fill="var(--color-naipe)" /></g></g>
        <rect x="8" y="8" width="84" height="140" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif

    {{-- "Cuadros": el dorso clásico de la baraja española, una trama escocesa en diagonal en Espada y tinta. Dibujada para este mazo. --}}
    @if ($dorsos->hace('cuadros'))
    <symbol id="dorso-cuadros" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="11.5" y="11.5" width="77" height="133" rx="1.5" fill="url(#trama-cuadros)" stroke="var(--color-tinta)" stroke-width="0.8" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif

    {{-- "Aros": aros que se cruzan y dibujan pétalos, en Basto. --}}
    @if ($dorsos->hace('aros'))
    <symbol id="dorso-aros" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="11.5" y="11.5" width="77" height="133" rx="1.5" fill="url(#trama-aros)" stroke="var(--color-tinta)" stroke-width="0.8" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif

    {{-- "Estrella": estrellas de ocho puntas en Copa, con un rombo de tinta entre cada cuatro. --}}
    @if ($dorsos->hace('estrella'))
    <symbol id="dorso-estrella" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="11.5" y="11.5" width="77" height="133" rx="1.5" fill="url(#trama-estrella)" stroke="var(--color-tinta)" stroke-width="0.8" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif









    {{-- "Grabado": un rosetón de líneas finas y una guarda de ondas cruzadas, como la trama de seguridad de un billete viejo, en Espada. Premio del nivel 39. --}}
    @if ($dorsos->hace('grabado'))
    <symbol id="dorso-grabado" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="11.5" y="11.5" width="77" height="133" rx="1.5" fill="none" stroke="var(--color-espada)" stroke-width="0.6" /><rect x="19.5" y="19.5" width="61" height="117" rx="1" fill="none" stroke="var(--color-espada)" stroke-width="0.6" /><g clip-path="url(#recorte-dorso)"><path d="M15 15.5L16 16.9L16.9 17.5L17.9 16.9L18.9 15.5L19.9 14.1L20.8 13.5L21.8 14.1L22.8 15.5L23.8 16.9L24.7 17.5L25.7 16.9L26.7 15.5L27.6 14.1L28.6 13.5L29.6 14.1L30.6 15.5L31.5 16.9L32.5 17.5L33.5 16.9L34.4 15.5L35.4 14.1L36.4 13.5L37.4 14.1L38.3 15.5L39.3 16.9L40.3 17.5L41.2 16.9L42.2 15.5L43.2 14.1L44.2 13.5L45.1 14.1L46.1 15.5L47.1 16.9L48.1 17.5L49 16.9L50 15.5L51 14.1L51.9 13.5L52.9 14.1L53.9 15.5L54.9 16.9L55.8 17.5L56.8 16.9L57.8 15.5L58.8 14.1L59.7 13.5L60.7 14.1L61.7 15.5L62.6 16.9L63.6 17.5L64.6 16.9L65.6 15.5L66.5 14.1L67.5 13.5L68.5 14.1L69.4 15.5L70.4 16.9L71.4 17.5L72.4 16.9L73.3 15.5L74.3 14.1L75.3 13.5L76.2 14.1L77.2 15.5L78.2 16.9L79.2 17.5L80.1 16.9L81.1 15.5L82.1 14.1L83.1 13.5L84 14.1L85 15.5M15 15.5L16 14.1L16.9 13.5L17.9 14.1L18.9 15.5L19.9 16.9L20.8 17.5L21.8 16.9L22.8 15.5L23.8 14.1L24.7 13.5L25.7 14.1L26.7 15.5L27.6 16.9L28.6 17.5L29.6 16.9L30.6 15.5L31.5 14.1L32.5 13.5L33.5 14.1L34.4 15.5L35.4 16.9L36.4 17.5L37.4 16.9L38.3 15.5L39.3 14.1L40.3 13.5L41.2 14.1L42.2 15.5L43.2 16.9L44.2 17.5L45.1 16.9L46.1 15.5L47.1 14.1L48.1 13.5L49 14.1L50 15.5L51 16.9L51.9 17.5L52.9 16.9L53.9 15.5L54.9 14.1L55.8 13.5L56.8 14.1L57.8 15.5L58.8 16.9L59.7 17.5L60.7 16.9L61.7 15.5L62.6 14.1L63.6 13.5L64.6 14.1L65.6 15.5L66.5 16.9L67.5 17.5L68.5 16.9L69.4 15.5L70.4 14.1L71.4 13.5L72.4 14.1L73.3 15.5L74.3 16.9L75.3 17.5L76.2 16.9L77.2 15.5L78.2 14.1L79.2 13.5L80.1 14.1L81.1 15.5L82.1 16.9L83.1 17.5L84 16.9L85 15.5M15.5 22L13.6 23.6L14.2 25.1L16.5 26.7L17.5 28.2L15.8 29.8L13.8 31.3L14 32.9L16.2 34.4L17.5 36L16.2 37.6L14 39.1L13.8 40.7L15.8 42.2L17.5 43.8L16.5 45.3L14.2 46.9L13.6 48.4L15.5 50L17.4 51.6L16.8 53.1L14.5 54.7L13.5 56.2L15.2 57.8L17.2 59.3L17 60.9L14.8 62.4L13.5 64L14.8 65.6L17 67.1L17.2 68.7L15.2 70.2L13.5 71.8L14.5 73.3L16.8 74.9L17.4 76.4L15.5 78L13.6 79.6L14.2 81.1L16.5 82.7L17.5 84.2L15.8 85.8L13.8 87.3L14 88.9L16.2 90.4L17.5 92L16.2 93.6L14 95.1L13.8 96.7L15.8 98.2L17.5 99.8L16.5 101.3L14.2 102.9L13.6 104.4L15.5 106L17.4 107.6L16.8 109.1L14.5 110.7L13.5 112.2L15.2 113.8L17.2 115.3L17 116.9L14.8 118.4L13.5 120L14.8 121.6L17 123.1L17.2 124.7L15.2 126.2L13.5 127.8L14.5 129.3L16.8 130.9L17.4 132.4L15.5 134M15.5 22L17.4 23.6L16.8 25.1L14.5 26.7L13.5 28.2L15.2 29.8L17.2 31.3L17 32.9L14.8 34.4L13.5 36L14.8 37.6L17 39.1L17.2 40.7L15.2 42.2L13.5 43.8L14.5 45.3L16.8 46.9L17.4 48.4L15.5 50L13.6 51.6L14.2 53.1L16.5 54.7L17.5 56.2L15.8 57.8L13.8 59.3L14 60.9L16.2 62.4L17.5 64L16.2 65.6L14 67.1L13.8 68.7L15.8 70.2L17.5 71.8L16.5 73.3L14.2 74.9L13.6 76.4L15.5 78L17.4 79.6L16.8 81.1L14.5 82.7L13.5 84.2L15.2 85.8L17.2 87.3L17 88.9L14.8 90.4L13.5 92L14.8 93.6L17 95.1L17.2 96.7L15.2 98.2L13.5 99.8L14.5 101.3L16.8 102.9L17.4 104.4L15.5 106L13.6 107.6L14.2 109.1L16.5 110.7L17.5 112.2L15.8 113.8L13.8 115.3L14 116.9L16.2 118.4L17.5 120L16.2 121.6L14 123.1L13.8 124.7L15.8 126.2L17.5 127.8L16.5 129.3L14.2 130.9L13.6 132.4L15.5 134" fill="none" stroke="var(--color-espada)" stroke-width="0.5" /><g transform="rotate(180 50 78)"><path d="M15 15.5L16 16.9L16.9 17.5L17.9 16.9L18.9 15.5L19.9 14.1L20.8 13.5L21.8 14.1L22.8 15.5L23.8 16.9L24.7 17.5L25.7 16.9L26.7 15.5L27.6 14.1L28.6 13.5L29.6 14.1L30.6 15.5L31.5 16.9L32.5 17.5L33.5 16.9L34.4 15.5L35.4 14.1L36.4 13.5L37.4 14.1L38.3 15.5L39.3 16.9L40.3 17.5L41.2 16.9L42.2 15.5L43.2 14.1L44.2 13.5L45.1 14.1L46.1 15.5L47.1 16.9L48.1 17.5L49 16.9L50 15.5L51 14.1L51.9 13.5L52.9 14.1L53.9 15.5L54.9 16.9L55.8 17.5L56.8 16.9L57.8 15.5L58.8 14.1L59.7 13.5L60.7 14.1L61.7 15.5L62.6 16.9L63.6 17.5L64.6 16.9L65.6 15.5L66.5 14.1L67.5 13.5L68.5 14.1L69.4 15.5L70.4 16.9L71.4 17.5L72.4 16.9L73.3 15.5L74.3 14.1L75.3 13.5L76.2 14.1L77.2 15.5L78.2 16.9L79.2 17.5L80.1 16.9L81.1 15.5L82.1 14.1L83.1 13.5L84 14.1L85 15.5M15 15.5L16 14.1L16.9 13.5L17.9 14.1L18.9 15.5L19.9 16.9L20.8 17.5L21.8 16.9L22.8 15.5L23.8 14.1L24.7 13.5L25.7 14.1L26.7 15.5L27.6 16.9L28.6 17.5L29.6 16.9L30.6 15.5L31.5 14.1L32.5 13.5L33.5 14.1L34.4 15.5L35.4 16.9L36.4 17.5L37.4 16.9L38.3 15.5L39.3 14.1L40.3 13.5L41.2 14.1L42.2 15.5L43.2 16.9L44.2 17.5L45.1 16.9L46.1 15.5L47.1 14.1L48.1 13.5L49 14.1L50 15.5L51 16.9L51.9 17.5L52.9 16.9L53.9 15.5L54.9 14.1L55.8 13.5L56.8 14.1L57.8 15.5L58.8 16.9L59.7 17.5L60.7 16.9L61.7 15.5L62.6 14.1L63.6 13.5L64.6 14.1L65.6 15.5L66.5 16.9L67.5 17.5L68.5 16.9L69.4 15.5L70.4 14.1L71.4 13.5L72.4 14.1L73.3 15.5L74.3 16.9L75.3 17.5L76.2 16.9L77.2 15.5L78.2 14.1L79.2 13.5L80.1 14.1L81.1 15.5L82.1 16.9L83.1 17.5L84 16.9L85 15.5M15.5 22L13.6 23.6L14.2 25.1L16.5 26.7L17.5 28.2L15.8 29.8L13.8 31.3L14 32.9L16.2 34.4L17.5 36L16.2 37.6L14 39.1L13.8 40.7L15.8 42.2L17.5 43.8L16.5 45.3L14.2 46.9L13.6 48.4L15.5 50L17.4 51.6L16.8 53.1L14.5 54.7L13.5 56.2L15.2 57.8L17.2 59.3L17 60.9L14.8 62.4L13.5 64L14.8 65.6L17 67.1L17.2 68.7L15.2 70.2L13.5 71.8L14.5 73.3L16.8 74.9L17.4 76.4L15.5 78L13.6 79.6L14.2 81.1L16.5 82.7L17.5 84.2L15.8 85.8L13.8 87.3L14 88.9L16.2 90.4L17.5 92L16.2 93.6L14 95.1L13.8 96.7L15.8 98.2L17.5 99.8L16.5 101.3L14.2 102.9L13.6 104.4L15.5 106L17.4 107.6L16.8 109.1L14.5 110.7L13.5 112.2L15.2 113.8L17.2 115.3L17 116.9L14.8 118.4L13.5 120L14.8 121.6L17 123.1L17.2 124.7L15.2 126.2L13.5 127.8L14.5 129.3L16.8 130.9L17.4 132.4L15.5 134M15.5 22L17.4 23.6L16.8 25.1L14.5 26.7L13.5 28.2L15.2 29.8L17.2 31.3L17 32.9L14.8 34.4L13.5 36L14.8 37.6L17 39.1L17.2 40.7L15.2 42.2L13.5 43.8L14.5 45.3L16.8 46.9L17.4 48.4L15.5 50L13.6 51.6L14.2 53.1L16.5 54.7L17.5 56.2L15.8 57.8L13.8 59.3L14 60.9L16.2 62.4L17.5 64L16.2 65.6L14 67.1L13.8 68.7L15.8 70.2L17.5 71.8L16.5 73.3L14.2 74.9L13.6 76.4L15.5 78L17.4 79.6L16.8 81.1L14.5 82.7L13.5 84.2L15.2 85.8L17.2 87.3L17 88.9L14.8 90.4L13.5 92L14.8 93.6L17 95.1L17.2 96.7L15.2 98.2L13.5 99.8L14.5 101.3L16.8 102.9L17.4 104.4L15.5 106L13.6 107.6L14.2 109.1L16.5 110.7L17.5 112.2L15.8 113.8L13.8 115.3L14 116.9L16.2 118.4L17.5 120L16.2 121.6L14 123.1L13.8 124.7L15.8 126.2L17.5 127.8L16.5 129.3L14.2 130.9L13.6 132.4L15.5 134" fill="none" stroke="var(--color-espada)" stroke-width="0.5" /></g></g><path d="M68.1 78L66.7 79.7L63.7 80.8L61.4 81.4L61.4 82.6L63.5 85L65.8 88L66.2 90.2L64.2 90.5L61.2 89.4L59.1 88.6L59 90.1L60.4 93.8L61.4 97.9L60.9 99.7L58.9 98.3L56.5 95.2L55 93.5L54.7 95.4L54.9 99.8L54.6 103.9L53.5 104.6L52 101.5L50.8 97.2L50 95.3L49.2 97.2L48 101.5L46.5 104.6L45.4 103.9L45.1 99.8L45.3 95.4L45 93.5L43.5 95.2L41.1 98.3L39.1 99.7L38.6 97.9L39.6 93.8L41 90.1L40.9 88.6L38.8 89.4L35.8 90.5L33.8 90.2L34.2 88L36.5 85L38.6 82.6L38.6 81.4L36.3 80.8L33.3 79.7L31.9 78L33.3 76.3L36.3 75.2L38.6 74.6L38.6 73.4L36.5 71L34.2 68L33.8 65.8L35.8 65.5L38.8 66.6L40.9 67.4L41 65.9L39.6 62.2L38.6 58.1L39.1 56.3L41.1 57.7L43.5 60.8L45 62.5L45.3 60.6L45.1 56.2L45.4 52.1L46.5 51.4L48 54.5L49.2 58.8L50 60.7L50.8 58.8L52 54.5L53.5 51.4L54.6 52.1L54.9 56.2L54.7 60.6L55 62.5L56.5 60.8L58.9 57.7L60.9 56.3L61.4 58.1L60.4 62.2L59 65.9L59.1 67.4L61.2 66.6L64.2 65.5L66.2 65.8L65.8 68L63.5 71L61.4 73.4L61.4 74.6L63.7 75.2L66.7 76.3ZM68.8 78L66.1 79.6L63.3 80.7L62.6 81.8L64.2 83.8L66.7 86.7L67.9 89.3L66.6 90.5L63.6 90L61 89.2L60.3 90L61.4 93.2L62.8 97.4L62.8 100.3L61.1 100L58.5 97.4L56.5 95.1L55.8 95.9L55.9 99.9L55.9 104.5L55 106.6L53.5 104.6L51.9 100.5L50.8 97.5L50 98.3L49 102.3L47.6 106.2L46.2 106.9L45.5 103.7L45.4 98.9L45.1 95.9L44 96.6L41.8 99.7L39.5 102L38.3 101.2L38.8 97.5L40 93.2L40.4 90.8L38.9 90.9L36 92.3L33.4 92.6L32.9 90.8L34.7 87.7L37 84.7L37.7 83L36 82.3L32.9 81.4L30.8 79.9L31.2 78L33.9 76.4L36.7 75.3L37.4 74.2L35.8 72.2L33.3 69.3L32.1 66.7L33.4 65.5L36.4 66L39 66.8L39.7 66L38.6 62.8L37.2 58.6L37.2 55.7L38.9 56L41.5 58.6L43.5 60.9L44.2 60.1L44.1 56.1L44.1 51.5L45 49.4L46.5 51.4L48.1 55.5L49.2 58.5L50 57.7L51 53.7L52.4 49.8L53.8 49.1L54.5 52.3L54.6 57.1L54.9 60.1L56 59.4L58.2 56.3L60.5 54L61.7 54.8L61.2 58.5L60 62.8L59.6 65.2L61.1 65.1L64 63.7L66.6 63.4L67.1 65.2L65.3 68.3L63 71.3L62.3 73L64 73.7L67.1 74.6L69.2 76.1ZM68.5 78L65.5 79.5L64 80.8L65 82.5L67.5 85.1L69.4 88L68.9 89.9L66.2 90.2L63.1 89.6L61.7 89.9L62.4 92.5L63.9 96.6L64.6 100.2L63.3 101.1L60.7 99.3L58.3 96.8L57.1 96.7L57.1 99.8L57.3 104.7L56.7 107.9L55.2 107.3L53.3 103.7L51.9 100.1L50.9 99.7L50 103L48.7 107.4L47.3 109.4L46.2 107.3L45.7 102.6L45.4 98.8L44.5 98.3L42.6 101L40.2 104L38.5 104.3L38.3 101.3L39.2 96.7L39.9 93.3L39 92.7L36.3 94L33.4 94.9L32.1 93.8L33.1 90.7L35.3 87.3L36.6 84.9L35.6 83.9L32.6 83.3L29.9 82L29.4 80.1L31.5 78L34.5 76.5L36 75.2L35 73.5L32.5 70.9L30.6 68L31.1 66.1L33.8 65.8L36.9 66.4L38.3 66.1L37.6 63.5L36.1 59.4L35.4 55.8L36.7 54.9L39.3 56.7L41.7 59.2L42.9 59.3L42.9 56.2L42.7 51.3L43.3 48.1L44.8 48.7L46.7 52.3L48.1 55.9L49.1 56.3L50 53L51.3 48.6L52.7 46.6L53.8 48.7L54.3 53.4L54.6 57.2L55.5 57.7L57.4 55L59.8 52L61.5 51.7L61.7 54.7L60.8 59.3L60.1 62.7L61 63.3L63.7 62L66.6 61.1L67.9 62.2L66.9 65.3L64.7 68.7L63.4 71.1L64.4 72.1L67.4 72.7L70.1 74L70.6 75.9ZM67.8 78L65.6 79.6L65.8 81.2L68.2 83.5L70.6 86.4L70.9 88.8L68.8 89.8L65.6 89.7L63.4 89.8L63.5 91.7L65.1 95.6L66.2 99.7L65.5 101.7L63.1 100.8L60.3 98.5L58.6 97.6L58.3 99.8L58.6 104.5L58.3 108.7L57 109.5L55 106.7L53.2 102.8L52 101.3L51.1 103.7L50 108.2L48.6 111.4L47.2 110.7L46.3 106.5L45.8 102L45 100.4L43.4 102.3L41 105.7L39 107.1L38.1 105.1L38.7 100.5L39.4 96.4L39 94.8L36.7 95.8L33.6 97.1L31.6 96.7L31.8 94L33.7 90.3L35.4 87.2L35.1 85.7L32.5 85.1L29.4 84.2L28 82.4L29.3 80.1L32.2 78L34.4 76.4L34.2 74.8L31.8 72.5L29.4 69.6L29.1 67.2L31.2 66.2L34.4 66.3L36.6 66.2L36.5 64.3L34.9 60.4L33.8 56.3L34.5 54.3L36.9 55.2L39.7 57.5L41.4 58.4L41.7 56.2L41.4 51.5L41.7 47.3L43 46.5L45 49.3L46.8 53.2L48 54.7L48.9 52.3L50 47.8L51.4 44.6L52.8 45.3L53.7 49.5L54.2 54L55 55.6L56.6 53.7L59 50.3L61 48.9L61.9 50.9L61.3 55.5L60.6 59.6L61 61.2L63.3 60.2L66.4 58.9L68.4 59.3L68.2 62L66.3 65.7L64.6 68.8L64.9 70.3L67.5 70.9L70.6 71.8L72 73.6L70.7 75.9ZM67.4 78L66.9 79.7L68.8 81.8L71.5 84.5L72.7 87.3L71.3 89L68.1 89.4L65.4 89.6L64.8 91L66.1 94.4L67.7 98.7L67.7 101.6L65.7 101.9L62.7 100L60.4 98.6L59.6 99.9L59.9 104.1L60 108.9L59 111.1L57 109.4L54.9 105.7L53.3 103.2L52.3 104.5L51.3 108.8L50 112.9L48.5 113.6L47.2 110.3L46.4 105.5L45.6 102.8L44.3 103.8L42.1 107.1L39.8 109.6L38.4 108.7L38.4 104.5L39 99.8L38.9 97.2L37.1 97.6L34.1 99.2L31.5 99.6L30.9 97.5L32.2 93.6L34.1 89.9L34.4 87.8L32.4 87.1L29.2 86.5L27 85L27.4 82.5L30 80L32.6 78L33.1 76.3L31.2 74.2L28.5 71.5L27.3 68.7L28.7 67L31.9 66.6L34.6 66.4L35.2 65L33.9 61.6L32.3 57.3L32.3 54.4L34.3 54.1L37.3 56L39.6 57.4L40.4 56.1L40.1 51.9L40 47.1L41 44.9L43 46.6L45.1 50.3L46.7 52.8L47.7 51.5L48.7 47.2L50 43.1L51.5 42.4L52.8 45.7L53.6 50.5L54.4 53.2L55.7 52.2L57.9 48.9L60.2 46.4L61.6 47.3L61.6 51.5L61 56.2L61.1 58.8L62.9 58.4L65.9 56.8L68.5 56.4L69.1 58.5L67.8 62.4L65.9 66.1L65.6 68.2L67.6 68.9L70.8 69.5L73 71L72.6 73.5L70 76ZM68.2 78L69.4 79.9L72.2 82.5L74.2 85.3L73.6 87.6L70.8 88.8L67.7 89.2L66.3 90.3L67.2 93.1L69 97.3L69.7 101L68.2 102.3L65.2 101.2L62.4 99.6L61.1 100.1L61.2 103.6L61.6 108.6L61 112L59.1 111.6L56.8 108.4L54.8 105.4L53.6 105.5L52.7 109.2L51.5 113.8L50 115.9L48.5 113.8L47.3 109.2L46.4 105.5L45.2 105.4L43.2 108.4L40.9 111.6L39 112L38.4 108.6L38.8 103.6L38.9 100.1L37.6 99.6L34.8 101.2L31.8 102.3L30.3 101L31 97.3L32.8 93.1L33.7 90.3L32.3 89.2L29.2 88.8L26.4 87.6L25.8 85.3L27.8 82.5L30.6 79.9L31.8 78L30.6 76.1L27.8 73.5L25.8 70.7L26.4 68.4L29.2 67.2L32.3 66.8L33.7 65.7L32.8 62.9L31 58.7L30.3 55L31.8 53.7L34.8 54.8L37.6 56.4L38.9 55.9L38.8 52.4L38.4 47.4L39 44L40.9 44.4L43.2 47.6L45.2 50.6L46.4 50.5L47.3 46.8L48.5 42.2L50 40.1L51.5 42.2L52.7 46.8L53.6 50.5L54.8 50.6L56.8 47.6L59.1 44.4L61 44L61.6 47.4L61.2 52.4L61.1 55.9L62.4 56.4L65.2 54.8L68.2 53.7L69.7 55L69 58.7L67.2 62.9L66.3 65.7L67.7 66.8L70.8 67.2L73.6 68.4L74.2 70.7L72.2 73.5L69.4 76.1ZM70.2 78L72.8 80.3L75.3 83.1L75.7 85.8L73.4 87.6L70.2 88.4L68.1 89.4L68.3 91.8L70.1 95.7L71.4 99.8L70.6 102.1L67.9 102L64.8 100.5L62.9 100.4L62.6 103.1L63.2 108L63 112.3L61.4 113.3L59 111L56.6 107.7L55.1 106.8L54.1 109.6L53.1 114.4L51.7 117.7L50 117L48.5 112.9L47.4 108.6L46.2 107.3L44.4 109.7L42.1 113.3L40 114.9L38.8 112.6L38.7 107.7L38.9 103.4L38 101.9L35.5 103.1L32.4 104.8L30.3 104.4L30.2 101.2L31.6 96.7L32.8 93.1L32.1 91.4L29.3 91L26.1 90.3L24.7 88.3L25.8 85.3L28.5 82.3L30.4 80L29.8 78L27.2 75.7L24.7 72.9L24.3 70.2L26.6 68.4L29.8 67.6L31.9 66.6L31.7 64.2L29.9 60.3L28.6 56.2L29.4 53.9L32.1 54L35.2 55.5L37.1 55.6L37.4 52.9L36.8 48L37 43.7L38.6 42.7L41 45L43.4 48.3L44.9 49.2L45.9 46.4L46.9 41.6L48.3 38.3L50 39L51.5 43.1L52.6 47.4L53.8 48.7L55.6 46.3L57.9 42.7L60 41.1L61.2 43.4L61.3 48.3L61.1 52.6L62 54.1L64.5 52.9L67.6 51.2L69.7 51.6L69.8 54.8L68.4 59.3L67.2 62.9L67.9 64.6L70.7 65L73.9 65.7L75.3 67.7L74.2 70.7L71.5 73.7L69.6 76ZM73.2 78L76.1 80.6L77.4 83.5L75.9 85.9L72.8 87.3L70.1 88.4L69.6 90.4L71.2 93.9L73 98.2L73 101.4L70.7 102.2L67.4 101.2L64.9 100.7L64.2 102.6L64.7 107.1L64.9 112L63.8 114.3L61.4 113.1L58.7 110L56.7 108.2L55.6 110L54.8 114.6L53.5 118.9L51.8 119.7L50 116.5L48.5 112L47.3 109.6L45.7 111L43.5 114.7L41.2 117.4L39.6 116.4L39 112L39 107L38.4 104.5L36.3 105.1L33.3 107.1L30.6 107.6L29.7 105.2L30.6 100.7L32 96.4L31.8 94L29.5 93.4L26.2 93L23.9 91.5L24.2 88.6L26.5 85.1L28.7 82.3L29 80.1L26.8 78L23.9 75.4L22.6 72.5L24.1 70.1L27.2 68.7L29.9 67.6L30.4 65.6L28.8 62.1L27 57.8L27 54.6L29.3 53.8L32.6 54.8L35.1 55.3L35.8 53.4L35.3 48.9L35.1 44L36.2 41.7L38.6 42.9L41.3 46L43.3 47.8L44.4 46L45.2 41.4L46.5 37.1L48.2 36.3L50 39.5L51.5 44L52.7 46.4L54.3 45L56.5 41.3L58.8 38.6L60.4 39.6L61 44L61 49L61.6 51.5L63.7 50.9L66.7 48.9L69.4 48.4L70.3 50.8L69.4 55.3L68 59.6L68.2 62L70.5 62.6L73.8 63L76.1 64.5L75.8 67.4L73.5 70.9L71.3 73.7L71 75.9ZM76.7 78L78.7 80.9L78.2 83.7L75.4 85.7L72.3 87.1L71.1 88.9L72.3 92.1L74.3 96.2L75 100L73.4 101.9L70.2 101.6L67.2 101L65.9 102.2L66.2 106.1L66.7 111.2L66.1 114.7L63.9 114.8L61.1 112.2L58.7 109.9L57.3 110.6L56.5 114.7L55.4 119.6L53.8 121.8L51.8 119.9L50 115.5L48.5 112.1L47 112.4L45.1 115.9L42.7 119.4L40.7 119.7L39.6 116.2L39.3 111L38.8 107.5L37.2 107.2L34.3 109.2L31.3 110.5L29.6 109L29.9 104.9L31.1 100.1L31.5 96.9L29.8 95.8L26.5 95.7L23.6 94.7L22.9 92L24.6 88.4L27 85L27.9 82.4L26.3 80.4L23.3 78L21.3 75.1L21.8 72.3L24.6 70.3L27.7 68.9L28.9 67.1L27.7 63.9L25.7 59.8L25 56L26.6 54.1L29.8 54.4L32.8 55L34.1 53.8L33.8 49.9L33.3 44.8L33.9 41.3L36.1 41.2L38.9 43.8L41.3 46.1L42.7 45.4L43.5 41.3L44.6 36.4L46.2 34.2L48.2 36.1L50 40.5L51.5 43.9L53 43.6L54.9 40.1L57.3 36.6L59.3 36.3L60.4 39.8L60.7 45L61.2 48.5L62.8 48.8L65.7 46.8L68.7 45.5L70.4 47L70.1 51.1L68.9 55.9L68.5 59.1L70.2 60.2L73.5 60.3L76.4 61.3L77.1 64L75.4 67.6L73 71L72.1 73.6L73.7 75.6ZM79.7 78L80.1 81L77.9 83.6L74.7 85.5L72.8 87.3L73.4 90.1L75.4 94L76.9 98.2L76 100.9L73.1 101.5L69.7 101.1L67.8 101.7L67.7 104.9L68.4 110L68.3 114.4L66.6 115.8L63.7 114.1L60.9 111.5L59.1 111.3L58.2 114.6L57.3 119.7L55.9 123.2L53.9 122.7L51.8 118.9L50 115L48.4 114.1L46.6 116.9L44.4 121L42.2 122.6L40.6 120.3L39.9 115.2L39.4 110.8L38 109.6L35.4 111.2L32.3 113.2L30 112.8L29.5 109.2L30.4 104.2L31.1 100.1L29.9 98.4L26.9 98.3L23.6 97.8L22 95.7L22.9 92L25.3 88.1L26.7 85.1L25.8 82.8L23 80.7L20.3 78L19.9 75L22.1 72.4L25.3 70.5L27.2 68.7L26.6 65.9L24.6 62L23.1 57.8L24 55.1L26.9 54.5L30.3 54.9L32.2 54.3L32.3 51.1L31.6 46L31.7 41.6L33.4 40.2L36.3 41.9L39.1 44.5L40.9 44.7L41.8 41.4L42.7 36.3L44.1 32.8L46.1 33.3L48.2 37.1L50 41L51.6 41.9L53.4 39.1L55.6 35L57.8 33.4L59.4 35.7L60.1 40.8L60.6 45.2L62 46.4L64.6 44.8L67.7 42.8L70 43.2L70.5 46.8L69.6 51.8L68.9 55.9L70.1 57.6L73.1 57.7L76.4 58.2L78 60.3L77.1 64L74.7 67.9L73.3 70.9L74.2 73.2L77 75.3ZM81.7 78L80.3 81L77.2 83.5L74.8 85.5L74.6 88L76.4 91.7L78.4 95.9L78.4 99.4L76 100.9L72.5 100.9L69.9 101.3L69.3 103.8L70 108.5L70.5 113.5L69.2 116.2L66.5 115.5L63.3 113.2L61.1 112.2L59.9 114.6L59.3 119.5L58.1 124L56.1 125L53.8 122.1L51.7 118L50 116.1L48.3 118L46.2 122.1L43.9 125L41.9 124L40.7 119.5L40.1 114.6L38.9 112.2L36.7 113.2L33.5 115.5L30.8 116.2L29.5 113.5L30 108.5L30.7 103.8L30.1 101.3L27.5 100.9L24 100.9L21.6 99.4L21.6 95.9L23.6 91.7L25.4 88L25.2 85.5L22.8 83.5L19.7 81L18.3 78L19.7 75L22.8 72.5L25.2 70.5L25.4 68L23.6 64.3L21.6 60.1L21.6 56.6L24 55.1L27.5 55.1L30.1 54.7L30.7 52.2L30 47.5L29.5 42.5L30.8 39.8L33.5 40.5L36.7 42.8L38.9 43.8L40.1 41.4L40.7 36.5L41.9 32L43.9 31L46.2 33.9L48.3 38L50 39.9L51.7 38L53.8 33.9L56.1 31L58.1 32L59.3 36.5L59.9 41.4L61.1 43.8L63.3 42.8L66.5 40.5L69.2 39.8L70.5 42.5L70 47.5L69.3 52.2L69.9 54.7L72.5 55.1L76 55.1L78.4 56.6L78.4 60.1L76.4 64.3L74.6 68L74.8 70.5L77.2 72.5L80.3 75ZM82.4 78L79.7 81L76.9 83.4L75.9 85.9L77.4 89.2L79.7 93.3L80.5 97.3L78.8 99.7L75.4 100.4L72.3 100.7L71.1 102.7L71.6 106.9L72.4 112.1L71.8 115.9L69.4 116.5L66.1 114.7L63.3 113.2L61.8 114.6L61.2 119.1L60.3 124.2L58.6 126.7L56.1 125L53.7 121.1L51.7 118.3L50 119.1L48.1 123L45.8 126.8L43.6 127.3L42 123.7L41 118.6L39.9 115.2L37.9 115.3L35 117.7L31.9 119.3L30 117.7L29.8 113.1L30.4 107.9L30.2 104.5L28.1 103.6L24.6 103.8L21.6 103L20.7 100L22.1 95.6L24.1 91.4L24.5 88.4L22.6 86.3L19.3 84.1L17.1 81.3L17.6 78L20.3 75L23.1 72.6L24.1 70.1L22.6 66.8L20.3 62.7L19.5 58.7L21.2 56.3L24.6 55.6L27.7 55.3L28.9 53.3L28.4 49.1L27.6 43.9L28.2 40.1L30.6 39.5L33.9 41.3L36.7 42.8L38.2 41.4L38.8 36.9L39.7 31.8L41.4 29.3L43.9 31L46.3 34.9L48.3 37.7L50 36.9L51.9 33L54.2 29.2L56.4 28.7L58 32.3L59 37.4L60.1 40.8L62.1 40.7L65 38.3L68.1 36.7L70 38.3L70.2 42.9L69.6 48.1L69.8 51.5L71.9 52.4L75.4 52.2L78.4 53L79.3 56L77.9 60.4L75.9 64.6L75.5 67.6L77.4 69.7L80.7 71.9L82.9 74.7Z" fill="none" stroke="var(--color-espada)" stroke-width="0.4" /><ellipse cx="50" cy="78" rx="11" ry="15" fill="var(--color-naipe)" stroke="var(--color-espada)" stroke-width="0.8" /><ellipse cx="50" cy="78" rx="8.6" ry="12.4" fill="none" stroke="var(--color-copa)" stroke-width="0.6" /><g transform="translate(45 73) scale(0.36)"><path d="M4 24V8M8 4H24M24 8V24M24 24H8" fill="none" stroke="var(--color-tinta)" stroke-width="2.4" stroke-linecap="round" /><g fill="var(--color-copa)"><circle cx="4" cy="5" r="2.8" /><circle cx="23" cy="4" r="2.8" /><circle cx="24" cy="23" r="2.8" /><circle cx="5" cy="24" r="2.8" /></g></g>
        <rect x="8" y="8" width="84" height="140" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif

    {{-- "Monograma": la V y la C entrelazadas en dos cartuchos (uno dado vuelta) sobre un fondo punteado, a la manera de Owen Jones para De La Rue, en Copa. Premio del nivel 35. --}}
    @if ($dorsos->hace('monograma'))
    <symbol id="dorso-monograma" viewBox="0 0 100 156">
        <pattern id="trama-monograma" width="4" height="4" patternUnits="userSpaceOnUse"><circle cx="1" cy="1" r=".45" fill="var(--color-copa)" opacity=".55" /><circle cx="3" cy="3" r=".45" fill="var(--color-copa)" opacity=".55" /></pattern>
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="11.5" y="11.5" width="77" height="133" rx="1.5" fill="url(#trama-monograma)" stroke="var(--color-copa)" stroke-width="0.8" />
        <g transform="rotate(0 50 78)"><path d="M14 14H22M14 14V22" fill="none" stroke="var(--color-copa)" stroke-width="1"/><circle cx="14" cy="14" r="1.6" fill="var(--color-copa)"/></g><g transform="rotate(180 50 78)"><path d="M14 14H22M14 14V22" fill="none" stroke="var(--color-copa)" stroke-width="1"/><circle cx="14" cy="14" r="1.6" fill="var(--color-copa)"/></g><g transform="translate(100 0) scale(-1 1)"><g transform="rotate(0 50 78)"><path d="M14 14H22M14 14V22" fill="none" stroke="var(--color-copa)" stroke-width="1"/><circle cx="14" cy="14" r="1.6" fill="var(--color-copa)"/></g><g transform="rotate(180 50 78)"><path d="M14 14H22M14 14V22" fill="none" stroke="var(--color-copa)" stroke-width="1"/><circle cx="14" cy="14" r="1.6" fill="var(--color-copa)"/></g></g><path d="M50 19C47 15 47 12 50 10C53 12 53 15 50 19Z" fill="var(--color-copa)" transform="rotate(0 50 46)"/><path d="M50 19C47 15 47 12 50 10C53 12 53 15 50 19Z" fill="var(--color-copa)" transform="rotate(90 50 46)"/><path d="M50 19C47 15 47 12 50 10C53 12 53 15 50 19Z" fill="var(--color-copa)" transform="rotate(270 50 46)"/><ellipse cx="50" cy="46" rx="23" ry="25" fill="var(--color-naipe)" stroke="var(--color-copa)" stroke-width="1.3"/><ellipse cx="50" cy="46" rx="20" ry="22" fill="none" stroke="var(--color-copa)" stroke-width="0.5"/><g transform="translate(33.5 31)"><path d="M30.5 6.8A10.5 10.5 0 1 0 30.5 23.2" fill="none" stroke="var(--color-copa)" stroke-width="1.3" stroke-linecap="round"/><path d="M15.6 9.8A10.5 10.5 0 0 0 15.6 20.2" fill="none" stroke="var(--color-copa)" stroke-width="3.2" stroke-linecap="round"/><path d="M3 3L11.5 27L20 3" fill="none" stroke="var(--color-naipe)" stroke-width="4.6" stroke-linejoin="round"/><path d="M3 3L11.5 27" stroke="var(--color-copa)" stroke-width="3" stroke-linecap="butt"/><path d="M20 3L11.5 27" stroke="var(--color-copa)" stroke-width="1.2"/><path d="M0 3H6.5M16.8 3H23.2M30.5 4.6V9" stroke="var(--color-copa)" stroke-width="1.1" stroke-linecap="round"/></g><g transform="rotate(180 50 78)"><path d="M50 19C47 15 47 12 50 10C53 12 53 15 50 19Z" fill="var(--color-copa)" transform="rotate(0 50 46)"/><path d="M50 19C47 15 47 12 50 10C53 12 53 15 50 19Z" fill="var(--color-copa)" transform="rotate(90 50 46)"/><path d="M50 19C47 15 47 12 50 10C53 12 53 15 50 19Z" fill="var(--color-copa)" transform="rotate(270 50 46)"/><ellipse cx="50" cy="46" rx="23" ry="25" fill="var(--color-naipe)" stroke="var(--color-copa)" stroke-width="1.3"/><ellipse cx="50" cy="46" rx="20" ry="22" fill="none" stroke="var(--color-copa)" stroke-width="0.5"/><g transform="translate(33.5 31)"><path d="M30.5 6.8A10.5 10.5 0 1 0 30.5 23.2" fill="none" stroke="var(--color-copa)" stroke-width="1.3" stroke-linecap="round"/><path d="M15.6 9.8A10.5 10.5 0 0 0 15.6 20.2" fill="none" stroke="var(--color-copa)" stroke-width="3.2" stroke-linecap="round"/><path d="M3 3L11.5 27L20 3" fill="none" stroke="var(--color-naipe)" stroke-width="4.6" stroke-linejoin="round"/><path d="M3 3L11.5 27" stroke="var(--color-copa)" stroke-width="3" stroke-linecap="butt"/><path d="M20 3L11.5 27" stroke="var(--color-copa)" stroke-width="1.2"/><path d="M0 3H6.5M16.8 3H23.2M30.5 4.6V9" stroke="var(--color-copa)" stroke-width="1.1" stroke-linecap="round"/></g></g><path d="M50 72L56 78L50 84L44 78Z" fill="var(--color-copa)"/><path d="M50 74.6L53.4 78L50 81.4L46.6 78Z" fill="var(--color-naipe)"/><circle cx="38" cy="78" r="1.4" fill="var(--color-copa)"/><circle cx="62" cy="78" r="1.4" fill="var(--color-copa)"/>
        <rect x="8" y="8" width="84" height="140" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif

    {{-- "Las que mandan": las bravas cruzadas en un medallón de dos cabezas, como una figura, sobre Copa. El premio más alto, nivel 40. --}}
    @if ($dorsos->hace('las-que-mandan'))
    <symbol id="dorso-las-que-mandan" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="var(--color-copa)" /><g clip-path="url(#recorte-dorso)"><path d="M-132 8L8 148M-126 8L14 148M-120 8L20 148M-114 8L26 148M-108 8L32 148M-102 8L38 148M-96 8L44 148M-90 8L50 148M-84 8L56 148M-78 8L62 148M-72 8L68 148M-66 8L74 148M-60 8L80 148M-54 8L86 148M-48 8L92 148M-42 8L98 148M-36 8L104 148M-30 8L110 148M-24 8L116 148M-18 8L122 148M-12 8L128 148M-6 8L134 148M0 8L140 148M6 8L146 148M12 8L152 148M18 8L158 148M24 8L164 148M30 8L170 148M36 8L176 148M42 8L182 148M48 8L188 148M54 8L194 148M60 8L200 148M66 8L206 148M72 8L212 148M78 8L218 148M84 8L224 148M90 8L230 148M8 8L-132 148M14 8L-126 148M20 8L-120 148M26 8L-114 148M32 8L-108 148M38 8L-102 148M44 8L-96 148M50 8L-90 148M56 8L-84 148M62 8L-78 148M68 8L-72 148M74 8L-66 148M80 8L-60 148M86 8L-54 148M92 8L-48 148M98 8L-42 148M104 8L-36 148M110 8L-30 148M116 8L-24 148M122 8L-18 148M128 8L-12 148M134 8L-6 148M140 8L0 148M146 8L6 148M152 8L12 148M158 8L18 148M164 8L24 148M170 8L30 148M176 8L36 148M182 8L42 148M188 8L48 148M194 8L54 148M200 8L60 148M206 8L66 148M212 8L72 148M218 8L78 148M224 8L84 148M230 8L90 148" stroke="var(--color-naipe)" stroke-width="0.35" opacity=".35" /></g><rect x="12" y="12" width="76" height="132" rx="2" fill="none" stroke="var(--color-naipe)" stroke-width="1" /><rect x="14.5" y="14.5" width="71" height="127" rx="1.5" fill="none" stroke="var(--color-naipe)" stroke-width="0.5" /><ellipse cx="50" cy="78" rx="29" ry="46" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.4" /><ellipse cx="50" cy="78" rx="26" ry="43" fill="none" stroke="var(--color-copa)" stroke-width="0.7" /><g clip-path="url(#recorte-dorso)"><g transform="translate(50 78) rotate(-30)"><use href="#palo-espada" x="-10" y="-33" width="20" height="30" /></g><g transform="translate(50 78) rotate(30)"><use href="#palo-basto" x="-9" y="-32" width="18" height="27" /></g><use href="#palo-oro" x="44" y="34" width="12" height="18" /><text x="20" y="27" font-family="Chivo, sans-serif" font-weight="900" font-size="9" fill="var(--color-naipe)">7</text><text x="80" y="27" font-family="Chivo, sans-serif" font-weight="900" font-size="9" fill="var(--color-naipe)" text-anchor="end">1</text><g transform="rotate(180 50 78)"><g transform="translate(50 78) rotate(-30)"><use href="#palo-espada" x="-10" y="-33" width="20" height="30" /></g><g transform="translate(50 78) rotate(30)"><use href="#palo-basto" x="-9" y="-32" width="18" height="27" /></g><use href="#palo-oro" x="44" y="34" width="12" height="18" /><text x="20" y="27" font-family="Chivo, sans-serif" font-weight="900" font-size="9" fill="var(--color-naipe)">7</text><text x="80" y="27" font-family="Chivo, sans-serif" font-weight="900" font-size="9" fill="var(--color-naipe)" text-anchor="end">1</text></g></g>
        <rect x="8" y="8" width="84" height="140" rx="3" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif



    {{-- Tanda 5, los palos de trazo de fósforo: la línea con cabeza de los íconos, en el color de cada palo. --}}
    @if (App\View\MazoEnUso::hace('palos', 'fosforo'))
    <symbol id="palo-oro-fosforo" viewBox="0 0 20 30">
        <g stroke-linecap="round" stroke-linejoin="round" fill="none">
            <path d="M7 7.5H13L17.5 12V18L13 22.5H7L2.5 18V12Z" stroke="var(--color-tinta)" stroke-width="3.8" />
            <path d="M7 7.5H13L17.5 12V18L13 22.5H7L2.5 18V12Z" stroke="var(--color-oro)" stroke-width="2" />
            <circle cx="10" cy="15" r="2.6" fill="var(--color-oro)" stroke="var(--color-tinta)" stroke-width="1.1" />
        </g>
    </symbol>
    <symbol id="palo-copa-fosforo" viewBox="0 0 20 30">
        <g stroke-linecap="round" stroke-linejoin="round" fill="none">
            <path d="M3.5 4H16.5M5 4 7.8 13H12.2L15 4M10 13V19M5.5 26.5H14.5" stroke="var(--color-tinta)" stroke-width="3.8" />
            <path d="M3.5 4H16.5M5 4 7.8 13H12.2L15 4M10 13V19M5.5 26.5H14.5" stroke="var(--color-copa)" stroke-width="2" />
            <circle cx="10" cy="22.6" r="2.4" fill="var(--color-copa)" stroke="var(--color-tinta)" stroke-width="1.1" />
        </g>
    </symbol>
    <symbol id="palo-espada-fosforo" viewBox="0 0 20 30">
        <g stroke-linecap="round" stroke-linejoin="round" fill="none">
            <path d="M10 2.5V19.5M4.5 19.5H15.5M10 19.5V23.5" stroke="var(--color-tinta)" stroke-width="3.8" />
            <path d="M10 2.5V19.5M4.5 19.5H15.5M10 19.5V23.5" stroke="var(--color-espada)" stroke-width="2" />
            <circle cx="10" cy="26.6" r="2.5" fill="var(--color-espada)" stroke="var(--color-tinta)" stroke-width="1.1" />
        </g>
    </symbol>
    <symbol id="palo-basto-fosforo" viewBox="0 0 20 30">
        <g stroke-linecap="round" stroke-linejoin="round" fill="none">
            <path d="M6.5 27.5 13.5 6M8.8 17.5 13 20.5" stroke="var(--color-tinta)" stroke-width="3.8" />
            <path d="M6.5 27.5 13.5 6M8.8 17.5 13 20.5" stroke="var(--color-basto)" stroke-width="2" />
            <circle cx="14.6" cy="3.6" r="2.9" fill="var(--color-basto)" stroke="var(--color-tinta)" stroke-width="1.1" />
        </g>
    </symbol>
    @endif




    {{-- Tanda 5, las figuras de medio cuerpo (Retratos): cada una en su marco en arco; el gorro, el caballo o la corona las distinguen. --}}
    @if (App\View\MazoEnUso::hace('figuras', 'medio'))
    <symbol id="figura-sota-medio" viewBox="0 0 100 156">
        <clipPath id="retrato-sota"><path d="M16 128V58A34 34 0 0 1 84 58V128Z" /></clipPath>
        <g clip-path="url(#retrato-sota)"><g transform="translate(44 128) scale(1.16) translate(-44 -128)" stroke="var(--color-tinta)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round">
            <path d="M18 128 24 104Q44 94 64 104L70 128Z" fill="currentColor" />
            <path d="M44 106V128" fill="none" />
            <path d="M39 89H49V100H39Z" fill="var(--color-naipe)" />
            <path d="M32 100Q44 110 56 100 50 96 44 100 38 96 32 100Z" fill="var(--color-naipe)" />
            <path d="M60 106 70 86 76 89 67 110Z" fill="currentColor" />
            <circle cx="73" cy="84" r="3.5" fill="var(--color-naipe)" />
            <circle cx="44" cy="78" r="13" fill="var(--color-naipe)" />
            <path d="M29 75Q30 58 44 57Q58 58 60 72Q46 66 29 75Z" fill="currentColor" />
            <circle cx="44" cy="55.5" r="3.2" fill="currentColor" />
        </g></g>
        <g transform="translate(44 128) scale(1.16) translate(-44 -128)" fill="var(--color-tinta)"><circle cx="39.5" cy="80" r="1.5" /><circle cx="48.5" cy="80" r="1.5" /></g>
        <path d="M16 128V58A34 34 0 0 1 84 58V128Z" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    <symbol id="figura-caballo-medio" viewBox="0 0 100 156">
        <clipPath id="retrato-caballo"><path d="M16 128V58A34 34 0 0 1 84 58V128Z" /></clipPath>
        <g clip-path="url(#retrato-caballo)"><g transform="translate(48 128) scale(1.1) translate(-48 -128)" stroke="var(--color-tinta)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round">
            <path d="M18 128 22 108Q24 100 30 98L20 96Q15 94 17 88L30 72 33 62 39 70Q48 76 48 92L46 128Z" fill="var(--color-naipe)" />
            <path d="M39 70Q48 76 48 92L46 128H42L44 94Q44 80 37 74Z" fill="currentColor" />
            <path d="M18 92 32 86" fill="none" />
            <path d="M48 128 52 108Q62 102 72 108L76 128Z" fill="currentColor" />
            <path d="M70 110 76 92 81 94 76 112Z" fill="currentColor" />
            <circle cx="79" cy="89" r="3.2" fill="var(--color-naipe)" />
            <circle cx="62" cy="93" r="10" fill="var(--color-naipe)" />
            <path d="M51 91Q52 78 62 77Q72 78 73 89Q62 85 51 91Z" fill="currentColor" />
        </g></g>
        <g transform="translate(48 128) scale(1.1) translate(-48 -128)" fill="var(--color-tinta)"><circle cx="29" cy="80" r="1.6" /><circle cx="58.5" cy="94" r="1.3" /><circle cx="65.5" cy="94" r="1.3" /></g>
        <path d="M16 128V58A34 34 0 0 1 84 58V128Z" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    <symbol id="figura-rey-medio" viewBox="0 0 100 156">
        <clipPath id="retrato-rey"><path d="M16 128V58A34 34 0 0 1 84 58V128Z" /></clipPath>
        <g clip-path="url(#retrato-rey)"><g transform="translate(44 128) scale(1.16) translate(-44 -128)" stroke="var(--color-tinta)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round">
            <path d="M16 128 22 104Q44 92 66 104L72 128Z" fill="currentColor" />
            <path d="M44 108V128" fill="none" />
            <path d="M62 106 72 88 78 91 69 110Z" fill="currentColor" />
            <circle cx="75" cy="85" r="3.5" fill="var(--color-naipe)" />
            <path d="M26 102Q44 114 62 102 56 96 44 101 32 96 26 102Z" fill="var(--color-naipe)" />
            <circle cx="44" cy="80" r="13" fill="var(--color-naipe)" />
            <path d="M35 87Q36 99 44 101 52 99 53 87 44 92 35 87Z" fill="var(--color-tinta)" />
            <path d="M30 70V52L36 60 44 48 52 60 58 52V70Z" fill="currentColor" />
        </g></g>
        <g transform="translate(44 128) scale(1.16) translate(-44 -128)" fill="var(--color-tinta)"><circle cx="39.5" cy="79" r="1.5" /><circle cx="48.5" cy="79" r="1.5" /></g>
        <path d="M16 128V58A34 34 0 0 1 84 58V128Z" fill="none" stroke="var(--color-tinta)" stroke-width="1.5" />
    </symbol>
    @endif




    {{-- Tanda 5, las figuras de cuerpo entero (los mazos de fósforo): de pie, sueltas como las de fábrica; el gorro, el caballo o la corona las distinguen. --}}
    @if (App\View\MazoEnUso::hace('figuras', 'entero'))
    <symbol id="figura-sota-entero" viewBox="0 0 100 156">
        <g transform="translate(50 147) scale(1.15) translate(-50 -128)" stroke="var(--color-tinta)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round">
            <path d="M33 102H39V122H33ZM45 102H51V122H45Z" fill="var(--color-naipe)" />
            <path d="M30 122H40V128H30ZM44 122H54V128H44Z" fill="var(--color-tinta)" />
            <path d="M31 60Q42 54 53 60L57 102H27Z" fill="currentColor" />
            <path d="M28.5 82H55.5" fill="none" />
            <path d="M33 61Q42 68 51 61 46 58 42 61 38 58 33 61Z" fill="var(--color-naipe)" />
            <path d="M52 62 65 66 64 73 52 70Z" fill="currentColor" />
            <circle cx="67.5" cy="69.5" r="3.4" fill="var(--color-naipe)" />
            <circle cx="42" cy="48" r="9.5" fill="var(--color-naipe)" />
            <path d="M32 46Q33 32 42 32Q52 33 52 44Q42 40 32 46Z" fill="currentColor" />
            <circle cx="42" cy="30.5" r="2.8" fill="currentColor" />
        </g>
        <g transform="translate(50 147) scale(1.15) translate(-50 -128)" fill="var(--color-tinta)"><circle cx="38.5" cy="49.5" r="1.3" /><circle cx="45.5" cy="49.5" r="1.3" /></g>
    </symbol>
    <symbol id="figura-caballo-entero" viewBox="0 0 100 156">
        <g transform="translate(46 147) scale(1.05) translate(-50 -128)" stroke="var(--color-tinta)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round">
            <path d="M63 92Q72 97 68 112 65 102 63 100Z" fill="var(--color-naipe)" />
            <path d="M26 106H32V124H26ZM34 106H40V124H34ZM48 106H54V124H48ZM56 106H62V124H56Z" fill="var(--color-naipe)" />
            <path d="M26 124H32V128H26ZM34 124H40V128H34ZM48 124H54V128H48ZM56 124H62V128H56Z" fill="var(--color-tinta)" />
            <path d="M24 96Q24 86 34 86H54Q64 86 64 96V107H24Z" fill="var(--color-naipe)" />
            <path d="M39 90 36 74Q34 64 26 64 23 64 19 71L17 75Q16 79 20 80L28 80 29 92Z" fill="var(--color-naipe)" />
            <path d="M31 65 33 58 37 67" fill="var(--color-naipe)" />
            <path d="M45 82H58L59 101H53V90H45Z" fill="currentColor" />
            <path d="M52 101H60V104H52Z" fill="var(--color-tinta)" />
            <path d="M44 87 46 66Q51 62 56 66L58 87Z" fill="currentColor" />
            <path d="M55 67 64 60 67 64 57 73Z" fill="currentColor" />
            <circle cx="66.5" cy="61.5" r="3.2" fill="var(--color-naipe)" />
            <circle cx="51" cy="55" r="8" fill="var(--color-naipe)" />
            <path d="M42.5 53.5Q43.5 42 51 42Q59 43 59 52Q51 49 42.5 53.5Z" fill="currentColor" />
            <circle cx="51" cy="40.5" r="2.4" fill="currentColor" />
        </g>
        <g transform="translate(46 147) scale(1.05) translate(-50 -128)" fill="var(--color-tinta)"><circle cx="48.5" cy="56" r="1.2" /><circle cx="53.5" cy="56" r="1.2" /><circle cx="25" cy="70" r="1.3" /></g>
    </symbol>
    <symbol id="figura-rey-entero" viewBox="0 0 100 156">
        <g transform="translate(50 147) scale(1.15) translate(-50 -128)" stroke="var(--color-tinta)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round">
            <path d="M28 66Q42 58 56 66L64 128H20Z" fill="currentColor" />
            <path d="M42 76V128M21 120H63" fill="none" />
            <path d="M54 68 67 70 66 77 55 76Z" fill="currentColor" />
            <circle cx="69" cy="73.5" r="3.4" fill="var(--color-naipe)" />
            <path d="M29 68Q42 78 55 68 49 63 42 67 35 63 29 68Z" fill="var(--color-naipe)" />
            <circle cx="42" cy="52" r="10" fill="var(--color-naipe)" />
            <path d="M33.5 56Q34.5 67 42 69 49.5 67 50.5 56 42 60 33.5 56Z" fill="var(--color-tinta)" />
            <path d="M31.5 45V31L36.5 38 42 28 47.5 38 52.5 31V45Z" fill="currentColor" />
        </g>
        <g transform="translate(50 147) scale(1.15) translate(-50 -128)" fill="var(--color-tinta)"><circle cx="38.5" cy="51" r="1.3" /><circle cx="45.5" cy="51" r="1.3" /></g>
    </symbol>
    @endif
</svg>
