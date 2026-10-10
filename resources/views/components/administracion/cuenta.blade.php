@props(['cantidad'])

@php
    // Se dibujan hasta seis grupos de cinco, como en un tanteador entero. El número exacto lo dice la frase de al lado.
    $tope = 30;
    $dibujados = min($cantidad, $tope);
    $grupos = max(1, intdiv($dibujados + 4, 5));
@endphp

{{--
    Una cuenta llevada con fósforos, como se anota en el club: grupos de cinco, cuatro en cuadrado y
    uno cruzado. En cero queda el lugar vacío. Es dibujo: lo que cuenta ya está dicho con palabras.
--}}
<span {{ $attributes->class('inline-flex flex-wrap items-center gap-[0.22em]') }} aria-hidden="true">
    @for ($g = 0; $g < $grupos; $g++)
        <x-fosforos :grupo="$g" :puntos="$dibujados" class="size-[1.45em]" />
    @endfor
    @if ($cantidad > $tope)
        <span class="ml-[0.3em] text-[0.7em] font-semibold">y más</span>
    @endif
</span>
