{{--
    Botón de modo claro y oscuro: una carta que se da vuelta.
    De día está boca arriba y muestra un oro; de noche muestra el dorso.
--}}
<button type="button" {{ $attributes->class('modo-boton') }} data-cambiar-modo aria-label="Cambiar entre modo claro y oscuro">
    <x-modo.carta />
</button>
