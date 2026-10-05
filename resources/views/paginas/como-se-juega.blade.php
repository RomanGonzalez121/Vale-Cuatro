@php
    use App\View\Components\Carta;

    $todas = fn (int $numero) => array_map(fn (string $palo) => [$palo, $numero], Carta::PALOS);
    $partes = fn (string $carta) => [explode('-', $carta)[1], (int) explode('-', $carta)[0]];

    $mandan = [['espada', 1], ['basto', 1], ['espada', 7], ['oro', 7]];

    // Del escalón 5 al 14. Las cartas de un mismo escalón empatan entre sí.
    $resto = [
        ['Los 3', $todas(3)],
        ['Los 2', $todas(2)],
        ['El 1 de copa y el 1 de oro', [['copa', 1], ['oro', 1]]],
        ['Los 12', $todas(12)],
        ['Los 11', $todas(11)],
        ['Los 10', $todas(10)],
        ['El 7 de copa y el 7 de basto', [['copa', 7], ['basto', 7]]],
        ['Los 6', $todas(6)],
        ['Los 5', $todas(5)],
        ['Los 4', $todas(4)],
    ];

    // Cada caso: nombre, bazas como [carta tuya, carta del rival, resultado] y quién gana la mano.
    $pardas = [
        ['Parda la primera', [['3-espada', '3-copa', 'parda'], ['7-oro', '12-basto', 'vos']], 'Gana quien gana la segunda. Acá, vos.'],
        ['Pardas las dos primeras', [['3-espada', '3-copa', 'parda'], ['2-oro', '2-basto', 'parda'], ['1-basto', '7-copa', 'vos']], 'Gana quien gana la tercera. Acá, vos.'],
        ['Pardas las tres', [['3-espada', '3-copa', 'parda'], ['2-oro', '2-basto', 'parda'], ['11-espada', '11-oro', 'parda']], 'Gana el mano, es decir, quien salió primero.'],
        ['Parda la segunda', [['1-espada', '5-oro', 'vos'], ['6-copa', '6-basto', 'parda']], 'Gana quien ganó la primera. Acá, vos.'],
        ['Parda la tercera', [['7-espada', '4-oro', 'vos'], ['5-basto', '12-copa', 'rival'], ['10-espada', '10-copa', 'parda']], 'Iban una y una: gana quien ganó la primera. Acá, vos.'],
    ];

    $cadenas = [
        ['Envido', '2', '1'],
        ['Real envido', '3', '1'],
        ['Falta envido', 'la falta', '1'],
        ['Envido, envido', '4', '2'],
        ['Envido, real envido', '5', '2'],
        ['Envido, falta envido', 'la falta', '2'],
        ['Real envido, falta envido', 'la falta', '3'],
        ['Envido, envido, real envido', '7', '4'],
        ['Envido, envido, falta envido', 'la falta', '4'],
        ['Envido, real envido, falta envido', 'la falta', '5'],
        ['Envido, envido, real envido, falta envido', 'la falta', '7'],
    ];

    $trucos = [
        ['Sin cantar', 1, null, 'La mano vale 1.'],
        ['Truco', 2, 1, 'Vale 2 si se quiere y 1 si no.'],
        ['Retruco', 3, 2, 'Vale 3 si se quiere y 2 si no.'],
        ['Vale cuatro', 4, 3, 'Vale 4 si se quiere y 3 si no. No se puede subir más.'],
    ];

    $secciones = [
        'basico' => 'Lo básico',
        'cartas' => 'El orden de las cartas',
        'pardas' => 'Las pardas',
        'envido' => 'El envido',
        'flor' => 'La flor',
        'truco' => 'El truco',
        'mazo' => 'Irse al mazo',
        'tanteo' => 'El tanteo',
    ];
@endphp

<x-layouts.base titulo="Cómo se juega" descripcion="El reglamento de truco que usa Vale Cuatro, explicado con las cartas del sitio y para probar.">
    <x-mazo.plantillas />

    <div class="mx-auto max-w-6xl px-5 pb-24 sm:px-8">
        <header class="grid items-end gap-8 pb-10 pt-10 sm:pt-16 lg:grid-cols-[1fr_auto]">
            <div>
                <h1 class="text-5xl font-black tracking-tight sm:text-7xl">Cómo se juega</h1>
                <p class="mt-5 max-w-[56ch] text-lg leading-relaxed sm:text-xl">
                    El truco cambia en cada provincia. Este es el reglamento de Vale Cuatro, explicado para quien nunca jugó.
                    Cada regla tiene una mesa para probarla.
                </p>
            </div>
            <form method="POST" action="{{ route('jugar') }}" class="justify-self-start">
                @csrf
                <button type="submit" class="boton boton-tinta"><x-icono nombre="bot" /> Jugar contra el bot</button>
            </form>
        </header>

        <nav aria-label="En esta página" class="border-y-2 border-texto py-4">
            <ul class="flex flex-wrap gap-x-7 gap-y-1">
                @foreach ($secciones as $ancla => $nombre)
                    <li><a href="#{{ $ancla }}" class="enlace-nav">{{ $nombre }}</a></li>
                @endforeach
            </ul>
        </nav>

        <section id="basico" class="grid scroll-mt-6 items-center gap-10 py-14 lg:grid-cols-2 lg:gap-16">
            <div>
                <h2 class="text-4xl font-black tracking-tight sm:text-5xl">Lo básico</h2>
                <div class="mt-5 max-w-[52ch] space-y-4 text-lg leading-relaxed">
                    <p>Se juega de a dos, con una baraja española de 40 cartas: no hay 8 ni 9. En cada mano cada uno recibe tres cartas.</p>
                    <p>Una mano tiene hasta tres bazas. En cada baza cada uno tira una carta y gana la más fuerte. Quien gana dos bazas gana la mano.</p>
                    <p>La partida es a 30 puntos: las primeras 15 son las malas y las otras 15, las buenas. El mano es quien sale primero, y cambia en cada mano.</p>
                </div>
            </div>

            <div class="superficie-pano rounded-xl px-6 py-9" aria-hidden="true">
                <div class="flex justify-center gap-2">
                    @foreach ([1, 2, 3] as $dorso)
                        <div class="w-[15%] max-w-16"><x-dorso /></div>
                    @endforeach
                </div>
                <div class="abanico mt-8" style="--ancho-carta: clamp(5.5rem, 24vw, 8rem)">
                    <div><x-carta palo="oro" :numero="7" /></div>
                    <div><x-carta palo="espada" :numero="1" /></div>
                    <div><x-carta palo="basto" :numero="3" /></div>
                </div>
            </div>
        </section>

        <section id="cartas" class="scroll-mt-6 border-t-2 border-texto py-14">
            <h2 class="text-4xl font-black tracking-tight sm:text-5xl">El orden de las cartas</h2>
            <p class="mt-5 max-w-[56ch] text-lg leading-relaxed">
                Las cartas no valen por su número. Hay catorce escalones: del 1 de espada, que le gana a todas, a los 4.
                Las que comparten escalón empatan.
            </p>

            <div class="superficie-pano mt-8 rounded-xl p-5 sm:p-9">
                <h3 class="text-2xl font-extrabold">Las cuatro que mandan</h3>
                {{-- Las cuatro en un renglón, también en el celular: ahí el número va arriba del nombre. --}}
                <ol class="mt-6 grid grid-cols-4 gap-x-2.5 max-sm:mt-5 sm:gap-x-8">
                    @foreach ($mandan as $i => [$palo, $numero])
                        <li>
                            <div class="max-w-40"><x-carta :palo="$palo" :numero="$numero" /></div>
                            <p class="mt-3 font-bold leading-tight max-sm:mt-2 max-sm:text-[0.8rem]"><span class="text-2xl font-black text-oro max-sm:block">{{ $i + 1 }}</span> El {{ $numero }} de {{ $palo }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>

            <ol start="5" class="mt-8 grid gap-x-12 lg:grid-cols-2">
                @foreach ($resto as $i => [$nombre, $cartas])
                    <li class="grid grid-cols-[2.5rem_1fr] items-center gap-x-3 gap-y-2 border-b border-texto/15 py-3.5 sm:grid-cols-[2.5rem_11rem_1fr]">
                        <span class="text-2xl font-black tabular-nums">{{ $i + 5 }}</span>
                        <span class="text-lg font-bold leading-tight">{{ $nombre }}</span>
                        <span class="col-start-2 flex gap-1.5 sm:col-start-3">
                            @foreach ($cartas as [$palo, $numero])
                                <span class="w-11 flex-none sm:w-12"><x-carta :palo="$palo" :numero="$numero" /></span>
                            @endforeach
                        </span>
                    </li>
                @endforeach
            </ol>

            <div x-data="duelo" class="superficie-pano mt-10 grid items-center gap-8 rounded-xl p-6 sm:p-9 lg:grid-cols-[1fr_1.1fr]">
                <div>
                    <h3 class="text-3xl font-black tracking-tight">Probá: ¿cuál gana?</h3>
                    <p aria-live="polite" class="mt-4 min-h-20 max-w-[40ch] text-lg leading-snug" x-text="veredicto"></p>
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <button type="button" class="boton boton-linea" x-show="elegida === null" @click="elegir('parda')">Empatan</button>
                        <button type="button" class="boton boton-naipe" x-show="elegida !== null" x-cloak @click="repartir()">
                            <x-icono nombre="repartir" /> Otro par
                        </button>
                        <p class="font-semibold tabular-nums" x-show="intentos > 0" x-cloak>
                            <span x-text="aciertos"></span> de <span x-text="intentos"></span>
                        </p>
                    </div>
                </div>

                <div x-ref="lugares" class="flex items-center justify-center gap-4 sm:gap-7">
                    @foreach ([0, 1] as $lugar)
                        <button type="button" data-lugar class="naipe-jugable carta-duelo w-[40%] max-w-40" :class="estado({{ $lugar }})"
                            :disabled="elegida !== null" :aria-label="'Gana el ' + nombre({{ $lugar }})" @click="elegir({{ $lugar }})"></button>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="pardas" class="scroll-mt-6 border-t-2 border-texto py-14" x-data="{ caso: 0 }">
            <h2 class="text-4xl font-black tracking-tight sm:text-5xl">Las pardas</h2>
            <p class="mt-5 max-w-[56ch] text-lg leading-relaxed">
                Una baza es parda cuando las dos cartas empatan. Después de una parda sale el mano.
                Elegí un caso para ver quién se lleva la mano.
            </p>

            <div class="mt-7 flex flex-wrap gap-2" role="group" aria-label="Casos de parda">
                @foreach ($pardas as $i => [$nombre])
                    <button type="button" class="pestana" :aria-pressed="(caso === {{ $i }}).toString()" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}" @click="caso = {{ $i }}">{{ $nombre }}</button>
                @endforeach
            </div>

            @foreach ($pardas as $i => [$nombre, $bazas, $resultado])
                <div class="superficie-pano mt-5 rounded-xl p-6 sm:p-9" x-show="caso === {{ $i }}" @if ($i > 0) x-cloak @endif
                    x-transition:enter="aparece" x-transition:enter-start="desde-chico">
                    <ol class="flex flex-wrap gap-x-8 gap-y-6 sm:gap-x-12">
                        @foreach ($bazas as $numero => [$tuya, $suya, $quien])
                            <li>
                                <p class="mb-2.5 text-sm font-semibold">{{ $numero + 1 }}ª baza, {{ ['vos' => 'tuya', 'rival' => 'del rival', 'parda' => 'parda'][$quien] }}</p>
                                <div class="flex gap-2.5">
                                    @foreach ([[$tuya, 'Vos', 'vos'], [$suya, 'Rival', 'rival']] as [$carta, $rotulo, $jugador])
                                        <figure class="w-[4.75rem] sm:w-24">
                                            <x-carta :palo="$partes($carta)[0]" :numero="$partes($carta)[1]" :class="$quien !== 'parda' && $quien !== $jugador ? 'opacity-45' : ''" />
                                            <figcaption class="mt-1.5 text-center text-xs font-semibold">{{ $rotulo }}</figcaption>
                                        </figure>
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    <p class="mt-7 text-xl font-extrabold leading-snug">{{ $resultado }}</p>
                </div>
            @endforeach
        </section>

        <section id="envido" class="scroll-mt-6 border-t-2 border-texto py-14">
            <div class="grid items-start gap-10 lg:grid-cols-2 lg:gap-16">
                <div>
                    <h2 class="text-4xl font-black tracking-tight sm:text-5xl">El envido</h2>
                    <div class="mt-5 max-w-[52ch] space-y-4 text-lg leading-relaxed">
                        <p>El envido es una apuesta aparte sobre quién tiene más tanto. Se canta en la primera baza, antes de jugar tu primera carta.</p>
                        <p>Dos cartas del mismo palo suman sus valores más 20. Las figuras, que son el 10, el 11 y el 12, valen 0. Si empatan, gana el mano.</p>
                        <p>Si te cantan truco en la primera baza y todavía no hubo envido, podés contestar con envido: el envido está primero.</p>
                    </div>
                </div>

                <div x-data="tantoDeEnvido" class="superficie-pano rounded-xl p-6 sm:p-9">
                    <h3 class="text-2xl font-extrabold">¿Cuánto tenés de envido?</h3>
                    <div x-ref="lugares" class="mt-6 flex justify-center gap-3">
                        @foreach ([0, 1, 2] as $lugar)
                            <div data-lugar class="aspect-[100/156] w-[28%] max-w-28"></div>
                        @endforeach
                    </div>
                    <p class="mt-6 flex items-baseline gap-4" aria-live="polite">
                        <span class="canto canto-oro text-7xl" x-text="tanto"></span>
                        <span class="max-w-[30ch] text-lg leading-snug" x-text="cuenta"></span>
                    </p>
                    <button type="button" class="boton boton-oro mt-6" @click="repartir()"><x-icono nombre="repartir" /> Repartir otras tres</button>
                </div>
            </div>

            <h3 class="mt-14 text-2xl font-extrabold">Cuánto vale</h3>
            <div class="mt-3 max-w-[60ch] space-y-4 text-lg leading-relaxed">
                <p>El canto se puede subir: envido, envido, real envido y falta envido. Siempre para arriba; se pueden saltear pasos, pero no volver atrás.</p>
                <p>La falta envido vale lo que le falta al que va ganando para llegar a 30. Decir "no quiero" le da al otro lo que valían los cantos anteriores, o 1 si era el primero.</p>
            </div>
            <div class="mt-6 overflow-x-auto">
                {{-- En el celular la tabla entra en el ancho: el canto largo baja de renglón y los números quedan a la vista. --}}
                <table class="tabla max-w-2xl sm:min-w-[30rem]">
                    <thead>
                        <tr>
                            <th scope="col">Lo que se cantó</th>
                            <th scope="col" class="numero">Querido</th>
                            <th scope="col" class="numero">No querido</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cadenas as [$cadena, $querido, $noQuerido])
                            <tr>
                                <td>{{ $cadena }}</td>
                                <td class="numero font-bold">{{ $querido }}</td>
                                <td class="numero font-bold">{{ $noQuerido }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section id="flor" class="grid scroll-mt-6 items-center gap-10 border-t-2 border-texto py-14 lg:grid-cols-2 lg:gap-16">
            <div>
                <h2 class="text-4xl font-black tracking-tight sm:text-5xl">La flor</h2>
                <div class="mt-5 max-w-[52ch] space-y-4 text-lg leading-relaxed">
                    <p>Flor es tener las tres cartas del mismo palo. Vale 3 puntos y, si la cantás, anula el envido de esa mano.</p>
                    <p>Cantarla es opcional: podés callarla y jugar el envido con tus dos mejores cartas. Pero si no la cantás antes de tu primera carta, la perdés.</p>
                    <p>Si los dos tienen flor, se puede cantar contraflor, que vale 6 querida y 4 no querida, o contraflor al resto, que vale lo mismo que la falta envido y 6 si no se quiere.</p>
                </div>
            </div>

            <div class="superficie-pano rounded-xl p-6 sm:p-9">
                <div class="flex justify-center gap-3">
                    @foreach ([3, 5, 12] as $numero)
                        <div class="w-[28%] max-w-28"><x-carta palo="copa" :numero="$numero" /></div>
                    @endforeach
                </div>
                <p class="mt-6 flex items-baseline gap-4">
                    <span class="canto canto-oro text-7xl">Flor</span>
                    <span class="max-w-[26ch] text-lg leading-snug">Tres de copa: 3 más 5 más 0 más 20 son 28.</span>
                </p>
            </div>
        </section>

        <section id="truco" class="grid scroll-mt-6 items-center gap-10 border-t-2 border-texto py-14 lg:grid-cols-2 lg:gap-16" x-data="{ nivel: 0 }">
            <div>
                <h2 class="text-4xl font-black tracking-tight sm:text-5xl">El truco</h2>
                <div class="mt-5 max-w-[52ch] space-y-4 text-lg leading-relaxed">
                    <p>El truco sube lo que vale la mano. Quien lo recibe puede querer, no querer o subirlo.</p>
                    <p>Solo puede subirlo quien tiene el quiero, es decir, el último que aceptó.</p>
                </div>
                <div class="mt-6 flex flex-wrap gap-3">
                    <button type="button" class="boton boton-copa" :disabled="nivel === 3" @click="nivel++">
                        <x-icono nombre="truco" /> <span x-text="['Cantar truco', 'Subir a retruco', 'Subir a vale cuatro', 'Vale cuatro'][nivel]">Cantar truco</span>
                    </button>
                    <button type="button" class="boton boton-linea" x-show="nivel > 0" x-cloak @click="nivel = 0">Empezar de nuevo</button>
                </div>
            </div>

            <div class="superficie-pano flex min-h-72 flex-col items-center justify-center overflow-hidden rounded-xl p-6 text-center sm:p-9" aria-live="polite">
                @foreach ($trucos as $i => [$canto, $vale, $noQuerido, $texto])
                    <div x-show="nivel === {{ $i }}" @if ($i > 0) x-cloak @endif x-transition:enter="aparece" x-transition:enter-start="desde-chico">
                        @if ($i === 0)
                            <p class="text-2xl font-extrabold">Nadie cantó todavía</p>
                        @else
                            <p class="canto canto-ficha canto-copa -rotate-2 text-[clamp(3.25rem,13vw,6rem)]">{{ $canto }}</p>
                        @endif
                        <p class="mt-6 text-lg font-semibold leading-snug">{{ $texto }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section id="mazo" class="scroll-mt-6 border-t-2 border-texto py-14">
            <h2 class="text-4xl font-black tracking-tight sm:text-5xl">Irse al mazo</h2>
            <div class="mt-5 max-w-[56ch] space-y-4 text-lg leading-relaxed">
                <p>Podés irte al mazo en cualquier momento. El rival suma lo que valía la mano.</p>
                <p>Si te vas en la primera baza sin que se haya cantado envido ni flor, el rival suma 2: uno por la mano y uno por el envido que no se llegó a jugar.</p>
            </div>
        </section>

        <section id="tanteo" class="grid scroll-mt-6 items-center gap-10 border-t-2 border-texto py-14 lg:grid-cols-2 lg:gap-16" x-data="{ puntos: 12 }">
            <div>
                <h2 class="text-4xl font-black tracking-tight sm:text-5xl">El tanteo</h2>
                <div class="mt-5 max-w-[52ch] space-y-4 text-lg leading-relaxed">
                    <p>Los puntos se anotan con fósforos, en grupos de cinco: cuatro en cuadrado y uno cruzado. A la izquierda de la raya van las malas y a la derecha, las buenas.</p>
                    <p>Los puntos del envido y de la flor se anotan antes que los del truco.</p>
                </div>
                <div class="mt-6 flex flex-wrap gap-3">
                    <button type="button" class="boton boton-oro" :disabled="puntos >= 30" @click="puntos++">Anotar un punto</button>
                    <button type="button" class="boton boton-linea" @click="puntos = 0">Volver a cero</button>
                </div>
            </div>

            <div class="rounded-xl bg-pano-hondo p-6 text-[1.15rem] sm:p-9 sm:text-[1.7rem]">
                <x-tanteador nombre="Vos" :puntos="0" modelo="puntos" />
                <p class="mt-5 text-lg font-semibold text-naipe" aria-live="polite"
                    x-text="puntos === 30 ? 'Treinta: ganaste la partida.' : puntos > 15 ? `Estás en las buenas: llevás ${puntos - 15}.` : puntos === 15 ? 'Quince: completaste las malas.' : `Estás en las malas: llevás ${puntos}.`"></p>
            </div>
        </section>
    </div>
</x-layouts.base>
