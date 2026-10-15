@php
    $mandan = [
        ['espada', 1, 'El ancho de espada'],
        ['basto', 1, 'El ancho de basto'],
        ['espada', 7, 'El siete de espada'],
        ['oro', 7, 'El siete de oro'],
    ];

    // Los juegos que ya se juegan, cada uno con su carta, una línea y el camino directo a su pantalla.
    $juegos = array_values(array_filter(App\Juego\Modos::juegos(), App\Juego\Modos::seJuega(...)));
    $enUnaLinea = [
        'mano-a-mano' => 'Uno contra uno, a 30 y con flor.',
        'de-a-cuatro' => 'Dos contra dos, con señas.',
        'torneo' => 'Cuatro u ocho, eliminación directa.',
        'desafios' => 'Manos armadas con un objetivo.',
        'escalera' => 'Ocho escalones, de a uno.',
    ];
    $destino = fn (array $juego) => match ($juego['clave']) {
        'desafios' => route('desafios'),
        'escalera' => route('escalera'),
        default => route('modos', ['juego' => $juego['clave']]),
    };
@endphp

<x-layouts.base superficie="pano" :apertura="true">
    {{--
        En el celular el bloque de texto se desarma (`contents`) para que la mano de cartas quede entre el
        título y el botón. La primera pantalla mide justo lo que queda entre el encabezado y la barra de abajo,
        y el título, la mano y el canto se achican con el alto de la ventana (`.portada-inicio` en app.css).
        Desde `sm` vuelve a ser un bloque y la mano va después.
    --}}
    <section class="portada-inicio mx-auto grid max-w-6xl items-center gap-12 px-5 pb-16 pt-6 max-sm:gap-0 max-sm:pb-5 max-sm:pt-2 sm:px-8 lg:grid-cols-[1.1fr_1fr] lg:gap-8 lg:pb-24 lg:pt-14">
        <div class="max-sm:contents">
            <h1 class="text-[clamp(2.75rem,7.4vw,5.5rem)] font-black leading-[0.94] tracking-[-0.035em] max-sm:order-1">
                <span class="block">Truco</span>
                <span class="block">argentino,</span>
                <span class="block">mano a mano.</span>
            </h1>
            <p class="portada-bajada mt-6 max-w-[44ch] text-lg leading-relaxed max-sm:order-2 max-sm:mt-4 max-sm:leading-snug sm:text-xl">
                Jugá una mano contra la máquina ahora mismo, sin registrarte.
                <span class="max-sm:hidden">O mandale un link a alguien y jueguen en vivo.</span>
            </p>
            {{-- Sin campos: apretar el botón alcanza. Quien no tiene sesión entra como invitado. --}}
            <form method="POST" action="{{ route('jugar') }}" class="mt-8 flex flex-wrap gap-3 max-sm:order-4 max-sm:mt-6">
                @csrf
                <button type="submit" class="boton boton-naipe min-h-14 px-6 text-lg max-sm:w-full">
                    <x-icono nombre="bot" /> Jugar contra la máquina
                </button>
                {{-- Los demás modos se eligen en su pantalla: este botón no entra a ninguna mesa. En el celular ya está "Jugar" en la barra de abajo. --}}
                <a href="{{ route('modos') }}" class="boton boton-linea min-h-14 px-6 text-lg max-sm:hidden">
                    <x-icono nombre="repartir" /> Elegir otro modo
                </a>
            </form>
            <p class="mt-5 text-[0.95rem] max-sm:hidden">
                A 30 puntos y con flor. ¿Nunca jugaste?
                <a href="{{ route('como-se-juega') }}" class="font-semibold underline underline-offset-4">Mirá cómo se juega</a>.
            </p>
        </div>

        {{-- Una mano en miniatura: el reparto es el único movimiento que no responde a una acción. --}}
        <div class="portada-mano relative mx-auto w-full max-w-md max-sm:order-3 max-sm:mt-5" aria-hidden="true">
            <div class="flex justify-center gap-2.5 max-sm:hidden">
                @foreach ([1, 3, 5] as $orden)
                    <div class="se-reparte w-[17%]" style="--orden: {{ $orden }}"><x-dorso /></div>
                @endforeach
            </div>

            <div class="relative z-10 -mb-4 mt-7 flex justify-center max-sm:mt-0">
                <p class="canto canto-ficha canto-copa se-canta portada-canto sm:text-[clamp(4.75rem,23vw,8.75rem)]">Truco</p>
            </div>

            <div class="abanico sm:[--ancho-carta:clamp(6.25rem,31vw,10.5rem)]">
                <div><div class="se-reparte" style="--orden: 0"><x-carta palo="oro" :numero="7" /></div></div>
                <div><div class="se-reparte" style="--orden: 2"><x-carta palo="espada" :numero="1" /></div></div>
                <div><div class="se-reparte" style="--orden: 4"><x-carta palo="basto" :numero="3" /></div></div>
            </div>
        </div>
    </section>

    {{--
        Debajo de la primera pantalla, todo con el mismo armado: el título de sección del sitio, una línea y
        lo que se puede tocar. Primero los juegos, que es a lo que se viene.
    --}}
    <section aria-labelledby="titulo-juegos" class="border-t border-naipe/20">
        <div class="mx-auto max-w-6xl px-5 py-14 sm:px-8 lg:py-20">
            <h2 id="titulo-juegos" class="seccion-titulo">Formas de jugar.</h2>

            {{-- En el celular, un renglón por juego con su carta chica; desde `md`, las cartas en fila, como una mano. --}}
            <ul class="portada-juegos mt-8">
                @foreach ($juegos as $juego)
                    <li>
                        <a href="{{ $destino($juego) }}" class="portada-juego">
                            <span class="portada-juego-carta" aria-hidden="true">
                                <x-carta-modo :icono="$juego['icono']" :nombre="$juego['nombre']" :renglones="$juego['renglones']" />
                            </span>
                            <span class="min-w-0">
                                <span class="enlace-nav text-lg font-extrabold leading-tight">{{ $juego['nombre'] }}</span>
                                <span class="mt-1 block leading-snug">{{ $enUnaLinea[$juego['clave']] ?? $juego['resumen'] }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{--
        Instalarlo como aplicación. La sección aparece solo cuando se puede (resources/js/instalar.js): si el
        navegador no lo ofrece o ya está instalado, no ocupa lugar. El dibujo es el ícono con su nombre debajo,
        tal como queda en la pantalla del celular. En un iPhone se instala a mano: en vez del botón, los dos pasos.
    --}}
    <section aria-labelledby="titulo-instalar" class="border-t border-naipe/20" data-donde-instalar hidden>
        <div class="mx-auto grid max-w-6xl items-center gap-x-10 gap-y-7 px-5 py-14 sm:px-8 md:grid-cols-[auto_minmax(0,1fr)_auto] lg:py-16">
            <figure class="flex items-center gap-4 md:block md:text-center" aria-hidden="true">
                <img src="/iconos/icono-192.png" alt="" width="192" height="192" class="size-16 rounded-[22%] border-2 border-naipe/25 md:size-24">
                <figcaption class="text-sm font-semibold md:mt-2">Vale Cuatro</figcaption>
            </figure>

            <div>
                <h2 id="titulo-instalar" class="seccion-titulo">Llevalo en el celular.</h2>
                {{-- Lo que gana quien lo instala, en tres renglones cortos de la misma planilla. --}}
                <ul class="instalar-ventajas mt-5">
                    <li>Abre a pantalla completa, como una aplicación.</li>
                    <li>Te avisa cuando te toca, si jugás con otra persona.</li>
                    <li>Se instala desde acá, sin tienda.</li>
                </ul>
            </div>

            <div>
                <button type="button" class="boton boton-naipe min-h-14 px-6 text-lg max-md:w-full" data-instalar hidden>
                    <x-icono nombre="instalar" /> Instalar
                </button>

                {{-- En el iPhone no hay botón: se hace desde el navegador. Los dos pasos, con los dibujos de sus botones. --}}
                <ol class="instalar-pasos" data-instalar-en-iphone hidden>
                    <li><x-icono nombre="compartir" class="size-7" /> <span><strong>1.</strong> Tocá Compartir</span></li>
                    <li><x-icono nombre="agregar" class="size-7" /> <span><strong>2.</strong> Elegí «Agregar a inicio»</span></li>
                </ol>
            </div>
        </div>
    </section>

    <section aria-labelledby="titulo-fosforos" class="sobre-pano bg-pano-hondo" x-data="{ vos: 13 }">
        <div class="mx-auto grid max-w-6xl gap-10 px-5 py-14 sm:px-8 lg:grid-cols-2 lg:items-center lg:py-20">
            <div>
                <h2 id="titulo-fosforos" class="seccion-titulo">Se anota con fósforos.</h2>
                <p class="mt-4 max-w-[48ch] text-lg leading-relaxed">Cuatro en cuadrado y uno cruzado son cinco puntos.</p>
                <button type="button" class="boton boton-oro mt-6" :disabled="vos >= 30" @click="vos++">
                    Anotar un punto
                </button>
            </div>

            <div class="flex flex-col gap-7 text-[1.2rem] sm:text-[1.75rem]">
                <x-tanteador nombre="Vos" :puntos="13" modelo="vos" />
                <x-tanteador nombre="Rival" :puntos="9" />
            </div>
        </div>
    </section>

    <section aria-labelledby="titulo-mandan" class="mx-auto max-w-6xl px-5 py-14 sm:px-8 lg:py-20">
        <h2 id="titulo-mandan" class="seccion-titulo">Las cuatro que mandan.</h2>
        {{-- El mazo es propio: las 40 cartas están dibujadas para este sitio. --}}
        <p class="mt-4 max-w-[52ch] text-lg leading-relaxed">Le ganan a todas las demás, en este orden.</p>

        {{-- Las cuatro en un solo renglón, también en el celular: el orden se lee de izquierda a derecha. --}}
        <ol class="mt-8 grid grid-cols-4 gap-x-2.5 sm:gap-x-8">
            @foreach ($mandan as $i => [$palo, $numero, $apodo])
                <li>
                    <div class="max-w-44"><x-carta :palo="$palo" :numero="$numero" /></div>
                    <p class="mt-4 text-lg font-extrabold leading-tight max-sm:mt-2.5 max-sm:text-[0.8rem]">
                        <span class="max-sm:block max-sm:text-xl max-sm:font-black">{{ $i + 1 }}<span class="max-sm:hidden">.</span></span>
                        {{ $apodo }}
                    </p>
                </li>
            @endforeach
        </ol>

        <p class="mt-8">
            <a href="{{ route('como-se-juega') }}#cartas" class="enlace-nav font-bold">Ver el orden completo</a>
        </p>
    </section>

</x-layouts.base>
