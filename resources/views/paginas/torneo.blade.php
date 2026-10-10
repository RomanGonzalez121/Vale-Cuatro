@php
    use App\Juego\Llaves;

    /*
     | $llaves sale de App\Juego\Llaves: las rondas con sus cruces, el campeón y en qué está la persona.
     | Acá no se decide nada del torneo: solo se dibuja.
     |
     | Cada jugador se ve como una carta del mazo. La de un bot dice su nivel por la jerarquía del truco
     | (el más difícil es el ancho de espada, los fáciles son cuatros); la de la persona es el 4 de copas.
     | Quien pierde un cruce queda boca abajo.
     */
    $vos = $llaves['vos'];
    $campeon = $llaves['campeon'];
    $rival = $vos['rival'];
    $seJuega = $torneo->enCurso();

    // Arriba se ve la partida propia como una baza: la carta del rival y la de la persona. Si ya se jugó,
    // la de quien perdió queda boca abajo.
    $perdio = $vos['estado'] === 'afuera';
    $gano = $vos['estado'] === 'campeon';
    $duelo = [
        'arriba' => $rival ? ['carta' => $rival['carta'], 'bocaAbajo' => $gano, 'gana' => $perdio] : null,
        'abajo' => ['carta' => $vos['carta'], 'bocaAbajo' => $perdio, 'gana' => $gano],
    ];
@endphp

<x-layouts.base titulo="Torneo relámpago" descripcion="Las llaves de tu torneo relámpago de truco contra bots." superficie="pano">
    <div class="mx-auto max-w-6xl px-5 pb-16 pt-6 sm:px-8 lg:pb-24 lg:pt-10">
        {{--
            Lo primero es la partida que toca: dicha en una frase y puesta sobre la mesa, carta contra carta.
            En el celular van el título, las cartas y el resto; en pantallas anchas las cartas van a la derecha.
        --}}
        <header class="torneo-inicio grid items-start gap-x-14 gap-y-6 [grid-template-areas:'titulo'_'duelo'_'resto'] lg:grid-cols-[minmax(0,1fr)_auto] lg:grid-rows-[auto_1fr] lg:[grid-template-areas:'titulo_duelo'_'resto_duelo']">
            <h1 class="max-w-[16ch] text-[clamp(2.25rem,5.6vw,4rem)] font-black leading-[0.98] tracking-[-0.035em] [grid-area:titulo] [overflow-wrap:anywhere]">
                @switch ($vos['estado'])
                    @case (Llaves::POR_JUGAR)
                        Jugás {{ $vos['partido'] }} contra {{ $rival['apodo'] }}
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

            {{-- Las dos cartas, con cada nombre a la altura de la suya: arriba el rival, abajo vos. --}}
            <div class="torneo-duelo flex items-stretch gap-5 [grid-area:duelo] lg:self-center">
                <x-duelo :arriba="$duelo['arriba']" :abajo="$duelo['abajo']" reparte />

                <div class="flex min-w-0 flex-col justify-between py-[0.4em] leading-tight">
                    @if ($rival)
                        <p @class(['text-naipe/70' => $gano])>
                            <span class="flex items-center gap-2">
                                <x-icono nombre="bot" class="size-5 flex-none" />
                                <x-nivel-fosforos :nivel="$rival['nivel']" class="flex-none text-[0.8rem]" />
                            </span>
                            <span class="mt-1.5 block text-xl font-black tracking-tight sm:text-2xl">{{ $rival['apodo'] }}</span>
                            <span class="block text-[0.95rem] font-semibold">Bot {{ mb_strtolower($rival['nivel']->nombre()) }}</span>
                        </p>
                    @endif
                    <p @class(['flex items-center gap-2 text-xl font-black tracking-tight sm:text-2xl', 'text-naipe/70' => $perdio])>
                        <x-icono nombre="jugador" class="size-5 flex-none" /> Vos
                    </p>
                </div>
            </div>

            <div class="[grid-area:resto]">
                <p class="max-w-[46ch] text-lg leading-relaxed">
                    @if ($seJuega)
                        {{ $rival['nivel']->detalle() }} La partida va a {{ $torneo->puntos }} puntos.
                    @elseif ($gano)
                        Ganaste {{ $torneo->rondas() === 2 ? 'las dos partidas' : 'las tres partidas' }}, cada una a {{ $torneo->puntos }} puntos.
                    @elseif ($rival && $campeon && $rival['lugar'] === $campeon['lugar'])
                        {{ $vos['partido'] === 'la final' ? "El campeón es {$campeon['apodo']}." : "Pasó {$campeon['apodo']}, que terminó ganando el torneo." }}
                    @else
                        @if ($rival)
                            Pasó {{ $rival['apodo'] }}.
                        @endif
                        @if ($campeon)
                            El campeón es {{ $campeon['apodo'] }}.
                        @endif
                    @endif
                </p>

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    @if ($seJuega)
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
            </div>
        </header>

        <section aria-labelledby="titulo-llaves" class="mt-12 lg:mt-14">
            <h2 id="titulo-llaves" class="text-2xl font-black tracking-tight sm:text-3xl">Las llaves</h2>
            <p class="mt-2 max-w-[64ch] leading-relaxed">
                Son {{ $torneo->lugares }} jugadores y pasa quien gana. Los demás son bots, y la carta de cada uno dice cómo juega:
                cuanto más alta en el truco, más difícil. Quien pierde queda boca abajo.
                @if ($seJuega)
                    Las otras partidas se juegan a la par de la tuya: los resultados aparecen cuando terminás.
                @endif
            </p>

            {{--
                Cada cruce es una baza: las dos cartas pisándose y, al lado, quiénes son y cuánto hicieron.
                En pantallas anchas va una columna por ronda, con cada par de cruces unido por una llave dibujada
                como un fósforo (app.css, .llaves). En el celular las rondas van una debajo de la otra.
            --}}
            <div class="llaves mt-8" style="--rondas: {{ count($llaves['rondas']) }}" data-torneo="{{ $torneo->id }}">
                @foreach ($llaves['rondas'] as $ronda)
                    <section aria-labelledby="ronda-{{ $ronda['numero'] }}" @class(['llave-ronda', 'llave-final' => $loop->last])>
                        <h3 id="ronda-{{ $ronda['numero'] }}" class="text-lg font-extrabold leading-tight tracking-tight">{{ $ronda['nombre'] }}</h3>

                        <ol class="llave-lugares">
                            @foreach ($ronda['cruces'] as $cruce)
                                @php
                                    [$uno, $dos] = $cruce['lados'];
                                    $enJuego = in_array($cruce['estado'], [Llaves::POR_JUGAR, Llaves::JUGANDO], true);
                                @endphp

                                @php($resuelto = $cruce['estado'] === Llaves::RESUELTO)
                                @php($completo = $cruce['estado'] !== Llaves::ESPERA)

                                <li class="llave-lugar" data-cruce="{{ $ronda['numero'] }}-{{ $cruce['orden'] }}" data-ronda="{{ $ronda['numero'] }}"
                                    @if ($resuelto) data-resuelto @endif @if ($completo) data-completo @endif>
                                    {{--
                                        La llave encendida va en piezas aparte, encima de la tenue: el palito y la cabeza
                                        cuando ya llegaron los dos, y el brazo que sale hacia la ronda siguiente cuando ya pasó alguien.
                                    --}}
                                    @unless ($loop->parent->first)
                                        <span class="cruce-cabeza" aria-hidden="true">@if ($completo)<span class="cruce-llama"></span>@endif</span>
                                        @if ($completo)
                                            <span class="llave-palito" aria-hidden="true"></span>
                                        @endif
                                    @endunless
                                    @if ($resuelto)
                                        <span class="llave-brazo" aria-hidden="true"></span>
                                    @endif

                                    <x-duelo
                                        :arriba="$uno ? ['carta' => $uno['carta'], 'bocaAbajo' => $uno['gano'] === false, 'gana' => $uno['gano'] === true] : null"
                                        :abajo="$dos ? ['carta' => $dos['carta'], 'bocaAbajo' => $dos['gano'] === false, 'gana' => $dos['gano'] === true] : null" />

                                    <div class="min-w-0 flex-1">
                                        @if ($uno === null)
                                            {{-- Todavía no llegó nadie: Naipe al 70 % sobre Paño da 4,7:1. --}}
                                            <p class="text-[0.95rem] leading-snug text-naipe/70">Esperan a quienes ganen {{ Llaves::conArticulo($llaves['rondas'][$loop->parent->index - 1]['nombre']) }}.</p>
                                        @else
                                            @foreach ($cruce['lados'] as $lado)
                                                @php($fuera = $lado['gano'] === false)
                                                <p @class(['cruce-lado flex min-h-8 items-center gap-2', 'text-naipe/70' => $fuera])>
                                                    <x-icono :nombre="$lado['vos'] ? 'jugador' : 'bot'" class="size-4 flex-none" />
                                                    <span @class(['min-w-0 truncate', 'font-extrabold' => ! $fuera, 'font-semibold' => $fuera])>
                                                        {{ $lado['vos'] ? 'Vos' : $lado['apodo'] }}
                                                        @unless ($lado['vos'])
                                                            <span class="sr-only">, bot {{ mb_strtolower($lado['nivel']->nombre()) }}</span>
                                                        @endunless
                                                        @if ($lado['gano'])
                                                            <span class="sr-only">, pasó</span>
                                                        @endif
                                                    </span>
                                                    {{-- El tanteo de quien pasó va en Oro y grande (Oro sobre Paño solo vale para texto grande); el otro, en Naipe. --}}
                                                    @if ($lado['puntos'] !== null)
                                                        <span @class(['cruce-tanteo ml-auto flex-none pl-2 text-xl font-black tabular-nums leading-none', 'text-oro' => ! $fuera])>{{ $lado['puntos'] }}<span class="sr-only"> puntos</span></span>
                                                    @endif
                                                </p>
                                            @endforeach

                                            {{-- La partida propia lleva la misma marca que "Sos mano" en la mesa. --}}
                                            @if ($enJuego)
                                                <p class="cruce-lado mt-1.5 inline-flex items-center gap-1.5 rounded-full border border-naipe/40 px-2.5 py-0.5 text-sm font-semibold">
                                                    <x-icono nombre="mano" class="size-4" /> {{ $cruce['estado'] === Llaves::JUGANDO ? 'La estás jugando' : 'Te toca' }}
                                                </p>
                                            @elseif ($cruce['porAbandono'])
                                                <p class="mt-1 text-[0.9rem] leading-snug text-naipe/70">{{ $cruce['sinJugar'] ? 'Pasó sin jugar.' : 'La dejaste antes del final.' }}</p>
                                            @endif
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endforeach

                {{-- Adonde llegan las llaves: la carta de quien gana la final. --}}
                <section aria-labelledby="titulo-campeon" class="llave-ronda llave-campeon">
                    <h3 id="titulo-campeon" class="text-lg font-extrabold leading-tight tracking-tight">Campeón</h3>

                    <div class="llave-lugares">
                        <div class="llave-lugar" data-cruce="campeon" @if ($campeon) data-completo @endif>
                            <span class="cruce-cabeza" aria-hidden="true">@if ($campeon)<span class="cruce-llama"></span>@endif</span>
                            @if ($campeon)
                                <span class="llave-palito" aria-hidden="true"></span>
                            @endif

                            <div class="carta-campeon flex-none" aria-hidden="true">@if ($campeon)<x-carta :palo="$campeon['carta'][0]" :numero="$campeon['carta'][1]" />@endif</div>

                            @if ($campeon)
                                <p class="cruce-lado flex min-w-0 items-center gap-2">
                                    <x-icono :nombre="$campeon['vos'] ? 'jugador' : 'bot'" class="size-5 flex-none" />
                                    <span class="min-w-0 text-xl font-black leading-[1.05] tracking-tight">
                                        {{ $campeon['vos'] ? 'Vos' : $campeon['apodo'] }}
                                        @unless ($campeon['vos'])
                                            <span class="sr-only">, bot {{ mb_strtolower($campeon['nivel']->nombre()) }}</span>
                                        @endunless
                                    </span>
                                </p>
                            @else
                                <p class="text-[0.95rem] leading-snug text-naipe/70">Quien gane la final.</p>
                            @endif
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </div>
</x-layouts.base>
