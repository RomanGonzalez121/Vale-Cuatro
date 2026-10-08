@props(['datos', 'encabezado' => 'h2'])

{{--
    La repetición de una partida, jugada por jugada. Es una sola pieza para los dos lugares donde se ve: el
    cartel que se abre sobre la lista del historial y la página propia (recargar, o entrar directo a su dirección).

    "datos" es una expresión de JavaScript que da lo que arma HistorialController::datos(): el resumen de la
    partida, cómo se nombra al rival y los cuadros. Todo lo que se lee acá sale de ahí (resources/js/repeticion.js);
    por eso el texto no está escrito en la plantilla.

    En pantallas anchas va en dos columnas, la mesa a la izquierda y los datos con los controles a la derecha,
    para que entre entera en la ventana. En el celular va una cosa debajo de la otra: el resultado, la mesa,
    los controles y al final el detalle, así la mesa y los botones se ven juntos sin deslizar.

    El slot es la acción de arriba: "Cerrar" en el cartel, "Volver al historial" en la página.
--}}

<div x-data="repeticion({{ $datos }})" {{ $attributes->class('repeticion') }} @keydown.window="tecla($event)">
    <header class="repeticion-titulo">
        {{ $slot }}

        <{{ $encabezado }} id="titulo-repeticion" class="text-[2rem] font-black leading-[0.98] tracking-tight sm:text-5xl">
            <span x-text="resumen.gano ? 'Ganaste' : 'Perdiste'"></span>
            <span class="whitespace-nowrap tabular-nums" x-text="`${resumen.vos} a ${resumen.ellos}`"></span>
        </{{ $encabezado }}>
    </header>

    {{-- En el celular esto se lee al final, debajo de los controles: arriba quedan juntos la mesa y los botones. --}}
    <div class="repeticion-detalle">
        <p class="text-lg leading-relaxed [overflow-wrap:anywhere] max-lg:text-base">
            <span x-text="detalle"></span>
            <span class="font-semibold" x-show="resumen.cierre" x-text="resumen.cierre"></span>
        </p>
        {{-- Qué cartas se ven: las del bot, todas; las de otra persona, solo las que se vieron en la mesa. --}}
        <p class="mt-2 leading-relaxed [overflow-wrap:anywhere]" x-text="nota"></p>
    </div>

    <div class="repeticion-escena">
        <div class="mesa repeticion-mesa superficie-pano relative overflow-hidden rounded-xl">
            <div class="relative z-10 grid grid-cols-2 gap-x-4 bg-pano-hondo px-4 py-3 text-[clamp(0.75rem,3.9cqw,1.05rem)]">
                <x-tanteador nombre="Vos" :puntos="0" modelo="estado.tanteo.vos" class="min-w-0" />
                <x-tanteador nombre="Rival" nombre-vivo="resumen.bot ? 'Bot' : resumen.rival" :puntos="0" modelo="estado.tanteo.rival" class="min-w-0" clase-nombre="min-w-0 truncate text-sm font-semibold" />
            </div>

            {{-- El rival: sus cartas si es el bot, dorsos si es otra persona. Los lugares vacíos guardan su ancho. --}}
            <div class="px-4 pt-3" aria-hidden="true">
                <div class="mesa-rival flex justify-center gap-2">
                    @foreach ([0, 1, 2] as $lugar)
                        <div data-orden="{{ $lugar * 2 + 1 }}" x-carta="estado.rival[{{ $lugar }}]"></div>
                    @endforeach
                </div>
            </div>

            <div x-ref="bazas" class="relative px-4 py-4">
                <ol class="mx-auto grid max-w-xl grid-cols-3 gap-3">
                    @foreach ([0, 1, 2] as $numero)
                        <li class="flex flex-col items-center">
                            <p class="mb-2 text-xs font-semibold sm:text-sm">{{ $numero + 1 }}ª baza<span x-text="rotuloDeBaza({{ $numero }})"></span></p>
                            <div class="hueco-baza hueco-rival" :class="{ 'opacity-55': perdio({{ $numero }}, 'rival') }" x-carta="estado.bazas[{{ $numero }}].rival"></div>
                            <div class="hueco-baza hueco-propio" :class="{ 'opacity-55': perdio({{ $numero }}, 'vos') }" x-carta="estado.bazas[{{ $numero }}].vos"></div>
                        </li>
                    @endforeach
                </ol>

                {{-- El canto, con la misma pieza que en la mesa. Uno largo puede venir en dos renglones. --}}
                <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center" aria-hidden="true">
                    <p class="voz canto whitespace-pre text-center" :data-visible="(estado.canto !== null).toString()" :data-quien="voz.quien" :style="{ fontSize: `${voz.letra}px` }"
                        :class="{ 'canto-oro': voz.tono === 'oro', 'canto-ficha canto-copa': voz.tono === 'copa', 'canto-ficha canto-basto': voz.tono === 'basto' }"
                        x-text="voz.texto"></p>
                </div>
            </div>

            <p aria-live="polite" class="flex min-h-12 items-center justify-center px-4 text-center font-semibold leading-snug" x-text="estado.texto"></p>

            <div class="px-4 pb-5 pt-1" aria-hidden="true">
                <div class="abanico mesa-mano">
                    @foreach ([0, 1, 2] as $lugar)
                        <div data-orden="{{ $lugar * 2 }}" x-carta="estado.propias[{{ $lugar }}]"></div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-texto/15" aria-hidden="true">
            <div class="h-full origin-left bg-texto transition-transform duration-200 ease-llegada motion-reduce:transition-none" :style="{ transform: `scaleX(${(indice + 1) / cuadros.length})` }"></div>
        </div>
    </div>

    <div class="repeticion-controles">
        {{-- Los tres controles van en un renglón, de igual ancho: no saltan cuando cambia el texto del medio. --}}
        <div class="grid grid-cols-3 items-center gap-2">
            <button type="button" class="boton boton-linea px-2 max-sm:text-[0.95rem]" :disabled="alPrincipio" @click="anterior()">Anterior</button>
            <button type="button" class="boton boton-tinta px-2 max-sm:text-[0.95rem]" @click="reproduciendo ? pausar() : reproducir()"
                x-text="reproduciendo ? 'Pausar' : (alFinal ? 'De nuevo' : 'Reproducir')">Reproducir</button>
            <button type="button" class="boton boton-linea px-2 max-sm:text-[0.95rem]" :disabled="alFinal" @click="pausar(); siguiente()">Siguiente</button>
        </div>

        {{-- Dónde se está, y el salto de mano en mano: en una partida de veinte manos nadie quiere pasar jugada por jugada. --}}
        <p class="mt-4 font-semibold tabular-nums">
            Mano <span x-text="mano + 1"></span> de <span x-text="manos.length"></span>, jugada <span x-text="jugada"></span> de <span x-text="jugadasDeLaMano"></span>
        </p>
        <div class="mt-1 flex flex-wrap gap-x-7">
            <button type="button" class="enlace-nav cursor-pointer font-bold disabled:pointer-events-none disabled:opacity-45" :disabled="alPrincipio" @click="manoAnterior()">Mano anterior</button>
            <button type="button" class="enlace-nav cursor-pointer font-bold disabled:pointer-events-none disabled:opacity-45" :disabled="mano === manos.length - 1" @click="manoSiguiente()">Mano siguiente</button>
        </div>
        <p class="mt-4 text-[0.95rem] leading-relaxed max-lg:hidden">Con las flechas del teclado pasás de jugada; con Mayús, de mano.</p>
    </div>
</div>
