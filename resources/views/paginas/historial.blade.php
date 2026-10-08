@php
    /*
     | $partidas es una página de partidas ya resumidas, o null si quien entra todavía no tiene jugador.
     | Cada resumen sale de los eventos de la partida (App\Juego\Historial): acá no hay ningún dato inventado.
     */
    $lista = collect($partidas?->items() ?? []);
    $porDia = $lista->groupBy('dia');
    $ganadas = $lista->where('gano', true)->count();
    $perdidas = $lista->count() - $ganadas;
    $primeraPagina = $partidas === null || $partidas->onFirstPage();
@endphp

<x-layouts.base titulo="Historial" descripcion="Tus partidas de Vale Cuatro, para volver a verlas jugada por jugada.">
    <x-mazo.plantillas />

    <div class="mx-auto max-w-5xl px-5 pb-24 sm:px-8" x-data="historial">
        <header class="grid items-end gap-8 pb-10 pt-10 sm:pt-16 md:grid-cols-[1fr_auto]">
            <div>
                <h1 class="text-5xl font-black tracking-tight sm:text-7xl">Historial</h1>
                <p class="mt-5 max-w-[56ch] text-lg leading-relaxed">
                    Cada partida queda guardada jugada por jugada. Por eso se puede volver a ver entera.
                </p>

                {{-- Quien juega sin cuenta ve lo de su sesión. Se le dice de entrada, con la salida al lado. --}}
                @if ($esInvitado)
                    <p class="mt-4 max-w-[56ch] leading-relaxed">
                        Jugás sin cuenta: acá están las partidas de esta sesión.
                        <a href="{{ route('registro') }}" class="enlace">Creá una cuenta</a> y te quedan guardadas, también las de antes.
                    </p>
                @endif

                @if ($enCurso)
                    <p class="mt-4 leading-relaxed">
                        Tenés una partida sin terminar.
                        <a href="{{ route('mesa') }}" class="enlace">Seguila en la mesa</a>
                    </p>
                @endif
            </div>

            {{-- La tira: una marca por partida, de la más vieja a la más nueva. Alta si la ganaste, baja si la perdiste. --}}
            @if ($lista->isNotEmpty())
                <div>
                    <ul class="flex h-10 items-end gap-1.5" aria-hidden="true">
                        @foreach ($lista->reverse() as $partida)
                            <li @class(['w-4 rounded-sm', 'h-10 bg-gana' => $partida['gano'], 'h-4 bg-pierde' => ! $partida['gano']])></li>
                        @endforeach
                    </ul>
                    <p class="mt-2 font-semibold">
                        {{ $ganadas }} {{ $ganadas === 1 ? 'ganada' : 'ganadas' }} y {{ $perdidas }} {{ $perdidas === 1 ? 'perdida' : 'perdidas' }}
                        @if ($primeraPagina && ! $partidas->hasMorePages())
                            en total
                        @else
                            en esta página
                        @endif
                    </p>
                </div>
            @endif
        </header>

        @forelse ($porDia as $dia => $delDia)
            <section aria-labelledby="dia-{{ $loop->index }}" class="border-t-2 border-texto pb-6 pt-5">
                <h2 id="dia-{{ $loop->index }}" class="text-xl font-extrabold">{{ $dia }}</h2>

                <ul>
                    @foreach ($delDia as $partida)
                        {{-- En el celular: el resultado y "Ver de nuevo" en un renglón, y debajo contra quién. El tanteo en fósforos entra desde `sm`. --}}
                        <li class="grid grid-cols-[1fr_auto] items-center gap-x-7 gap-y-3 border-b border-texto/15 py-5 last:border-b-0 max-sm:gap-y-2 max-sm:py-4 sm:grid-cols-[9rem_1fr] lg:grid-cols-[9rem_1fr_auto_auto]">
                            <p class="leading-none">
                                <span @class(['block text-sm font-bold', 'text-gana' => $partida['gano'], 'text-pierde' => ! $partida['gano']])>{{ $partida['gano'] ? 'Ganaste' : 'Perdiste' }}</span>
                                <span class="mt-1.5 block text-3xl font-black tabular-nums tracking-tight">{{ $partida['vos'] }} a {{ $partida['ellos'] }}</span>
                            </p>
                            <p class="min-w-0 leading-snug max-sm:order-3 max-sm:col-span-2">
                                <span class="block text-lg font-bold [overflow-wrap:anywhere] max-sm:text-base">Contra {{ $partida['rival'] }}</span>
                                {{-- De qué juego fue. Hoy hay uno solo; el torneo, los desafíos y el de a cuatro van a decirlo acá. --}}
                                <span class="block text-[0.95rem]">
                                    Mano a mano. {{ $partida['hora'] }}. {{ $partida['manos'] }} {{ $partida['manos'] === 1 ? 'mano' : 'manos' }} en {{ $partida['minutos'] }} {{ $partida['minutos'] === 1 ? 'minuto' : 'minutos' }}.
                                    @if ($partida['cierre'])
                                        <span class="font-semibold">{{ $partida['cierre'] }}</span>
                                    @endif
                                </span>
                            </p>
                            <div class="flex w-fit gap-5 rounded-lg bg-pano-hondo px-3.5 py-2.5 text-[0.66rem] max-sm:hidden sm:col-span-2 lg:col-span-1">
                                <x-tanteador nombre="Vos" :puntos="$partida['vos']" />
                                <x-tanteador nombre="Rival" :puntos="$partida['ellos']" />
                            </div>
                            {{-- Un link a la página de la repetición. Con JavaScript se abre en un cartel acá mismo (historial.js). --}}
                            <a href="{{ route('historial.ver', $partida['id']) }}" data-cuadros="{{ route('historial.cuadros', $partida['id']) }}"
                                class="boton boton-linea min-h-11 w-fit px-4 py-2 text-[0.95rem] max-sm:order-2 sm:col-span-2 lg:col-span-1"
                                @click="abrir($event)" :aria-busy="(pidiendo === $el.dataset.cuadros).toString()"
                                aria-label="Ver de nuevo la partida contra {{ $partida['rival'] }} de {{ mb_strtolower($partida['dia']) }} a las {{ $partida['hora'] }}">
                                <x-icono nombre="repetir" /> Ver de nuevo
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            {{--
                Todavía sin partidas: el tanteador en cero, con los lugares de los fósforos que faltan, y el botón
                para jugar la primera. No se rellena con partidas de ejemplo.
            --}}
            <section aria-labelledby="titulo-vacio" class="grid items-center gap-x-12 gap-y-8 border-t-2 border-texto pt-10 md:grid-cols-[auto_1fr]">
                <div class="flex w-fit gap-6 rounded-xl bg-pano-hondo px-5 py-4 text-[0.95rem]" aria-hidden="true">
                    <x-tanteador nombre="Vos" :puntos="0" />
                    <x-tanteador nombre="Rival" :puntos="0" />
                </div>

                <div>
                    <h2 id="titulo-vacio" class="max-w-[18ch] text-3xl font-black leading-[1.05] tracking-tight sm:text-4xl">
                        {{ $esInvitado ? 'Todavía no terminaste ninguna partida en esta sesión.' : 'Todavía no terminaste ninguna partida.' }}
                    </h2>
                    <p class="mt-3 max-w-[46ch] text-lg leading-relaxed">Cuando termines una, queda acá para volver a verla jugada por jugada.</p>

                    <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-3">
                        @if ($enCurso)
                            <a href="{{ route('mesa') }}" class="boton boton-tinta min-h-12 px-5">Seguir la partida</a>
                        @else
                            <form method="POST" action="{{ route('jugar') }}">
                                @csrf
                                <button type="submit" class="boton boton-tinta min-h-12 px-5">
                                    <x-icono nombre="bot" /> Jugar contra el bot
                                </button>
                            </form>
                        @endif
                        <a href="{{ route('modos') }}" class="enlace-nav text-lg font-bold">Elegir otro modo</a>
                    </div>
                </div>
            </section>
        @endforelse

        @if ($partidas && (! $primeraPagina || $partidas->hasMorePages()))
            <nav aria-label="Más partidas" class="flex flex-wrap gap-3 border-t-2 border-texto pt-6">
                @if (! $primeraPagina)
                    <a href="{{ $partidas->previousPageUrl() }}" class="boton boton-linea min-h-11 px-4" rel="prev">Más nuevas</a>
                @endif
                @if ($partidas->hasMorePages())
                    <a href="{{ $partidas->nextPageUrl() }}" class="boton boton-linea min-h-11 px-4" rel="next">Más viejas</a>
                @endif
            </nav>
        @endif

        {{--
            El cartel de la repetición. Es el <dialog> del navegador: se ocupa del foco, de la tecla Esc y de
            que la lista de atrás no se pueda tocar. Cerrar (el botón, Esc o un clic en el fondo) es volver
            atrás en el navegador, porque al abrirlo la dirección pasó a ser la de esa partida.
        --}}
        @if ($lista->isNotEmpty())
            <dialog x-ref="cartel" class="cartel-repeticion" aria-labelledby="titulo-repeticion" @cancel.prevent="cerrar()" @click.self="cerrar()">
                <template x-if="datos">
                    <x-repeticion datos="datos" class="cartel-contenido">
                        <button type="button" class="boton boton-linea mb-5 min-h-10 w-fit shrink-0 px-3 py-1.5 text-sm max-sm:mb-0" @click="cerrar()">Cerrar</button>
                    </x-repeticion>
                </template>
            </dialog>
        @endif
    </div>
</x-layouts.base>
