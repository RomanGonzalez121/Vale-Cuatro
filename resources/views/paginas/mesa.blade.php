<x-layouts.base titulo="Mesa" superficie="pano" :encabezado="false" :pie="false">
    <x-mazo.plantillas />

    {{-- La mesa ocupa la pantalla completa y nunca hace scroll: ver .mesa en app.css. --}}
    <div x-data="mesa(@js($manos))" class="mesa mesa-completa relative">
        <h1 class="sr-only">Mesa contra el bot</h1>

        <header class="mesa-barra relative z-10">
            <a href="{{ route('portada') }}" class="justify-self-start rounded text-lg no-underline [grid-area:logo] lg:text-2xl" aria-label="Vale Cuatro, ir al inicio"><x-logo /></a>

            <section aria-label="Tanteador" class="grid grid-cols-2 gap-x-4 text-[clamp(0.8rem,4.1cqw,1.3rem)] [grid-area:tanteo] lg:gap-x-12">
                <x-tanteador nombre="Vos" :puntos="7" modelo="puntos.vos" />
                <x-tanteador nombre="Bot, nivel 2" :puntos="5" modelo="puntos.rival" />
            </section>

            <div class="flex items-center gap-2 justify-self-end [grid-area:acciones]">
                <x-modo />
                <a href="{{ route('portada') }}" class="boton boton-linea min-h-10 gap-1.5 px-3 py-1.5 text-sm" aria-label="Salir de la mesa">
                    <x-icono nombre="salir" class="size-5" /> Salir
                </a>
            </div>
        </header>

        <div class="mesa-campo">
            <section aria-label="Rival" class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 sm:gap-7">
                <p class="flex flex-col items-end gap-1 justify-self-end text-right text-sm font-semibold leading-tight sm:flex-row sm:items-center sm:gap-2 sm:text-base">
                    <x-icono nombre="bot" class="size-5" />
                    Bot, nivel 2
                </p>
                <div x-ref="rival" class="mesa-rival flex justify-center gap-1.5 sm:gap-2"></div>
                {{-- El mazo: de acá salen las cartas al repartir. --}}
                <div x-ref="origen" class="mesa-mazo justify-self-start" aria-hidden="true">
                    <x-dorso />
                    <x-dorso />
                    <x-dorso />
                </div>
            </section>

            <section aria-label="Cartas jugadas" class="relative flex flex-1 flex-col justify-center py-2">
                <ol x-ref="bazas" class="mx-auto grid w-full grid-cols-3 gap-3" style="max-width: calc(var(--b) * 7.5)">
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

            <p aria-live="polite" class="min-h-6 text-center text-[0.95rem] font-semibold leading-snug sm:text-lg" x-text="aviso">Repartiendo.</p>

            {{-- En el celular, la marca de mano y el estado del truco van arriba de las cartas; en pantallas anchas, a los costados. --}}
            <section aria-label="Tu mano" class="grid items-center gap-x-7 pb-4 pt-1 sm:grid-cols-[1fr_auto_1fr]">
                <div class="flex min-h-7 items-center justify-center gap-3 text-sm sm:contents">
                    <p class="inline-flex items-center gap-1.5 rounded-full border border-naipe/40 px-2.5 py-0.5 font-semibold sm:order-1 sm:justify-self-end">
                        <x-icono nombre="mano" class="size-4" /> Sos mano
                    </p>
                    <p class="font-semibold sm:order-3 sm:justify-self-start sm:text-base" x-text="estadoDelTruco"></p>
                </div>
                <div x-ref="mano" class="abanico mesa-mano sm:order-2">
                    <div></div>
                    <div></div>
                    <div></div>
                </div>
            </section>
        </div>

        <section aria-label="Cantos disponibles" class="relative z-10 bg-pano-hondo px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3">
            {{-- Solo se muestran los cantos que existen en este momento de la mano. --}}
            <div class="mesa-acciones mx-auto flex max-w-2xl gap-2" x-show="! pendiente">
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

            <div class="mesa-acciones mx-auto flex max-w-2xl gap-2" x-show="pendiente" x-cloak>
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
