<x-layouts.base titulo="Invitá a alguien" descripcion="Mandale el link de tu partida a quien quieras y jueguen al truco en vivo." superficie="pano">
    <div class="sala-inicio mx-auto grid max-w-6xl gap-x-16 gap-y-6 px-5 pb-16 pt-6 sm:gap-y-9 sm:px-8 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] lg:items-center lg:pb-24 lg:pt-14"
        x-data="sala({ estado: @js(route('sala.estado', $codigo)), mesa: @js(route('mesa')), enlace: @js($enlace), partida: @js($partida) })">
        <section aria-labelledby="titulo-sala">
            <h1 id="titulo-sala" class="text-[clamp(2.5rem,6.4vw,4.5rem)] font-black leading-[0.96] tracking-[-0.035em]">
                Invitá a alguien
            </h1>
            <p class="mt-4 max-w-[34ch] text-lg leading-relaxed">Mandale este link. Cuando se siente, se reparte la primera mano.</p>

            <div class="mt-5 max-w-xl sm:mt-7">
                {{-- En el celular el título y el renglón de arriba ya lo dicen: el rótulo queda para el lector de pantalla y la mesa sube. --}}
                <label for="enlace" class="font-bold max-sm:sr-only">El link de tu partida</label>
                <input id="enlace" x-ref="enlace" type="text" readonly value="{{ $enlace }}" class="sala-enlace mt-2 block w-full" @focus="$el.select()">

                <div class="mt-3 flex flex-wrap items-center gap-3">
                    {{--
                        El botón mismo confirma: por unos segundos pasa a Basto y dice "Link copiado". Antes la
                        confirmación era una ficha aparte, que en el celular caía a otro renglón y empujaba la mesa.
                    --}}
                    <button type="button" class="boton boton-naipe min-h-12 px-5" :class="{ 'boton-naipe': copiado !== 'copiado', 'boton-basto': copiado === 'copiado' }" @click="copiar()">
                        <span class="contents" x-show="copiado !== 'copiado'"><x-icono nombre="invitar" /> Copiar link</span>
                        <span class="contents" x-show="copiado === 'copiado'" x-cloak><x-icono nombre="quiero" /> Link copiado</span>
                    </button>
                    <button type="button" class="boton boton-linea min-h-12 px-5" x-show="puedeCompartir" x-cloak @click="compartir()">
                        Compartir
                    </button>

                    <p role="status" class="sr-only" x-text="copiado === 'copiado' ? 'Link copiado' : ''"></p>

                    {{-- Si no se pudo copiar, el link queda elegido para copiarlo a mano. Espada informa. --}}
                    <p x-show="copiado === 'elegido'" x-cloak role="status" class="inline-flex items-center rounded-[0.625rem] bg-espada px-3.5 py-2.5 font-bold text-naipe"
                        x-transition:enter="aparece" x-transition:enter-start="desde-chico" x-transition:leave="se-va" x-transition:leave-end="opacity-0">
                        Elegido: copialo a mano
                    </p>
                </div>
            </div>
        </section>

        {{--
            La mesa servida, esperando: las mismas piezas de la mesa de juego (tanteador, mazo, la línea del
            campo y el aviso en el medio), con los dos lugares vacíos. Mientras se espera no se mueve nada.
            Cuando alguien se sienta, su apodo cae en el tanteador y el mazo reparte tres cartas a cada uno:
            es el único momento coreografiado de la pantalla. El dibujo es de adorno; lo que pasa lo dice el aviso.
        --}}
        <section aria-labelledby="titulo-mesa">
            <h2 id="titulo-mesa" class="sr-only">La mesa</h2>

            <div x-ref="mesa" class="sala-mesa">
                <div class="grid grid-cols-2 gap-x-4 rounded-t-2xl bg-pano-hondo px-4 pb-2.5 pt-2 text-[0.8rem] sm:pb-3 sm:pt-2.5 sm:text-[0.9rem]" aria-hidden="true">
                    <x-tanteador nombre="Vos" class="min-w-0" />
                    <x-tanteador x-ref="tanteoRival" nombre="Tu rival" nombre-vivo="rival ?? 'Tu rival'" class="min-w-0" clase-nombre="min-w-0 truncate text-sm font-semibold" />
                </div>

                <div class="sala-campo">
                    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3" aria-hidden="true">
                        <x-icono nombre="jugador" class="size-5 justify-self-end transition-opacity duration-200 ease-llegada" ::class="llego ? 'opacity-100' : 'opacity-45'" />
                        <div x-ref="lugarRival" class="sala-lugares sala-lugares-rival">
                            @foreach (range(0, 2) as $i)
                                <div class="sala-lugar">
                                    <template x-if="llego">
                                        <x-dorso />
                                    </template>
                                </div>
                            @endforeach
                        </div>
                        {{-- El mazo, contra el borde como en la mesa. De acá sale el reparto. --}}
                        <div x-ref="mazo" class="mesa-mazo justify-self-end">
                            <x-dorso />
                            <x-dorso />
                            <x-dorso />
                        </div>
                    </div>

                    {{-- Guarda siempre dos renglones: cuando llega el rival el texto es más largo y la mesa no salta. --}}
                    <p role="status" aria-live="polite" class="sala-aviso mx-auto flex max-w-[26ch] items-center justify-center text-center font-semibold leading-snug sm:text-lg">
                        <span x-text="llego ? `${rival} se sentó. Empieza la partida.` : 'Esperando que alguien se siente.'">Esperando que alguien se siente.</span>
                    </p>

                    <div x-ref="lugarPropio" class="sala-lugares sala-lugares-propio" aria-hidden="true">
                        @foreach (range(0, 2) as $i)
                            <div class="sala-lugar">
                                <template x-if="llego">
                                    <x-dorso />
                                </template>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-3">
                <form method="POST" action="{{ route('sala.cancelar', $codigo) }}">
                    @csrf
                    <button type="submit" class="boton boton-linea min-h-12 px-5" :disabled="llego">Cancelar sala</button>
                </form>
                <p class="max-w-[30ch] text-[0.95rem] leading-relaxed">Si no se sienta nadie en {{ $minutos }} minutos, la sala se cierra sola.</p>
            </div>
        </section>
    </div>
</x-layouts.base>
