{{--
    Piezas del mazo que se dibujan una sola vez por página y se reutilizan con <use>:
    los cuatro palos, las tres figuras, el dorso y el isotipo de fósforos.
--}}
<svg width="0" height="0" class="absolute" aria-hidden="true" focusable="false">
    <defs>
        <pattern id="trama-dorso" width="9" height="9" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
            <path d="M0 0H9M0 0V9" stroke="var(--color-tinta)" stroke-width="1.6" />
        </pattern>
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

    <symbol id="dorso" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="url(#trama-dorso)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <circle cx="50" cy="78" r="18" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <use href="#isotipo" x="37" y="65" width="26" height="26" style="color: var(--color-tinta)" />
    </symbol>
</svg>
