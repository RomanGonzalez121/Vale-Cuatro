@php
    use App\Juego\Modos;

    // En la mano van los juegos que ya se juegan; en el mazo, los que todavía no se repartieron.
    $enLaMano = array_values(array_filter($juegos, Modos::seJuega(...)));
    $enElMazo = array_values(array_filter($juegos, fn (array $juego) => ! Modos::seJuega($juego)));

    // Entra elegido el primer juego de la mano y, en cada juego, el primer rival contra el que se puede jugar.
    $rivalElegido = [];

    foreach ($enLaMano as $juego) {
        $rivalElegido[$juego['clave']] = collect($juego['rivales'])->firstWhere('boton', '!==', null)['clave'];
    }

    $juegoElegido = $enLaMano[0]['clave'] ?? null;
    $unaSola = count($enLaMano) === 1;

    // Con una partida sin terminar no se elige nivel: se sigue esa, contra el bot que ya tenía.
    $enCurso ??= null;
    $nivelElegido = $enCurso?->nivel_bot ?? \App\Juego\Nivel::porDefecto();
@endphp

<x-layouts.base titulo="Modos de juego" descripcion="Elegí cómo jugar al truco en Vale Cuatro: mano a mano contra el bot ahora mismo, y los modos que se van sumando." superficie="pano">
    {{-- Las cartas llegan desde afuera de la pantalla: se recorta el costado para que el reparto no agregue scroll. --}}
    <div class="overflow-x-clip">
        <div class="modos-inicio mx-auto grid max-w-6xl gap-x-16 gap-y-12 px-5 pb-16 pt-6 sm:px-8 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)] lg:pb-24 lg:pt-14"
            x-data="{ juego: {{ Js::from($juegoElegido) }}, rival: {{ Js::from($rivalElegido) }}, nivel: {{ $nivelElegido->value }}, serie: false }">
            <section aria-labelledby="titulo-modos">
                <h1 id="titulo-modos" class="text-[clamp(2.5rem,6.4vw,4.5rem)] font-black leading-[0.96] tracking-[-0.035em]">
                    ¿Cómo querés jugar?
                </h1>

                {{-- Con una sola carta, el detalle va al lado. Con varias, la mano ocupa el ancho y el detalle va debajo. --}}
                <div @class(['modos-mano mt-7 grid items-start gap-y-5 lg:mt-10', $unaSola ? 'grid-cols-[auto_minmax(0,1fr)] gap-x-5 sm:gap-x-9' : 'grid-cols-1'])>
                    {{-- La mano: una carta por juego que ya se juega. El reparto es el único movimiento que no responde a una acción. --}}
                    <div @class(['mano-juegos', $unaSola ? 'mano-de-una sm:row-span-2' : 'mano-de-varias'])
                        style="--ancho-carta: clamp(6.25rem, 27vw, 13rem); --medio: {{ (count($enLaMano) - 1) / 2 }}">
                        @foreach ($enLaMano as $i => $juego)
                            @if ($unaSola)
                                <div style="--i: {{ $i }}">
                                    <span class="se-reparte block" style="--orden: {{ $i }}">
                                        <span class="se-da-vuelta">
                                            <x-carta-modo :icono="$juego['icono']" :nombre="$juego['nombre']" :renglones="$juego['renglones']" />
                                            <x-dorso class="reverso" />
                                        </span>
                                    </span>
                                </div>
                            @else
                                <button type="button" class="naipe-juego" style="--i: {{ $i }}"
                                    aria-pressed="{{ $juego['clave'] === $juegoElegido ? 'true' : 'false' }}"
                                    :aria-pressed="(juego === '{{ $juego['clave'] }}').toString()"
                                    @click="juego = '{{ $juego['clave'] }}'">
                                    <span class="se-reparte block" style="--orden: {{ $i }}">
                                        <span class="se-da-vuelta">
                                            <x-carta-modo :icono="$juego['icono']" :nombre="$juego['nombre']" :renglones="$juego['renglones']" />
                                            <x-dorso class="reverso" />
                                        </span>
                                    </span>
                                </button>
                            @endif
                        @endforeach
                    </div>

                    @foreach ($enLaMano as $juego)
                        <div class="contents" x-show="juego === '{{ $juego['clave'] }}'" @if ($juego['clave'] !== $juegoElegido) x-cloak @endif>
                            <div @class(['self-center sm:self-end' => $unaSola])>
                                <h2 class="text-2xl font-black leading-[1.02] tracking-tight sm:text-4xl">{{ $juego['nombre'] }}</h2>
                                <p class="mt-2 max-w-[36ch] leading-relaxed sm:mt-3 sm:text-lg">{{ $juego['resumen'] }}</p>
                            </div>

                            <div @class(['col-span-2 sm:col-span-1 sm:col-start-2' => $unaSola])>
                                @if (count($juego['rivales']) > 1)
                                    <div class="flex flex-wrap gap-x-7 gap-y-1" role="group" aria-label="Contra quién">
                                        @foreach ($juego['rivales'] as $rival)
                                            <button type="button" class="enlace-nav cursor-pointer text-lg font-bold"
                                                aria-pressed="{{ $rival['clave'] === $rivalElegido[$juego['clave']] ? 'true' : 'false' }}"
                                                :aria-pressed="(rival['{{ $juego['clave'] }}'] === '{{ $rival['clave'] }}').toString()"
                                                @click="rival['{{ $juego['clave'] }}'] = '{{ $rival['clave'] }}'">{{ $rival['nombre'] }}</button>
                                        @endforeach
                                    </div>
                                @endif

                                @foreach ($juego['rivales'] as $rival)
                                    <div class="mt-3" x-show="rival['{{ $juego['clave'] }}'] === '{{ $rival['clave'] }}'"
                                        @if ($rival['clave'] !== $rivalElegido[$juego['clave']]) x-cloak @endif
                                        x-transition:enter="transition-opacity duration-150 ease-llegada" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                                        <p class="max-w-[36ch] leading-relaxed">
                                            {{ $rival['detalle'] }}
                                            @if ($rival['boton'] === null)
                                                <span class="font-bold">Todavía no se juega.</span>
                                            @endif
                                        </p>

                                        @if ($rival['boton'] !== null)
                                            @php
                                                $niveles = $rival['niveles'] ?? [];
                                                // Con una partida sin terminar, cualquiera de los botones la sigue: es una sola a la vez.
                                                $sigue = $enCurso !== null;
                                            @endphp

                                            @if ($sigue)
                                                <p class="mt-4 max-w-[36ch] font-bold leading-relaxed">
                                                    @if ($enCurso->esperando())
                                                        {{-- Dicho de entrada y con la salida al lado: sin esto los niveles apagados parecen un error. --}}
                                                        Tenés una sala abierta esperando rival. Para jugar contra el bot o en otro modo, primero cancelala.
                                                    @elseif ($enCurso->entre_personas)
                                                        Tenés una partida sin terminar con otra persona.
                                                    @else
                                                        Tenés una partida sin terminar contra {{ $enCurso->nivel_bot->nombre() }}.
                                                    @endif
                                                </p>
                                            @endif

                                            @if ($niveles !== [])
                                                {{--
                                                    El nivel del bot. No lleva el subrayado de los links: cuál está elegido lo dicen sus fósforos.
                                                    Con una partida sin terminar queda apagado: Naipe al 70 % sobre Paño da 4,7:1, que todavía se lee.
                                                --}}
                                                <div class="mt-3 grid max-w-[21rem] grid-cols-4" role="group" aria-label="Nivel del bot">
                                                    @foreach ($niveles as $opcion)
                                                        {{--
                                                            Los cuatro salen del mismo molde: una columna igual para cada uno, el mismo cuadrado de
                                                            fósforos (con uno, dos, tres o cuatro puestos) y el nombre en una palabra, para que midan
                                                            parecido. A 360 px cada columna tiene 80 px. Los del nivel elegido caen de a uno y se encienden.
                                                        --}}
                                                        <button type="button" @disabled($sigue)
                                                            class="nivel-opcion cursor-pointer py-1 text-left text-lg font-bold disabled:pointer-events-none disabled:cursor-default disabled:aria-[pressed=false]:opacity-70"
                                                            aria-pressed="{{ $opcion === $nivelElegido ? 'true' : 'false' }}"
                                                            :aria-pressed="(nivel === {{ $opcion->value }}).toString()"
                                                            @click="nivel = {{ $opcion->value }}"><x-nivel-fosforos :nivel="$opcion" :puesto="$opcion === $nivelElegido" modelo="nivel === {{ $opcion->value }}" class="mb-1.5 text-[1.25rem]" />{{ $opcion->corto() }}</button>
                                                    @endforeach
                                                </div>

                                                {{--
                                                    El nombre completo, dicho igual que en la mesa, y cómo juega. Siempre son dos renglones: el botón no salta al cambiar de nivel.
                                                    En el renglón del nombre, a la derecha, va la casilla de la serie: contra quién se juega y a cuántas
                                                    partidas, en un mismo lugar. Ahí no agrega un renglón, y el botón queda donde estaba.
                                                --}}
                                                <div class="relative mt-1 max-w-[36ch]">
                                                    @foreach ($niveles as $opcion)
                                                        <p class="leading-relaxed" x-show="nivel === {{ $opcion->value }}" @if ($opcion !== $nivelElegido) x-cloak @endif><span class="block font-bold">Bot {{ mb_strtolower($opcion->nombre()) }}</span>{{ $opcion->detalle() }}</p>
                                                    @endforeach

                                                    @unless ($sigue)
                                                        {{-- Mide un renglón, pero se toca en un área más alta (el ::before), para que el dedo no le erre. --}}
                                                        <label class="absolute right-0 top-0 flex h-[1.625rem] cursor-pointer items-center gap-2 font-semibold before:absolute before:-inset-x-2 before:-inset-y-2.5 before:content-['']" data-formato>
                                                            <input type="checkbox" class="casilla sr-only" x-model="serie">
                                                            <span class="casilla-caja" aria-hidden="true"><x-icono nombre="quiero" /></span>
                                                            Al mejor de tres
                                                        </label>
                                                    @endunless
                                                </div>
                                            @elseif (! $sigue)
                                                {{-- Con otra persona no hay nivel que elegir: la casilla de la serie va sola, arriba del botón. --}}
                                                <label class="mt-3 flex min-h-11 w-fit cursor-pointer items-center gap-3 font-semibold" data-formato>
                                                    <input type="checkbox" class="casilla sr-only" x-model="serie">
                                                    <span class="casilla-caja" aria-hidden="true"><x-icono nombre="quiero" /></span>
                                                    Al mejor de tres
                                                </label>
                                            @endif

                                            {{-- Sin campos a la vista: apretar el botón alcanza. Quien no tiene sesión entra como invitado. --}}
                                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                                <form method="POST" action="{{ route($rival['ruta'] ?? 'jugar') }}">
                                                    @csrf
                                                    @if ($niveles !== [])
                                                        <input type="hidden" name="nivel" value="{{ $nivelElegido->value }}" :value="nivel">
                                                    @endif
                                                    {{-- Una partida, o una serie al mejor de tres: lo dice la casilla de arriba. --}}
                                                    <input type="hidden" name="serie" value="0" :value="serie ? 1 : 0">
                                                    <button type="submit" class="boton boton-naipe min-h-14 px-6 text-lg">
                                                        <x-icono :nombre="$rival['icono'] ?? 'bot'" />
                                                        {{ $sigue ? ($enCurso->esperando() ? 'Volver a la sala' : 'Seguir la partida') : $rival['boton'] }}
                                                    </button>
                                                </form>

                                                {{-- La sala se cancela desde acá mismo: no hace falta entrar a ella para poder jugar otra cosa. --}}
                                                @if ($enCurso?->esperando())
                                                    <form method="POST" action="{{ route('sala.cancelar', $enCurso->codigo) }}">
                                                        @csrf
                                                        <button type="submit" class="boton boton-linea min-h-14 px-6 text-lg">Cancelar sala</button>
                                                    </form>
                                                @endif
                                            </div>

                                            @if ($sigue && ! $enCurso->esperando())
                                                <p class="mt-3 max-w-[36ch] text-[0.95rem] leading-relaxed">
                                                    {{ $niveles !== [] ? 'Para jugar contra otro nivel, primero abandonala desde la mesa.' : 'Para empezar otra, primero abandonala desde la mesa.' }}
                                                </p>
                                            @endif
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            @if ($enElMazo !== [])
                <section aria-labelledby="titulo-mazo" class="lg:pt-4">
                    <div class="flex items-center gap-6">
                        <div class="mazo-modos" aria-hidden="true">
                            <x-dorso />
                            <x-dorso />
                            <x-dorso />
                        </div>
                        <div>
                            <h2 id="titulo-mazo" class="text-2xl font-black leading-tight tracking-tight sm:text-3xl">Todavía en el mazo</h2>
                            <p class="mt-1.5 max-w-[28ch] leading-relaxed">Se reparten a medida que se termina cada modo.</p>
                        </div>
                    </div>

                    <ul class="mt-7 divide-y divide-naipe/20 border-t border-naipe/20">
                        @foreach ($enElMazo as $juego)
                            <li class="flex gap-4 py-4">
                                <x-icono :nombre="$juego['icono']" class="mt-0.5 size-6" />
                                <div>
                                    <p class="text-lg font-extrabold leading-tight tracking-tight">{{ $juego['nombre'] }}</p>
                                    <p class="mt-1 max-w-[40ch] text-[0.95rem] leading-relaxed">{{ $juego['resumen'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-layouts.base>
