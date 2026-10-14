@props(['de' => 'fabrica'])

{{-- Una carta boca abajo. "de" dice con qué dorso: el de fábrica, o uno de los que se ganan en la escalera. --}}
<svg {{ $attributes->class('carta') }} viewBox="0 0 100 156" role="img" aria-label="Carta boca abajo">
    <use href="#{{ $de === 'fabrica' ? 'dorso' : 'dorso-'.$de }}" />
</svg>
