@props(['lugar'])

{{--
    Lo último que dijo otro en la mesa (un canto, "Quiero", "No quiero"), en una fichita de naipe sobre el
    borde de abajo de sus cartas. Es la voz de los jugadores: va en la letra de los cantos. No ocupa lugar.
    El lector de pantalla ya lo oye en el aviso: acá es solo dibujo.
--}}
<p class="dicho" data-dicho="{{ $lugar }}" :data-tono="dicho.tono" x-show="dicho.lugar === '{{ $lugar }}' && ! cierre" x-cloak
    x-transition:leave="se-va" x-transition:leave-end="opacity-0" aria-hidden="true" x-text="dicho.texto"></p>
