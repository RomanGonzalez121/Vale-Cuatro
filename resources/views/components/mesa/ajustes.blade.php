{{--
    Los botones de la barra: los ajustes y salir. Los ajustes (el modo, el sonido y el ritmo) son
    preferencias que se eligen una vez, así que no ocupan la barra: se despliegan en una ventanita
    debajo, sin tapar la mesa ni detener nada. Se cierra tocando afuera, con Esc o con el mismo botón.
    El toque de afuera se escucha antes que nadie (capture): una carta, al jugarse, frena su propio toque.
--}}
<div class="relative flex items-center gap-2 justify-self-end [grid-area:acciones]" @click.outside.capture="cerrarAjustes(false)" @keydown.escape.window="cerrarAjustes()">
    <button type="button" x-ref="botonDeAjustes" class="boton min-h-10 gap-1.5 px-3 py-1.5 text-sm" :class="ajustando ? 'boton-naipe' : 'boton-linea'"
        @click="ajustando ? cerrarAjustes() : abrirAjustes()" aria-controls="ajustes-de-la-mesa" :aria-expanded="ajustando.toString()">
        <x-icono nombre="ajustes" class="size-5" /> Ajustes
    </button>
    <button type="button" class="boton boton-linea min-h-10 gap-1.5 px-3 py-1.5 text-sm" @click="cerrarAjustes(false); saliendo = true" aria-label="Salir de la mesa">
        <x-icono nombre="salir" class="size-5" /> Salir
    </button>

    {{--
        Cada ajuste es un renglón entero que se toca: a la izquierda qué es y cómo está, con palabras;
        a la derecha su llave. La del modo es la carta que se da vuelta, la misma del resto del sitio.
        Las otras dos son un fósforo en su carril: apagado descansa a un lado, prendido cruza y se
        le enciende la cabeza.
    --}}
    <div id="ajustes-de-la-mesa" x-ref="ajustes" x-show="ajustando" x-cloak
        x-transition:enter="aparece" x-transition:enter-start="desde-chico" x-transition:leave="se-va" x-transition:leave-end="opacity-0"
        class="ajustes superficie-naipe" role="dialog" aria-label="Ajustes de la mesa">
        <button type="button" class="ajuste" data-cambiar-modo>
            <span>
                <span class="block font-bold">Modo</span>
                <span class="text-sm" data-nombre-del-modo>De día</span>
            </span>
            <span class="ajuste-carta"><x-modo.carta /></span>
        </button>
        <button type="button" role="switch" class="ajuste" :aria-checked="sonido.toString()" aria-label="Sonido" data-boton-sonido @click="cambiarSonido()">
            <span>
                <span class="block font-bold">Sonido</span>
                <span class="text-sm" x-text="sonido ? 'Prendido' : 'Apagado'">Apagado</span>
            </span>
            <span class="llave" aria-hidden="true"><span class="llave-fosforo"><span class="llave-cabeza"></span></span></span>
        </button>
        <button type="button" role="switch" class="ajuste" :aria-checked="agil.toString()" aria-label="Ritmo ágil" data-boton-ritmo @click="cambiarRitmo()">
            <span>
                <span class="block font-bold">Ritmo</span>
                <span class="text-sm" x-text="agil ? 'Ágil, sin pausas largas' : 'Tranquilo, da tiempo a leer'">Tranquilo, da tiempo a leer</span>
            </span>
            <span class="llave" aria-hidden="true"><span class="llave-fosforo"><span class="llave-cabeza"></span></span></span>
        </button>
        {{--
            Los avisos al celular ("Te toca"), solo con otra persona enfrente: la máquina juega en el momento.
            El permiso se pide recién al tocar esta llave. Si el navegador no tiene avisos, el renglón no está;
            si la persona los bloqueó, lo dice y no se puede tocar: solo ella los puede volver a permitir.
        --}}
        <button type="button" role="switch" class="ajuste" x-show="pedidos.avisos && avisos !== 'no'" x-cloak
            :aria-checked="(avisos === 'prendidos').toString()" :disabled="avisos === 'bloqueados' || cambiandoAvisos"
            aria-label="Avisos al celular" data-boton-avisos @click="cambiarAvisos()">
            <span>
                <span class="block font-bold">Avisos</span>
                <span class="text-sm" x-text="textoDeAvisos">Apagados</span>
            </span>
            <span class="llave" aria-hidden="true"><span class="llave-fosforo"><span class="llave-cabeza"></span></span></span>
        </button>
        {{-- Solo cuando el navegador avisa que el sitio se puede instalar (resources/js/instalar.js). --}}
        <button type="button" class="ajuste" data-instalar hidden>
            <span>
                <span class="block font-bold">Instalar</span>
                <span class="text-sm">Tenela en tu pantalla de inicio</span>
            </span>
            <span class="ajuste-carta"><x-icono nombre="instalar" class="size-7" /></span>
        </button>
    </div>
</div>
