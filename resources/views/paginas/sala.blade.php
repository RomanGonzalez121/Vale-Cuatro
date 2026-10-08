<x-layouts.base titulo="Invitá a alguien" descripcion="Mandale el link de tu partida a quien quieras y jueguen al truco en vivo." superficie="pano">
    <div class="sala-inicio mx-auto grid max-w-6xl gap-x-16 gap-y-10 px-5 pb-16 pt-6 sm:px-8 lg:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)] lg:items-center lg:pb-24 lg:pt-14"
        x-data="sala({ estado: @js(route('sala.estado', $codigo)), mesa: @js(route('mesa')), enlace: @js($enlace) })">
        <section aria-labelledby="titulo-sala">
            <h1 id="titulo-sala" class="text-[clamp(2.5rem,6.4vw,4.5rem)] font-black leading-[0.96] tracking-[-0.035em]">
                Invitá a alguien
            </h1>
            <p class="mt-4 max-w-[34ch] text-lg leading-relaxed">Mandale este link. Cuando se siente, se reparte la primera mano.</p>

            <div class="mt-7 max-w-xl">
                <label for="enlace" class="font-bold">El link de tu partida</label>
                <input id="enlace" x-ref="enlace" type="text" readonly value="{{ $enlace }}" class="sala-enlace mt-2 block w-full" @focus="$el.select()">

                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <button type="button" class="boton boton-naipe min-h-12 px-5" @click="copiar()">
                        <x-icono nombre="invitar" /> Copiar link
                    </button>
                    <button type="button" class="boton boton-linea min-h-12 px-5" x-show="puedeCompartir" x-cloak @click="compartir()">
                        Compartir
                    </button>

                    {{-- Basto confirma algo hecho. Si no se pudo copiar, el link queda elegido para copiarlo a mano. --}}
                    <p role="status" class="min-h-[2.75rem]">
                        <span x-show="copiado === 'copiado'" x-cloak class="inline-flex items-center gap-2 rounded-[0.625rem] bg-basto px-3.5 py-2.5 font-bold text-naipe">
                            <x-icono nombre="quiero" class="size-5" /> Link copiado
                        </span>
                        <span x-show="copiado === 'elegido'" x-cloak class="inline-flex items-center rounded-[0.625rem] bg-espada px-3.5 py-2.5 font-bold text-naipe">
                            Elegido: copialo a mano
                        </span>
                    </p>
                </div>
            </div>
        </section>

        {{-- El lugar del rival: tres huecos con forma de carta. Cuando se sienta alguien, ahí se reparten tres dorsos. --}}
        <section aria-labelledby="titulo-lugar">
            <h2 id="titulo-lugar" class="text-2xl font-black leading-tight tracking-tight sm:text-3xl">Tu rival</h2>

            <div class="mt-5 flex gap-2.5 sm:gap-3.5" aria-hidden="true">
                @foreach (range(0, 2) as $i)
                    <div class="sala-lugar">
                        <template x-if="llego">
                            <x-dorso class="se-reparte" style="--orden: {{ $i }}" />
                        </template>
                    </div>
                @endforeach
            </div>

            <p role="status" aria-live="polite" class="mt-5 max-w-[30ch] text-lg font-bold leading-snug"
                x-text="llego ? `${rival} se sentó. Empieza la partida.` : 'Esperando que alguien se siente.'">
                Esperando que alguien se siente.
            </p>

            <form method="POST" action="{{ route('sala.cancelar', $codigo) }}" class="mt-8">
                @csrf
                <button type="submit" class="boton boton-linea min-h-12 px-5" :disabled="llego">Cancelar sala</button>
            </form>
            <p class="mt-3 max-w-[34ch] text-[0.95rem] leading-relaxed">Si no se sienta nadie en {{ $minutos }} minutos, la sala se cierra sola.</p>
        </section>
    </div>
</x-layouts.base>
