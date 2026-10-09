@php
    $mandan = [
        ['espada', 1, 'El ancho de espada'],
        ['basto', 1, 'El ancho de basto'],
        ['espada', 7, 'El siete de espada'],
        ['oro', 7, 'El siete de oro'],
    ];
@endphp

<x-layouts.base superficie="pano">
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
                Jugá una mano contra el bot ahora mismo, sin registrarte.
                <span class="max-sm:hidden">O mandale un link a alguien y jueguen en vivo.</span>
            </p>
            {{-- Sin campos: apretar el botón alcanza. Quien no tiene sesión entra como invitado. --}}
            <form method="POST" action="{{ route('jugar') }}" class="mt-8 flex flex-wrap gap-3 max-sm:order-4 max-sm:mt-6">
                @csrf
                <button type="submit" class="boton boton-naipe min-h-14 px-6 text-lg max-sm:w-full">
                    <x-icono nombre="bot" /> Jugar contra el bot
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

    <section aria-labelledby="titulo-fosforos" class="sobre-pano bg-pano-hondo" x-data="{ vos: 13 }">
        <div class="mx-auto grid max-w-6xl gap-10 px-5 py-16 sm:px-8 lg:grid-cols-2 lg:items-center lg:py-20">
            <div>
                <h2 id="titulo-fosforos" class="text-4xl font-black leading-[1.02] tracking-tight sm:text-5xl">
                    Se anota con fósforos, como en el club.
                </h2>
                <p class="mt-5 max-w-[48ch] text-lg leading-relaxed">
                    Cuatro fósforos en cuadrado y uno cruzado son cinco puntos.
                    Quince malas, quince buenas, y gana quien llega a treinta.
                </p>
                <button type="button" class="boton boton-oro mt-7" :disabled="vos >= 30" @click="vos++">
                    Anotar un punto
                </button>
            </div>

            <div class="flex flex-col gap-7 text-[1.2rem] sm:text-[1.75rem]">
                <x-tanteador nombre="Vos" :puntos="13" modelo="vos" />
                <x-tanteador nombre="Rival" :puntos="9" />
            </div>
        </div>
    </section>

    <section aria-labelledby="titulo-mandan" class="mx-auto max-w-6xl px-5 py-16 sm:px-8 lg:py-20">
        <h2 id="titulo-mandan" class="max-w-[18ch] text-4xl font-black leading-[1.02] tracking-tight sm:text-5xl">
            Las cuatro que mandan.
        </h2>
        <p class="mt-5 max-w-[52ch] text-lg leading-relaxed">
            En el truco las cartas no valen por su número. Estas cuatro le ganan a todas las demás, en este orden.
            El mazo es propio: las 40 cartas están dibujadas para este sitio.
        </p>

        {{-- Las cuatro en un solo renglón, también en el celular: el orden se lee de izquierda a derecha. --}}
        <ol class="mt-10 grid grid-cols-4 gap-x-2.5 max-sm:mt-8 sm:gap-x-8">
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

        <p class="mt-10">
            <a href="{{ route('como-se-juega') }}" class="boton boton-linea">Ver el orden completo y las reglas</a>
        </p>
    </section>

    <section aria-labelledby="titulo-real" class="border-t border-naipe/20">
        <div class="mx-auto max-w-6xl px-5 py-16 sm:px-8 lg:py-20">
            <h2 id="titulo-real" class="text-4xl font-black leading-[1.02] tracking-tight sm:text-5xl">
                Qué es simulado y qué es real.
            </h2>
            <div class="mt-9 grid gap-9 md:grid-cols-2 md:gap-14">
                <div>
                    <h3 class="text-xl font-extrabold">Simulado</h3>
                    <p class="mt-2 max-w-[46ch] text-lg leading-relaxed">
                        Los rivales bot y los jugadores de ejemplo del ranking.
                        En todo el sitio aparecen marcados como bots.
                    </p>
                </div>
                <div class="md:border-l md:border-naipe/20 md:pl-14">
                    <h3 class="text-xl font-extrabold">Real</h3>
                    <p class="mt-2 max-w-[46ch] text-lg leading-relaxed">
                        El motor de reglas, las partidas en vivo entre dos personas,
                        el historial que se puede volver a ver mano por mano y el ranking.
                    </p>
                </div>
            </div>
        </div>
    </section>
</x-layouts.base>
