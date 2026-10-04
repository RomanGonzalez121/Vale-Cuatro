<x-layouts.base titulo="Mesa" superficie="pano" :encabezado="false" :pie="false">
    <x-mazo.plantillas />

    <div x-data="mesa(@js($manos))" class="mesa relative mx-auto flex min-h-dvh max-w-3xl flex-col">
        <div class="flex items-center justify-between gap-3 px-4 py-2.5">
            <a href="{{ route('portada') }}" class="rounded text-lg no-underline" aria-label="Vale Cuatro, ir al inicio"><x-logo /></a>
            <a href="{{ route('portada') }}" class="text-sm font-semibold underline underline-offset-4">Salir de la mesa</a>
        </div>

        <h1 class="sr-only">Mesa contra el bot</h1>

        <section aria-label="Tanteador" class="relative z-10 grid grid-cols-2 gap-x-4 bg-pano-hondo px-4 py-3 text-[clamp(0.8rem,4.1cqw,1.2rem)]">
            <x-tanteador nombre="Vos" :puntos="7" modelo="puntos.vos" />
            <x-tanteador nombre="Bot, nivel 2" :puntos="5" modelo="puntos.rival" />
        </section>

        <section aria-label="Cartas del rival" class="relative px-4">
            <div x-ref="rival" class="mesa-rival flex justify-center gap-2"></div>
            <span x-ref="origen" class="absolute -top-24 right-2 size-px" aria-hidden="true"></span>
        </section>

        <section aria-label="Cartas jugadas" class="relative flex flex-1 flex-col justify-center px-4 py-3">
            <ol x-ref="bazas" class="mx-auto grid w-full max-w-xl grid-cols-3 gap-3">
                @foreach ([0, 1, 2] as $numero)
                    <li class="flex flex-col items-center" :class="{ 'baza-actual': baza === {{ $numero }} && ! cerrada }">
                        <p class="mb-2 text-xs font-semibold sm:text-sm">
                            {{ $numero + 1 }}ª baza<span x-show="resultadoDeBaza({{ $numero }})" x-cloak>, <span x-text="resultadoDeBaza({{ $numero }})"></span></span>
                        </p>
                        <div data-hueco="rival" class="hueco-baza hueco-rival"></div>
                        <div data-hueco="vos" class="hueco-baza hueco-propio"></div>
                    </li>
                @endforeach
            </ol>

            <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center" aria-hidden="true">
                <p class="voz canto text-center" :data-visible="voz.visible" :data-quien="voz.quien" :style="{ fontSize: tamanoDeVoz }"
                    :class="{ 'canto-oro': voz.tono === 'oro', 'canto-ficha canto-copa': voz.tono === 'copa', 'canto-ficha canto-basto': voz.tono === 'basto' }"
                    x-text="voz.texto"></p>
            </div>
        </section>

        <div class="px-4 text-center">
            <p aria-live="polite" class="min-h-6 text-[0.95rem] font-semibold leading-snug" x-text="aviso">Repartiendo.</p>
            <p class="min-h-5 text-sm text-naipe/85" x-text="estadoDelTruco"></p>
        </div>

        <section aria-label="Tu mano" class="px-4 pb-4 pt-1">
            <div x-ref="mano" class="abanico mesa-mano">
                <div></div>
                <div></div>
                <div></div>
            </div>
        </section>

        <section aria-label="Cantos disponibles" class="bg-pano-hondo px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3">
            {{-- Solo se muestran los cantos que existen en este momento de la mano. --}}
            <div class="mesa-acciones mx-auto flex max-w-xl gap-2" x-show="! pendiente">
                <button type="button" class="boton boton-oro" x-show="hayEnvido" :disabled="! puedeEnvido" @click="cantarEnvido()">
                    <x-icono nombre="envido" /> Envido
                </button>
                <button type="button" class="boton boton-copa" x-show="hayTruco" :disabled="! puedeTruco" @click="cantarTruco()">
                    <x-icono nombre="truco" /> <span x-text="cantoDeTruco">Truco</span>
                </button>
                <button type="button" class="boton boton-linea" :disabled="turno !== 'vos' || cerrada" @click="irseAlMazo()" aria-label="Irme al mazo">
                    <x-icono nombre="mazo" /> Al mazo
                </button>
            </div>

            <div class="mesa-acciones mx-auto flex max-w-xl gap-2" x-show="pendiente" x-cloak>
                <button type="button" class="boton boton-basto" @click="responder('quiero')">
                    <x-icono nombre="quiero" /> Quiero
                </button>
                <button type="button" class="boton boton-copa" @click="responder('retruco')">
                    <x-icono nombre="truco" /> Retruco
                </button>
                <button type="button" class="boton boton-copa" @click="responder('no-quiero')">
                    <x-icono nombre="no-quiero" /> No quiero
                </button>
            </div>
        </section>

        <div x-show="fin" x-cloak x-transition.opacity.duration.200ms class="absolute inset-0 z-30 flex items-center justify-center bg-pano-hondo/90 p-6">
            <div class="superficie-naipe w-full max-w-sm rounded-xl p-7 text-center">
                <h2 class="text-3xl font-black tracking-tight" x-text="fin === 'vos' ? 'Ganaste la partida' : 'Ganó el bot'"></h2>
                <p class="mt-2 text-lg tabular-nums">
                    <span x-text="puntos.vos"></span> a <span x-text="puntos.rival"></span>
                </p>
                <div class="mt-6 flex flex-col gap-2.5">
                    <a href="{{ route('mesa') }}" class="boton boton-tinta">Jugar otra partida</a>
                    <a href="{{ route('historial') }}" class="boton boton-linea">Ver el historial</a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.base>
