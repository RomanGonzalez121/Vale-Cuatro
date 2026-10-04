@php use App\View\Components\Carta; @endphp

{{--
    Las 40 cartas y el dorso como plantillas, para que la mesa pueda dibujar
    cualquier carta a partir de su identificador sin pedirle nada al servidor.
    Están siempre las 40: no dicen nada sobre qué cartas tiene cada jugador.
--}}
@foreach (Carta::mazo() as [$palo, $numero])
    <template data-plantilla="{{ $numero }}-{{ $palo }}"><x-carta :palo="$palo" :numero="$numero" /></template>
@endforeach
<template data-plantilla="dorso"><x-dorso /></template>
