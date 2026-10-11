{{--
    La apertura de la aplicación instalada. Al abrirla, el sistema muestra una pantalla quieta con el ícono
    y el nombre sobre el paño; esta la continúa: el mismo paño y los mismos cuatro fósforos en el centro.
    Las cabezas se encienden de a una, el nombre acusa el golpe de la cuarta y todo se funde a la portada
    (resources/js/apertura.js).

    Solo se ve si el layout puso "abriendo" en la raíz: instalada, una vez por apertura y sin movimiento
    reducido. Es un adorno de la entrada: no dice nada que la página no diga, así que el lector de pantalla no la lee.
--}}
<div class="apertura sobre-pano" aria-hidden="true">
    <div class="relative">
        <x-isotipo class="size-24" />
        {{-- El nombre cuelga del isotipo, que es lo que queda en el centro, como en la pantalla del sistema. --}}
        <p class="absolute left-1/2 top-full mt-6 -translate-x-1/2">
            <span class="apertura-nombre canto inline-block whitespace-nowrap text-4xl leading-none">Vale Cuatro</span>
        </p>
    </div>
</div>
