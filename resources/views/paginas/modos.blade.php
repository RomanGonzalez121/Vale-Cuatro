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

    // Desde la portada se llega con un juego ya elegido (?juego=de-a-cuatro); si no se juega, el primero.
    $juegoElegido = collect($enLaMano)->firstWhere('clave', request('juego'))['clave'] ?? $enLaMano[0]['clave'] ?? null;
    $unaSola = count($enLaMano) === 1;

    // Con una partida sin terminar no se elige nivel: se sigue esa, contra el bot que ya tenía.
    $enCurso ??= null;
    // Sin partida, entra elegido el que le toca por lo que declaró en su perfil (o el de siempre).
    $nivelElegido = $enCurso?->nivel_bot ?? \App\Juego\Experiencia::nivelPara(auth()->user());

    // El torneo que quedó sin terminar, si hay: se vuelve a sus llaves en vez de armar otro.
    $torneo ??= null;
    // De cuántos jugadores entra elegido el torneo: el más corto.
    $lugaresElegidos = 4;
    // Lo que se juega con cada tamaño, dicho en una línea.
    $recorrido = [
        4 => 'Dos rondas, a 15 puntos.',
        8 => 'Tres rondas, a 15 puntos.',
    ];

    /*
     | Con varias cartas, en pantallas anchas la mano va al lado del detalle y no arriba: así el botón de
     | jugar se ve sin bajar en una ventana baja. Hasta dos cartas van en una fila; desde tres, en dos filas
     | y más chicas, para que la mano no sea más alta que el detalle.
     */
    $cartas = count($enLaMano);
    // Sin nada en el mazo, la columna del mazo no existe: la mano toma ese ancho y va en una sola fila.
    $sinMazo = $enElMazo === [];
    // Y en pantallas anchas la pantalla se parte en dos zonas (.modos-dos-zonas en app.css): a la izquierda,
    // sobre el paño, el título y la mano abierta en abanico; a la derecha, un panel de paño hondo con el juego elegido.
    $abierta = $sinMazo && ! $unaSola;
    // Lo que la persona lleva en cada juego, para dibujarlo en el panel: solo en esa disposición.
    $escenas ??= [];
    $columnas = match (true) {
        $sinMazo => max(1, $cartas),
        $cartas <= 2 => max(1, $cartas),
        default => (int) ceil($cartas / 2),
    };
    $anchoDeVarias = match (true) {
        $sinMazo && $cartas > 2 => '7rem',
        $cartas <= 2 => '7.75rem',
        $cartas <= 4 => '7rem',
        default => '5.5rem',
    };
@endphp

<x-layouts.base titulo="Modos de juego" descripcion="Elegí cómo jugar al truco en Vale Cuatro: mano a mano contra la máquina ahora mismo, y los modos que se van sumando." superficie="pano">
    {{-- Las cartas llegan desde afuera de la pantalla: se recorta el costado para que el reparto no agregue scroll. --}}
    <div class="overflow-x-clip">
        <div @class(['modos-inicio mx-auto grid max-w-6xl gap-x-16 gap-y-12 px-5 pb-16 pt-6 sm:px-8 lg:pb-24 lg:pt-14', 'modos-dos-zonas' => $abierta, $unaSola ? 'lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]' : ($sinMazo ? '' : 'lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]')])
            x-data="{ juego: {{ Js::from($juegoElegido) }}, rival: {{ Js::from($rivalElegido) }}, nivel: {{ $nivelElegido->value }}, serie: false, lugares: {{ $lugaresElegidos }}, eligio: false }">
            <section aria-labelledby="titulo-modos">
                <h1 id="titulo-modos" class="pagina-titulo">
                    ¿Cómo querés jugar?
                </h1>

                {{--
                    Con una sola carta, el detalle va al lado. Con varias, la mano va arriba y el detalle debajo;
                    en pantallas anchas vuelve a ir al lado, para que el botón de jugar se vea sin bajar.
                --}}
                <div @class(['modos-mano mt-7 grid items-start gap-y-5 lg:mt-10', $unaSola ? 'grid-cols-[auto_minmax(0,1fr)] gap-x-5 sm:gap-x-9' : 'grid-cols-1 max-sm:gap-y-3 lg:grid-cols-[auto_minmax(0,1fr)] lg:gap-x-9'])>
                    {{-- La mano: una carta por juego que ya se juega. El reparto es el único movimiento que no responde a una acción. --}}
                    <div @class(['mano-juegos', $unaSola ? 'mano-de-una sm:row-span-2' : 'mano-de-varias lg:row-span-2', 'mano-abierta' => $abierta])
                        style="--ancho-carta: clamp(6.25rem, 27vw, 13rem); --medio: {{ (count($enLaMano) - 1) / 2 }}; --cartas: {{ count($enLaMano) }}; --columnas: {{ $columnas }}; --ancho-de-varias: {{ $anchoDeVarias }}">
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
                                    @click="eligio = eligio || juego !== '{{ $juego['clave'] }}'; juego = '{{ $juego['clave'] }}'">
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
                        <div class="modo-juego contents" x-show="juego === '{{ $juego['clave'] }}'" @if ($juego['clave'] !== $juegoElegido) x-cloak @endif>
                            {{-- Después de elegir una carta, su detalle entra con un fundido corto: primero el nombre y enseguida lo demás. --}}
                            <div @class(['modo-nombre', 'self-center sm:self-end' => $unaSola]) :class="{ 'detalle-entra': eligio }">
                                <h2 class="text-2xl font-black leading-[1.02] tracking-tight sm:text-4xl">{{ $juego['nombre'] }}</h2>
                                {{-- Con varias cartas, en el celular el resumen no va: la carta y el nombre ya dicen qué juego es, y así el botón entra en la pantalla. --}}
                                <p @class(['mt-2 max-w-[36ch] leading-relaxed sm:mt-3 sm:text-lg', 'max-sm:hidden' => ! $unaSola])>{{ $juego['resumen'] }}</p>
                            </div>

                            {{-- En el panel, entre el nombre y las opciones: lo que la persona lleva en ese juego, dibujado. --}}
                            @if ($abierta && isset($escenas[$juego['clave']]))
                                <div class="modo-escena max-lg:hidden" :class="{ 'detalle-entra': eligio }" style="animation-delay: 25ms">
                                    <x-modos.escena :juego="$juego['clave']" :datos="$escenas[$juego['clave']]" />
                                </div>
                            @endif

                            <div @class(['modo-opciones', 'col-span-2 sm:col-span-1 sm:col-start-2' => $unaSola, 'lg:col-start-2' => ! $unaSola]) :class="{ 'detalle-entra': eligio }" style="animation-delay: 50ms">
                                @if (count($juego['rivales']) > 1)
                                    {{-- En el celular, un punto más chicos: "Contra la máquina" y "Con otra persona" (o "Entre personas") tienen que entrar en un renglón. --}}
                                    <div class="flex flex-wrap gap-x-7 gap-y-1 max-sm:gap-x-5 lg:gap-x-5" role="group" aria-label="Contra quién">
                                        @foreach ($juego['rivales'] as $rival)
                                            <button type="button" class="enlace-nav cursor-pointer text-lg font-bold max-sm:text-base lg:text-[clamp(1rem,1.3vw,1.125rem)]"
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
                                                $lugares = $rival['lugares'] ?? [];
                                                // De a cuatro las máquinas son tres, y en el gallito dos: se dicen en plural. En los dos no hay series.
                                                $asientos = $rival['campos']['asientos'] ?? 2;
                                                $deACuatro = $asientos > 2;
                                                $conSerie = $rival['serie'] ?? true;
                                                // Con una partida sin terminar, cualquiera de los botones la sigue: es una sola a la vez.
                                                $sigue = $enCurso !== null;
                                                // Con un torneo sin terminar (y ninguna partida abierta), el torneo no se arma: se vuelve a sus llaves.
                                                $sigueElTorneo = $lugares !== [] && $torneo !== null && ! $sigue;
                                                // Los desafíos y la gira tienen su propia pantalla: el botón lleva ahí, no a una mesa.
                                                $esEnlace = $rival['enlace'] ?? false;
                                            @endphp

                                            @if ($sigue)
                                                <p class="mt-4 max-w-[36ch] font-bold leading-relaxed">
                                                    @if ($enCurso->esperando())
                                                        {{-- Dicho de entrada y con la salida al lado: sin esto los niveles apagados parecen un error. --}}
                                                        Tenés una sala abierta esperando rival. Para jugar contra la máquina o en otro modo, primero cancelala.
                                                    @elseif ($enCurso->entre_personas)
                                                        Tenés una partida sin terminar con otra persona.
                                                    @elseif ($enCurso->torneo_id)
                                                        Tenés una partida del torneo sin terminar.
                                                    @elseif ($enCurso->esDePractica())
                                                        Tenés {{ $enCurso->escalon ? 'un escalón' : 'un desafío' }} sin terminar.
                                                    @elseif ($enCurso->deACuatro())
                                                        Tenés una partida de a cuatro sin terminar.
                                                    @elseif ($enCurso->esGallito())
                                                        Tenés un gallito sin terminar.
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
                                                <div class="mt-3 grid max-w-[21rem] grid-cols-4" role="group" aria-label="Nivel de la máquina">
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
                                                        @if ($deACuatro)
                                                            <p class="leading-relaxed" x-show="nivel === {{ $opcion->value }}" @if ($opcion !== $nivelElegido) x-cloak @endif><span class="block font-bold">Máquina {{ $opcion->deLaMaquina() }}, en los {{ $asientos === 4 ? 'tres' : 'dos' }} lugares</span>{{ $opcion->detalleDeACuatro() }}</p>
                                                        @else
                                                            <p class="leading-relaxed" x-show="nivel === {{ $opcion->value }}" @if ($opcion !== $nivelElegido) x-cloak @endif><span class="block font-bold">Máquina {{ $opcion->deLaMaquina() }}</span>{{ $opcion->detalle() }}</p>
                                                        @endif
                                                    @endforeach

                                                    @unless ($sigue || ! $conSerie)
                                                        {{-- Mide un renglón, pero se toca en un área más alta (el ::before), para que el dedo no le erre. --}}
                                                        <label class="absolute right-0 top-0 flex h-[1.625rem] cursor-pointer items-center gap-2 font-semibold before:absolute before:-inset-x-2 before:-inset-y-2.5 before:content-['']" data-formato>
                                                            <input type="checkbox" class="casilla sr-only" x-model="serie">
                                                            <span class="casilla-caja" aria-hidden="true"><x-icono nombre="quiero" /></span>
                                                            Al mejor de tres
                                                        </label>
                                                    @endunless
                                                </div>
                                            @elseif ($sigueElTorneo)
                                                <p class="mt-4 max-w-[36ch] font-bold leading-relaxed">Tenés un torneo sin terminar.</p>
                                            @elseif ($lugares !== [])
                                                {{--
                                                    De cuántos jugadores. Sale del mismo molde que los niveles del bot: el cuadrado de cuatro fósforos,
                                                    uno para el torneo de cuatro y dos para el de ocho. Los del tamaño elegido caen de a uno.
                                                --}}
                                                <div class="mt-3 grid max-w-[21rem] grid-cols-4" role="group" aria-label="De cuántos jugadores">
                                                    @foreach ($lugares as $opcion)
                                                        <button type="button" @disabled($sigue)
                                                            class="nivel-opcion col-span-2 cursor-pointer py-1 text-left text-lg font-bold disabled:pointer-events-none disabled:cursor-default disabled:aria-[pressed=false]:opacity-70"
                                                            aria-pressed="{{ $opcion === $lugaresElegidos ? 'true' : 'false' }}"
                                                            :aria-pressed="(lugares === {{ $opcion }}).toString()"
                                                            @click="lugares = {{ $opcion }}"><span class="mb-1.5 flex gap-[0.35em] text-[1.25rem]">@for ($cuadrado = 0; $cuadrado < $opcion / 4; $cuadrado++)<x-nivel-fosforos :nivel="\App\Juego\Nivel::UltraDificil" :puesto="$opcion === $lugaresElegidos" modelo="lugares === {{ $opcion }}" />@endfor</span>De {{ $opcion }}</button>
                                                    @endforeach
                                                </div>

                                                <div class="mt-1 max-w-[36ch]">
                                                    @foreach ($lugares as $opcion)
                                                        <p class="leading-relaxed" x-show="lugares === {{ $opcion }}" @if ($opcion !== $lugaresElegidos) x-cloak @endif>{{ $recorrido[$opcion] }}</p>
                                                    @endforeach
                                                </div>
                                            @elseif (! $sigue && ! $esEnlace)
                                                {{-- Con otra persona no hay nivel que elegir: la casilla de la serie va sola, arriba del botón. --}}
                                                <label class="mt-3 flex min-h-11 w-fit cursor-pointer items-center gap-3 font-semibold" data-formato>
                                                    <input type="checkbox" class="casilla sr-only" x-model="serie">
                                                    <span class="casilla-caja" aria-hidden="true"><x-icono nombre="quiero" /></span>
                                                    Al mejor de tres
                                                </label>
                                            @endif

                                            {{-- Sin campos a la vista: apretar el botón alcanza. Quien no tiene sesión entra como invitado. --}}
                                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                                @if ($sigueElTorneo)
                                                    <a href="{{ route('torneo', $torneo) }}" class="boton boton-naipe min-h-14 px-6 text-lg"><x-icono nombre="torneo" /> Seguir el torneo</a>
                                                @elseif ($esEnlace && ! $sigue)
                                                    <a href="{{ route($rival['ruta']) }}" class="boton boton-naipe min-h-14 px-6 text-lg"><x-icono :nombre="$rival['icono']" /> {{ $rival['boton'] }}</a>
                                                @else
                                                <form method="POST" action="{{ route($rival['ruta'] ?? 'jugar') }}">
                                                    @csrf
                                                    @if ($niveles !== [])
                                                        <input type="hidden" name="nivel" value="{{ $nivelElegido->value }}" :value="nivel">
                                                    @endif
                                                    @if ($lugares !== [])
                                                        <input type="hidden" name="lugares" value="{{ $lugaresElegidos }}" :value="lugares">
                                                    @endif
                                                    @foreach ($rival['campos'] ?? [] as $campo => $valor)
                                                        <input type="hidden" name="{{ $campo }}" value="{{ $valor }}">
                                                    @endforeach
                                                    {{-- Una partida, o una serie al mejor de tres: lo dice la casilla de arriba. En un torneo y de a cuatro no hay series. --}}
                                                    @if ($lugares === [] && $conSerie)
                                                        <input type="hidden" name="serie" value="0" :value="serie ? 1 : 0">
                                                    @endif
                                                    <button type="submit" class="boton boton-naipe min-h-14 px-6 text-lg">
                                                        <x-icono :nombre="$rival['icono'] ?? 'bot'" />
                                                        {{ $sigue ? ($enCurso->esperando() ? 'Volver a la sala' : 'Seguir la partida') : $rival['boton'] }}
                                                    </button>
                                                </form>
                                                @endif

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
