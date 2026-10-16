@props(['de' => null])

@php
    // Cada uno ve lo suyo: sin decir cuál, una carta boca abajo sale con el dorso de quien está mirando.
    $de ??= \App\Juego\Premios::dorsoPropio();
    // Y lo anota, para que el mazo de la página traiga su plantilla (App\View\DorsosEnUso).
    app(\App\View\DorsosEnUso::class)->usar($de);
@endphp

{{-- Una carta boca abajo. "de" dice con qué dorso, cuando hay que mostrar uno en particular (el perfil, la identidad). --}}
<svg {{ $attributes->class('carta') }} viewBox="0 0 100 156" role="img" aria-label="Carta boca abajo">
    <use href="#{{ $de === 'fabrica' ? 'dorso' : 'dorso-'.$de }}" />
</svg>
