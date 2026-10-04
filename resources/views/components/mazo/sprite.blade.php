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

    {{-- Las figuras usan currentColor: la tinta del palo llega desde la carta. --}}
    <symbol id="figura-sota" viewBox="0 0 60 90">
        <g stroke="var(--color-tinta)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round">
            <path d="M10 89V64Q10 47 30 47 50 47 50 64V89Z" fill="currentColor" />
            <path d="M10 72H50" fill="none" />
            <circle cx="30" cy="31" r="12" fill="var(--color-naipe)" />
            <path d="M17 27Q30 8 43 27Z" fill="currentColor" />
            <path d="M41 22 49 9" fill="none" />
        </g>
        <g fill="var(--color-tinta)">
            <circle cx="26" cy="34" r="1.4" />
            <circle cx="34" cy="34" r="1.4" />
        </g>
    </symbol>

    <symbol id="figura-caballo" viewBox="0 0 60 90">
        <g stroke="var(--color-tinta)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round">
            <path d="M10 89 15 58Q17 42 24 33L22 17 32 27Q43 32 48 45L54 58Q55 66 48 67L40 66 34 56Q35 73 47 89Z" fill="currentColor" />
            <path d="M16 54 9 52M14.5 64 7.5 63M13 75 6 75" fill="none" />
        </g>
        <g fill="var(--color-tinta)">
            <circle cx="37" cy="42" r="1.8" />
            <circle cx="50" cy="60" r="1.2" />
        </g>
    </symbol>

    <symbol id="figura-rey" viewBox="0 0 60 90">
        <g stroke="var(--color-tinta)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round">
            <path d="M6 89V66Q6 47 30 47 54 47 54 66V89Z" fill="currentColor" />
            <path d="M30 47V89" fill="none" />
            <circle cx="30" cy="32" r="12" fill="var(--color-naipe)" />
            <path d="M17 25V7L23.5 15 30 5 36.5 15 43 7V25Z" fill="currentColor" />
            <path d="M23 39Q30 46 37 39" fill="none" />
        </g>
        <g fill="var(--color-tinta)">
            <circle cx="26" cy="33" r="1.4" />
            <circle cx="34" cy="33" r="1.4" />
        </g>
    </symbol>

    <symbol id="dorso" viewBox="0 0 100 156">
        <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <rect x="8" y="8" width="84" height="140" rx="3" fill="url(#trama-dorso)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <circle cx="50" cy="78" r="18" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
        <use href="#isotipo" x="37" y="65" width="26" height="26" style="color: var(--color-tinta)" />
    </symbol>
</svg>
