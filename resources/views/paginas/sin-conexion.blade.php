{{--
    Lo que se ve cuando se pide una página y no hay conexión. El service worker (public/sw.js) la guarda
    una vez, con sus estilos y sus fuentes, y la muestra en lugar del error del navegador.

    Es la misma para todos: no lleva encabezado ni nada que dependa de quién la pide. Y no promete lo que
    no puede cumplir: el truco se juega en vivo, así que sin conexión no hay mesa ni partida guardada.

    El dibujo es el mazo, con las cartas boca abajo: al abrirse, las tres vuelven a él, que es el camino
    inverso del reparto. "Probar de nuevo" lleva el ícono de repartir porque es lo que va a pasar cuando ande.
--}}

<x-layouts.base titulo="Sin conexión" descripcion="Vale Cuatro se juega en vivo: sin conexión no hay mesa." superficie="pano" :encabezado="false" :pie="false" :barra="false">
    <div class="sin-conexion mx-auto grid min-h-dvh max-w-5xl content-center gap-x-16 gap-y-7 px-6 py-7 sm:gap-y-10 sm:px-8 sm:py-10 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center"
        x-data="sinConexion({ pulso: @js(url('/up')), inicio: @js(route('portada')) })">
        {{-- El logo va primero en las dos disposiciones; en el celular le sigue el mazo y después el texto. --}}
        <a href="{{ route('portada') }}" class="w-fit rounded text-xl no-underline lg:col-span-2" aria-label="Vale Cuatro, ir al inicio"><x-logo /></a>

        <section aria-labelledby="titulo-sin-conexion" class="max-lg:order-3">
            <h1 id="titulo-sin-conexion" class="text-[clamp(3rem,13vw,6rem)] font-black leading-[0.92] tracking-[-0.04em]">
                Sin conexión
            </h1>
            <p class="mt-5 max-w-[30ch] text-lg leading-relaxed sm:text-xl">
                Las cartas volvieron al mazo. El truco se juega en vivo: sin conexión no hay mesa.
            </p>

            <button type="button" class="boton boton-naipe mt-7 min-h-14 px-6 text-lg" :disabled="probando" @click="probar()">
                <x-icono nombre="repartir" /> Probar de nuevo
            </button>

            {{-- Guarda su renglón: cuando aparece el texto el botón no se mueve. --}}
            <p role="status" aria-live="polite" class="mt-4 min-h-[1.6em] font-semibold" x-text="aviso"></p>
        </section>

        <div x-ref="mazo" class="sin-conexion-mazo max-lg:order-2" aria-hidden="true">
            <x-dorso />
            <x-dorso />
            <x-dorso />
        </div>
    </div>
</x-layouts.base>
