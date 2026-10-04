{{--
    Botón de modo claro y oscuro: una carta que se da vuelta.
    De día está boca arriba y muestra un oro; de noche muestra el dorso.
    El dibujo es más grueso que el de un naipe real para leerse a 24 px.
--}}
<button type="button" {{ $attributes->class('modo-boton') }} data-cambiar-modo aria-label="Cambiar entre modo claro y oscuro">
    <span class="modo-carta" aria-hidden="true">
        <svg viewBox="0 0 100 156">
            <rect x="4" y="4" width="92" height="148" rx="14" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="8" />
            <circle cx="50" cy="78" r="25" fill="var(--color-oro)" stroke="var(--color-tinta)" stroke-width="8" />
            <circle cx="50" cy="78" r="7" fill="var(--color-tinta)" />
        </svg>
        <svg class="modo-dorso" viewBox="0 0 100 156">
            <rect x="4" y="4" width="92" height="148" rx="14" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="8" />
            <rect x="22" y="22" width="56" height="112" rx="5" fill="var(--color-tinta)" />
            <circle cx="50" cy="78" r="13" fill="var(--color-naipe)" />
        </svg>
    </span>
</button>
