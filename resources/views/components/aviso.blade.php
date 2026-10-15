@php
    // Lo que el servidor le dice al jugador después de una acción. Cada color cumple su función de palo:
    // Basto confirma algo hecho, Espada informa y Copa dice que algo no salió.
    $inicial = match (true) {
        (bool) session('hecho') => ['tipo' => 'hecho', 'texto' => session('hecho')],
        (bool) session('aviso') => ['tipo' => 'aviso', 'texto' => session('aviso')],
        default => null,
    };
@endphp

{{--
    El aviso es un cartelito abajo, que no corre la página (pedido de Román el 15 de octubre de 2026: antes
    era una franja arriba que empujaba todo). Sube desde abajo, queda unos segundos y baja por donde vino.
    Se cierra tocándolo; con el mouse encima espera. También lo puede pedir la página sin recargarse, con el
    evento "aviso" (resources/js/aviso-flotante.js). Es lo segundo del sitio que queda quieto en la pantalla,
    después de la barra de abajo del celular: también lo pidió Román.
--}}
<div class="avisos" x-data="avisoFlotante({{ Js::from($inicial) }})" @aviso.window="mostrar($event.detail)" @mouseenter="pausar()" @mouseleave="esperar()">
    <p role="status" class="aviso-flotante" :class="{ 'bg-basto': tipo === 'hecho', 'bg-espada': tipo === 'aviso', 'bg-copa': tipo === 'error' }"
        x-show="visible" x-cloak @click="visible = false"
        x-transition:enter="aviso-entra" x-transition:enter-start="aviso-afuera" x-transition:leave="aviso-sale" x-transition:leave-end="aviso-afuera">
        <span x-show="tipo === 'hecho'"><x-icono nombre="quiero" class="size-5" /></span>
        <span x-show="tipo !== 'hecho'" x-cloak><x-icono nombre="tiempo" class="size-5" /></span>
        <span x-text="texto">{{ $inicial['texto'] ?? '' }}</span>
    </p>
</div>
