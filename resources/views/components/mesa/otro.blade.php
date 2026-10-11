@props(['asiento', 'lugar', 'nombre', 'lado' => null, 'acostada' => false])

{{--
    Uno de los otros tres de la mesa de a cuatro: sus cartas boca abajo y, debajo, quién es.
    Con "acostada", las cartas van de costado mirando al centro: es como se ven las de los rivales de los lados.
    Junto al nombre va su ícono de bot, que pasa a ser un reloj mientras piensa (guarda su lugar,
    así nada se corre, y no gira ni late). Si es mano, lleva una ficha en la esquina de sus cartas.
    Mientras canta, el nombre queda marcado: la palabra va al centro y acá se ve de quién es.
    "lado" completa el nombre para el lector de pantalla: los dos rivales se llaman igual a la vista.
--}}
<div {{ $attributes->class('flex flex-col items-center gap-1') }}>
    <div class="relative">
        <div data-dorsos="{{ $lugar }}" @class(['mano-de-otro', 'mano-acostada' => $acostada]) aria-hidden="true"></div>
        <span class="ficha-de-mano" x-show="vista.mano === {{ $asiento }} && ! cierre" x-cloak aria-hidden="true"><x-icono nombre="mano" class="size-3.5" /></span>
    </div>
    <p class="otro-nombre" :data-habla="(voz.visible && habla === {{ $asiento }}).toString()">
        <span class="relative size-4" aria-hidden="true">
            <x-icono nombre="bot" class="absolute inset-0 size-4 transition-opacity ease-llegada" ::class="piensa({{ $asiento }}) ? 'opacity-0 duration-100' : 'opacity-100 duration-200'" />
            <x-icono nombre="tiempo" class="absolute inset-0 size-4 opacity-0 transition-opacity ease-llegada" ::class="piensa({{ $asiento }}) ? 'opacity-100 duration-200' : 'opacity-0 duration-100'" />
        </span>
        <span>
            <span x-text="seFue({{ $asiento }}) ? 'Al mazo' : @js($nombre)">{{ $nombre }}</span>@if ($lado)<span class="sr-only"> {{ $lado }}</span>@endif
        </span>
        <span class="sr-only" x-show="vista.mano === {{ $asiento }}">, es mano</span>
    </p>
</div>
