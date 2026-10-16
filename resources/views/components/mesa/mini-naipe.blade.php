@props(['de'])

@php
    // El palo con el juego del mazo de la página (los de trazo de fósforo llevan su sufijo).
    $palos = \App\Juego\Mazos::de(\App\View\MazoEnUso::actual())['palos'];
    $sufijo = $palos === 'fabrica' ? '' : '-'.$palos;
@endphp

{{--
    Un naipe en chico: el número y el palo, que es lo único que se lee de una carta a ese tamaño.
    "de" es una expresión de Alpine que da qué dibujar: { tipo, numero, palo }. El tipo es "carta"
    (número y palo), "numero" (una seña que no dice el palo), "nada" (el lugar vacío) o "envido".
    Es solo dibujo: lo que dice va escrito al lado, o para el lector de pantalla.
--}}
<span {{ $attributes->class('mini-naipe') }} :data-tipo="{{ $de }}.tipo" aria-hidden="true">
    <span class="mini-naipe-numero" x-show="{{ $de }}.numero" x-text="{{ $de }}.numero ?? ''"></span>
    <svg class="mini-naipe-palo" viewBox="0 0 20 30" x-show="{{ $de }}.palo"><use :href="'#palo-' + ({{ $de }}.palo ?? 'oro') + '{{ $sufijo }}'" /></svg>
    <x-icono nombre="envido" class="mini-naipe-envido" x-show="{{ $de }}.tipo === 'envido'" />
</span>
