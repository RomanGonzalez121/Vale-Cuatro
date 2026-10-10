@php
    use App\Juego\Llaves;

    /*
     | $llaves sale de App\Juego\Llaves: las rondas con sus cruces, el campeón y en qué está la persona.
     | Acá no se decide nada del torneo: solo se dibuja.
     */
    $vos = $llaves['vos'];
    $campeon = $llaves['campeon'];
    $partidas = $torneo->rondas();

    // Lo que se dice de un cruce que todavía no tiene resultado.
    $notas = [
        Llaves::POR_JUGAR => 'Te toca jugarla.',
        Llaves::JUGANDO => 'La estás jugando.',
        Llaves::A_LA_PAR => 'Se juega a la par de la tuya.',
    ];
@endphp

<x-layouts.base titulo="Torneo relámpago" descripcion="Las llaves de tu torneo relámpago de truco contra bots." superficie="pano">
    <div class="mx-auto max-w-6xl px-5 pb-16 pt-6 sm:px-8 lg:pb-24 lg:pt-12">
        {{-- Lo primero es qué te toca ahora, dicho en una frase, con su botón. --}}
        <header>
            <h1 class="max-w-[18ch] text-[clamp(2.25rem,6vw,4rem)] font-black leading-[0.98] tracking-[-0.035em] [overflow-wrap:anywhere]">
                @switch ($vos['estado'])
                    @case (Llaves::POR_JUGAR)
                        Jugás {{ $vos['partido'] }} contra {{ $vos['rival']['apodo'] }}
                        @break
                    @case (Llaves::JUGANDO)
                        Estás jugando {{ $vos['partido'] }}
                        @break
                    @case ('campeon')
                        Sos el campeón del torneo
                        @break
                    @default
                        {{ $vos['partido'] ? "Quedaste afuera en {$vos['partido']}" : 'Quedaste afuera del torneo' }}
                @endswitch
            </h1>

            <div class="mt-4 max-w-[52ch] text-lg leading-relaxed">
                @if ($vos['rival'] && $torneo->enCurso())
                    {{-- Contra quién: un bot, dicho con su marca, su nivel y cómo juega. --}}
                    <p class="font-semibold">
                        <span class="mr-1 inline-flex items-center gap-2 align-[-0.2em]">
                            <x-icono nombre="bot" class="size-5" />
                            <x-nivel-fosforos :nivel="$vos['rival']['nivel']" class="text-[0.8rem]" />
                        </span>
                        {{ $vos['rival']['apodo'] }} es un bot {{ mb_strtolower($vos['rival']['nivel']->nombre()) }}.
                    </p>
                    <p class="mt-1">{{ $vos['rival']['nivel']->detalle() }} La partida va a {{ $torneo->puntos }} puntos.</p>
                @elseif ($vos['estado'] === 'campeon')
                    <p>Ganaste {{ $partidas === 2 ? 'las dos partidas' : 'las tres partidas' }}, cada una a {{ $torneo->puntos }} puntos.</p>
                @else
                    <p>
                        @if ($vos['rival'] && $campeon && $vos['rival']['lugar'] === $campeon['lugar'])
                            {{ $vos['partido'] === 'la final' ? "El campeón es {$campeon['apodo']}." : "Pasó {$campeon['apodo']}, que terminó ganando el torneo." }}
                        @else
                            @if ($vos['rival'])
                                Pasó {{ $vos['rival']['apodo'] }}.
                            @endif
                            @if ($campeon)
                                El campeón es {{ $campeon['apodo'] }}.
                            @endif
                        @endif
                    </p>
                @endif
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                @if ($torneo->enCurso())
                    <form method="POST" action="{{ route('torneo.jugar', $torneo) }}">
                        @csrf
                        <button type="submit" class="boton boton-naipe min-h-14 px-6 text-lg">
                            <x-icono nombre="repartir" />
                            {{ $vos['estado'] === Llaves::JUGANDO ? 'Seguir la partida' : "Jugar {$vos['partido']}" }}
                        </button>
                    </form>

                    {{-- Dejar el torneo no tiene vuelta: se confirma en el lugar, con un botón que dice lo que va a pasar. --}}
                    <form method="POST" action="{{ route('torneo.abandonar', $torneo) }}" class="flex flex-wrap items-center gap-3"
                        x-data="{ seguro: false }" @keydown.escape="seguro = false; $nextTick(() => $refs.abrir.focus())">
                        @csrf
                        <button type="button" x-ref="abrir" class="boton boton-linea min-h-14 px-6 text-lg" x-show="! seguro"
                            @click="seguro = true; $nextTick(() => $refs.confirmar.focus())">Dejar el torneo</button>
                        <button type="submit" x-ref="confirmar" class="boton boton-copa min-h-14 px-6 text-lg" x-show="seguro" x-cloak
                            x-transition:enter="aparece" x-transition:enter-start="desde-chico">Sí, dejarlo y quedar afuera</button>
                        <button type="button" class="boton boton-linea min-h-14 px-6 text-lg" x-show="seguro" x-cloak
                            x-transition:enter="aparece" x-transition:enter-start="desde-chico" @click="seguro = false; $nextTick(() => $refs.abrir.focus())">No</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('torneo.crear') }}">
                        @csrf
                        <input type="hidden" name="lugares" value="{{ $torneo->lugares }}">
                        <button type="submit" class="boton boton-naipe min-h-14 px-6 text-lg"><x-icono nombre="torneo" /> Armar otro torneo</button>
                    </form>
                    <a href="{{ route('modos') }}" class="boton boton-linea min-h-14 px-6 text-lg">Elegir cómo jugar</a>
                @endif
            </div>
        </header>

        <section aria-labelledby="titulo-llaves" class="mt-12 lg:mt-16">
            <h2 id="titulo-llaves" class="text-2xl font-black tracking-tight sm:text-3xl">Las llaves</h2>
            <p class="mt-2 max-w-[60ch] leading-relaxed">
                Son {{ $torneo->lugares }} jugadores y pasa quien gana. Los demás son bots.
                @if ($torneo->enCurso())
                    Sus partidas se juegan a la par de la tuya: los resultados aparecen cuando terminás.
                @endif
            </p>

            {{--
                En pantallas anchas, una columna por ronda y de izquierda a derecha, con cada par de cruces unido por
                una llave dibujada como un fósforo (app.css, .llaves). En el celular las rondas van una debajo de la otra.
            --}}
            <div class="llaves mt-8" style="--rondas: {{ count($llaves['rondas']) + 1 }}">
                @foreach ($llaves['rondas'] as $ronda)
                    <section aria-labelledby="ronda-{{ $ronda['numero'] }}" @class(['llave-ronda', 'llave-final' => $loop->last])>
                        <h3 id="ronda-{{ $ronda['numero'] }}" class="text-lg font-extrabold leading-tight tracking-tight">{{ $ronda['nombre'] }}</h3>

                        <ol class="llave-lugares">
                            @foreach ($ronda['cruces'] as $cruce)
                                <li class="llave-lugar" @if ($cruce['estado'] === Llaves::RESUELTO) data-resuelto @endif @if ($cruce['estado'] !== Llaves::ESPERA) data-completo @endif>
                                    @unless ($loop->parent->first)
                                        <span class="cruce-cabeza" aria-hidden="true"></span>
                                    @endunless

                                    {{-- En pantallas anchas la nota va por fuera, debajo: así los dos renglones quedan centrados donde llega la llave. --}}
                                    <div class="relative w-full">
                                        @foreach ($cruce['lados'] as $lado)
                                            @if ($lado === null)
                                                {{-- Todavía no se sabe quién llega a este lado: Naipe al 70 % sobre Paño da 4,7:1. --}}
                                                <p @class(['flex min-h-9 items-center text-[0.95rem] text-naipe/70', 'border-t border-naipe/20' => ! $loop->first])>Sale de {{ Llaves::conArticulo($llaves['rondas'][$loop->parent->parent->index - 1]['nombre']) }}</p>
                                            @else
                                                @php($perdio = $lado['gano'] === false)
                                                <p @class(['flex min-h-9 items-center gap-2', 'border-t border-naipe/20' => ! $loop->first, 'text-naipe/70' => $perdio])>
                                                    <x-icono :nombre="$lado['vos'] ? 'jugador' : 'bot'" class="size-4 flex-none" />
                                                    <span @class(['min-w-0 truncate', 'font-extrabold' => ! $perdio, 'font-semibold' => $perdio])>
                                                        {{ $lado['vos'] ? 'Vos' : $lado['apodo'] }}
                                                        @unless ($lado['vos'])
                                                            <span class="sr-only">, bot {{ mb_strtolower($lado['nivel']->nombre()) }}</span>
                                                        @endunless
                                                        @if ($lado['gano'])
                                                            <span class="sr-only">, pasó</span>
                                                        @endif
                                                    </span>
                                                    @unless ($lado['vos'])
                                                        <x-nivel-fosforos :nivel="$lado['nivel']" class="flex-none text-[0.6rem]" />
                                                    @endunless
                                                    {{-- El tanteo de quien pasó va en Oro y grande (Oro sobre Paño solo vale para texto grande); el otro, en Naipe. --}}
                                                    @if ($lado['puntos'] !== null)
                                                        <span @class(['ml-auto flex-none pl-2 text-xl font-black tabular-nums leading-none', 'text-oro' => ! $perdio])>{{ $lado['puntos'] }}<span class="sr-only"> puntos</span></span>
                                                    @endif
                                                </p>
                                            @endif
                                        @endforeach

                                        @if (isset($notas[$cruce['estado']]))
                                            <p @class(['mt-1 text-[0.9rem] leading-snug lg:absolute lg:top-full lg:mt-0.5', 'font-bold' => $cruce['tuyo'], 'text-naipe/70' => ! $cruce['tuyo']])>{{ $notas[$cruce['estado']] }}</p>
                                        @elseif ($cruce['porAbandono'])
                                            <p class="mt-1 text-[0.9rem] leading-snug text-naipe/70 lg:absolute lg:top-full lg:mt-0.5">{{ $cruce['sinJugar'] ? 'Pasó sin jugar.' : 'La dejaste antes del final.' }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endforeach

                {{-- Adonde llegan las llaves: quien gana la final. --}}
                <section aria-labelledby="titulo-campeon" class="llave-ronda llave-campeon">
                    <h3 id="titulo-campeon" class="text-lg font-extrabold leading-tight tracking-tight">Campeón</h3>

                    <div class="llave-lugares">
                        <div class="llave-lugar" @if ($campeon) data-completo @endif>
                            <span class="cruce-cabeza" aria-hidden="true"></span>

                            @if ($campeon)
                                <p class="flex min-w-0 items-center gap-2">
                                    <x-icono :nombre="$campeon['vos'] ? 'jugador' : 'bot'" class="size-5 flex-none" />
                                    @unless ($campeon['vos'])
                                        <x-nivel-fosforos :nivel="$campeon['nivel']" class="flex-none text-[0.7rem]" />
                                    @endunless
                                    <span class="min-w-0 text-2xl font-black leading-[1.05] tracking-tight">
                                        {{ $campeon['vos'] ? 'Vos' : $campeon['apodo'] }}
                                        @unless ($campeon['vos'])
                                            <span class="sr-only">, bot {{ mb_strtolower($campeon['nivel']->nombre()) }}</span>
                                        @endunless
                                    </span>
                                </p>
                            @else
                                <p class="text-[0.95rem] text-naipe/70">Sale de la final</p>
                            @endif
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </div>
</x-layouts.base>
