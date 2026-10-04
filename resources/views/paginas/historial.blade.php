@php
    $porDia = collect($partidas)->groupBy('dia');
    $ganadas = collect($partidas)->filter(fn (array $partida) => $partida['vos'] > $partida['ellos'])->count();
    $perdidas = count($partidas) - $ganadas;
@endphp

<x-layouts.base titulo="Historial" descripcion="Tus partidas de Vale Cuatro, para volver a verlas jugada por jugada.">
    <x-mazo.plantillas />

    <div class="mx-auto max-w-5xl px-5 pb-24 sm:px-8">
        <header class="grid items-end gap-8 pb-10 pt-10 sm:pt-16 md:grid-cols-[1fr_auto]">
            <div>
                <h1 class="text-5xl font-black tracking-tight sm:text-7xl">Historial</h1>
                <p class="mt-5 max-w-[56ch] text-lg leading-relaxed">
                    Cada partida queda guardada jugada por jugada. Por eso se puede volver a ver entera.
                </p>
            </div>

            {{-- La tira: una marca por partida, de la más vieja a la más nueva. Alta si la ganaste, baja si la perdiste. --}}
            <div>
                <ul class="flex h-10 items-end gap-1.5" aria-hidden="true">
                    @foreach (array_reverse($partidas) as $partida)
                        <li @class(['w-4 rounded-sm', 'h-10 bg-gana' => $partida['vos'] > $partida['ellos'], 'h-4 bg-pierde' => $partida['vos'] < $partida['ellos']])></li>
                    @endforeach
                </ul>
                <p class="mt-2 font-semibold">{{ $ganadas }} ganadas y {{ $perdidas }} perdidas en las últimas {{ count($partidas) }}</p>
            </div>
        </header>

        @foreach ($porDia as $dia => $delDia)
            <section aria-labelledby="dia-{{ $loop->index }}" class="border-t-2 border-texto pb-6 pt-5">
                <h2 id="dia-{{ $loop->index }}" class="text-xl font-extrabold">{{ $dia }}</h2>

                <ul>
                    @foreach ($delDia as $partida)
                        @php($gano = $partida['vos'] > $partida['ellos'])
                        <li class="grid items-center gap-x-7 gap-y-3 border-b border-texto/15 py-5 last:border-b-0 sm:grid-cols-[9rem_1fr] lg:grid-cols-[9rem_1fr_auto_auto]">
                            <p class="leading-none">
                                <span @class(['block text-sm font-bold', 'text-gana' => $gano, 'text-pierde' => ! $gano])>{{ $gano ? 'Ganaste' : 'Perdiste' }}</span>
                                <span class="mt-1.5 block text-3xl font-black tabular-nums tracking-tight">{{ $partida['vos'] }} a {{ $partida['ellos'] }}</span>
                            </p>
                            <p class="leading-snug">
                                <span class="text-lg font-bold">Contra {{ $partida['rival'] }}</span>
                                <span class="block text-[0.95rem]">{{ $partida['hora'] }}. {{ $partida['manos'] }} manos en {{ $partida['minutos'] }} minutos.</span>
                            </p>
                            <div class="flex w-fit gap-5 rounded-lg bg-pano-hondo px-3.5 py-2.5 text-[0.66rem] sm:col-span-2 lg:col-span-1">
                                <x-tanteador nombre="Vos" :puntos="$partida['vos']" />
                                <x-tanteador nombre="Rival" :puntos="$partida['ellos']" />
                            </div>
                            <a href="#repeticion" class="boton boton-linea min-h-11 w-fit px-4 py-2 text-[0.95rem] sm:col-span-2 lg:col-span-1">
                                <x-icono nombre="repetir" /> Ver de nuevo
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        <section id="repeticion" aria-labelledby="titulo-repeticion" class="scroll-mt-6 border-t-2 border-texto pt-12">
            <h2 id="titulo-repeticion" class="text-4xl font-black tracking-tight sm:text-5xl">Repetición</h2>
            <p class="mt-3 max-w-[56ch] text-lg leading-relaxed">
                Hoy, 21:40, contra Bot, nivel 2. Las tres últimas manos, jugada por jugada.
                También se avanza con las flechas del teclado.
            </p>

            <div x-data="repeticion(@js($pasos))" class="mt-7 max-w-2xl"
                @keydown.left.prevent="anterior()" @keydown.right.prevent="pausar(); siguiente()">
                <div class="mesa superficie-pano relative overflow-hidden rounded-xl">
                    <div class="relative z-10 grid grid-cols-2 gap-x-4 bg-pano-hondo px-4 py-3 text-[clamp(0.75rem,3.9cqw,1.05rem)]">
                        <x-tanteador nombre="Vos" :puntos="0" modelo="estado.tanteo[0]" />
                        <x-tanteador nombre="Bot, nivel 2" :puntos="0" modelo="estado.tanteo[1]" />
                    </div>

                    <div class="px-4" aria-hidden="true">
                        <div class="mesa-rival flex justify-center gap-2">
                            <template x-for="n in estado.rival" :key="n">
                                <div x-carta="'dorso'"></div>
                            </template>
                        </div>
                    </div>

                    <div class="relative px-4 py-5">
                        <ol class="mx-auto grid max-w-xl grid-cols-3 gap-3">
                            @foreach ([0, 1, 2] as $numero)
                                <li class="flex flex-col items-center">
                                    <p class="mb-2 text-xs font-semibold sm:text-sm">{{ $numero + 1 }}ª baza<span x-text="rotuloDeBaza({{ $numero }})"></span></p>
                                    <div class="hueco-baza hueco-rival" :class="{ 'opacity-55': perdio({{ $numero }}, 'rival') }" x-carta="estado.bazas[{{ $numero }}].rival"></div>
                                    <div class="hueco-baza hueco-propio" :class="{ 'opacity-55': perdio({{ $numero }}, 'vos') }" x-carta="estado.bazas[{{ $numero }}].vos"></div>
                                </li>
                            @endforeach
                        </ol>

                        <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center" aria-hidden="true">
                            <p class="voz canto text-center" x-effect="if (estado.canto) voz = estado.canto"
                                :data-visible="(estado.canto !== null).toString()" :data-quien="voz.quien" :style="{ fontSize: tamanoDeVoz }"
                                :class="{ 'canto-oro': voz.tono === 'oro', 'canto-ficha canto-copa': voz.tono === 'copa', 'canto-ficha canto-basto': voz.tono === 'basto' }"
                                x-text="voz.texto"></p>
                        </div>
                    </div>

                    <p aria-live="polite" class="min-h-12 px-4 text-center font-semibold leading-snug" x-text="estado.texto"></p>

                    <div class="px-4 pb-5 pt-1">
                        <div class="abanico mesa-mano">
                            @foreach ([0, 1, 2] as $lugar)
                                <div x-carta="estado.mano[{{ $lugar }}]"></div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-texto/15" aria-hidden="true">
                    <div class="h-full origin-left bg-texto transition-transform duration-200 ease-llegada" :style="{ transform: `scaleX(${(indice + 1) / pasos.length})` }"></div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="button" class="boton boton-linea" :disabled="indice === 0" @click="anterior()">Anterior</button>
                    <button type="button" class="boton boton-tinta" @click="reproduciendo ? pausar() : reproducir()">
                        <x-icono nombre="repetir" />
                        <span x-text="reproduciendo ? 'Pausar' : (alFinal ? 'Ver desde el principio' : 'Reproducir')">Reproducir</span>
                    </button>
                    <button type="button" class="boton boton-linea" :disabled="alFinal" @click="pausar(); siguiente()">Siguiente</button>
                    <p class="font-semibold tabular-nums">
                        Mano <span x-text="estado.numero">12</span> de 14, jugada <span x-text="indice + 1">1</span> de {{ count($pasos) }}
                    </p>
                </div>
            </div>
        </section>
    </div>
</x-layouts.base>
