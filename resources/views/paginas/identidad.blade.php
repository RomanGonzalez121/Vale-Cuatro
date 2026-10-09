@php
    use App\Identidad\Paleta;
    use App\View\Components\Carta;

    $claros = ['naipe', 'oro', 'fosforo'];

    $iconos = [
        'espada' => 'Espada', 'basto' => 'Basto', 'oro' => 'Oro', 'copa' => 'Copa',
        'envido' => 'Envido', 'flor' => 'Flor', 'truco' => 'Truco', 'mazo' => 'Irse al mazo', 'mano' => 'Quién es mano',
        'repartir' => 'Repartir', 'quiero' => 'Quiero', 'no-quiero' => 'No quiero', 'tiempo' => 'Tiempo',
        'bot' => 'Bot', 'invitar' => 'Invitar', 'ranking' => 'Ranking', 'repetir' => 'Repetir partida', 'sonido' => 'Sonido', 'ritmo' => 'Ritmo', 'ajustes' => 'Ajustes', 'salir' => 'Salir', 'jugador' => 'Jugador',
        'mano-a-mano' => 'Mano a mano', 'de-a-cuatro' => 'De a cuatro', 'torneo' => 'Torneo', 'desafio' => 'Desafío', 'escalera' => 'Escalera',
    ];

    $pesos = [300 => 'Liviana', 400 => 'Normal', 600 => 'Seminegra', 800 => 'Extranegra', 900 => 'Negra'];
@endphp

<x-layouts.base titulo="Identidad" descripcion="Logo, colores, tipografías, mazo e íconos de Vale Cuatro, en uso.">
    <div class="mx-auto max-w-6xl px-5 pb-24 sm:px-8">
        <header class="max-w-[62ch] pt-10 pb-14 sm:pt-16">
            <h1 class="text-5xl font-black tracking-tight sm:text-6xl">Identidad</h1>
            <p class="mt-5 text-lg leading-relaxed">
                Todo sale del mundo del truco: la baraja española impresa en tintas planas, el paño de la mesa
                del club y el tanteo con fósforos. No es un casino. Esta página muestra cada pieza en uso y
                se actualiza sola, porque usa los mismos componentes que el resto del sitio.
            </p>
        </header>

        <section aria-labelledby="titulo-logo" class="border-t-2 border-texto py-12">
            <h2 id="titulo-logo" class="text-3xl font-extrabold tracking-tight">Logo</h2>
            <p class="mt-3 max-w-[62ch] leading-relaxed">
                El isotipo son cuatro fósforos en cuadrado: el grupo de cuatro del tanteador.
                Va en una sola tinta y se lee a 16 px. El logotipo es el nombre en Piazzolla Black Italic.
            </p>

            <div id="logos-de-muestra" class="mt-8 grid gap-4 md:grid-cols-2">
                <div class="superficie-pano flex min-h-44 items-center justify-center rounded-xl p-5 sm:min-h-56 sm:p-8">
                    <x-logo class="text-[2rem] sm:text-6xl" />
                </div>
                <div class="flex min-h-44 items-center justify-center rounded-xl border-2 border-texto p-5 sm:min-h-56 sm:p-8">
                    <x-logo class="text-[2rem] sm:text-6xl" />
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-3">
                <button type="button" class="boton boton-tinta" data-contar-cuatro="#logos-de-muestra .logo">
                    <x-icono nombre="repetir" /> Ver la animación del logo
                </button>
                <p class="max-w-[46ch] leading-snug">
                    El logo cuenta hasta cuatro: los fósforos caen de a uno, como un punto en el tanteador,
                    y cada cabeza se enciende al llegar. En el sitio se ve al pasar el mouse por el logo.
                </p>
            </div>

            <div class="mt-8 flex flex-wrap items-end gap-x-10 gap-y-6">
                @foreach ([16, 24, 48, 96] as $lado)
                    <figure class="flex flex-col items-start gap-2">
                        <x-isotipo style="width: {{ $lado }}px; height: {{ $lado }}px" />
                        <figcaption class="text-sm tabular-nums">{{ $lado }} px</figcaption>
                    </figure>
                @endforeach
                <figure class="flex flex-col items-start gap-2">
                    <img src="/favicon.svg" alt="Favicon de Vale Cuatro" width="32" height="32">
                    <figcaption class="text-sm">Favicon</figcaption>
                </figure>
            </div>
        </section>

        <section aria-labelledby="titulo-colores" class="border-t-2 border-texto py-12">
            <h2 id="titulo-colores" class="text-3xl font-extrabold tracking-tight">Colores</h2>
            <p class="mt-3 max-w-[62ch] leading-relaxed">
                Nueve tintas. Las cuatro de los palos tienen una función cada una y no se usan como decoración.
                Hay dos superficies y no dos temas: la mesa, en paño, y las páginas de lectura, en naipe.
            </p>

            <ul class="mt-8 overflow-hidden rounded-xl border-2 border-texto">
                @foreach ($colores as $token => $color)
                    <li class="grid gap-x-6 gap-y-1 px-5 py-4 sm:grid-cols-[13rem_1fr] sm:items-baseline {{ in_array($token, $claros) ? 'text-tinta' : 'text-naipe' }}"
                        style="background-color: {{ $color['hex'] }}">
                        <p class="text-xl font-extrabold">
                            {{ $color['nombre'] }}
                            <span class="ml-2 text-base font-normal tabular-nums">{{ $color['hex'] }}</span>
                        </p>
                        <p class="leading-snug">{{ $color['uso'] }}</p>
                    </li>
                @endforeach
            </ul>

            <h3 class="mt-12 text-xl font-extrabold">De día y de noche</h3>
            <p class="mt-2 max-w-[62ch] leading-relaxed">
                El sitio tiene modo claro y oscuro: el club de día y de noche. Se cambia con la carta del encabezado,
                que se da vuelta. De noche las páginas de lectura pasan a Tinta, la mesa baja un paso y Espada, Basto y Copa
                se aclaran para leerse como texto. Las cartas no cambian nunca.
            </p>
            <ul class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach (Paleta::DE_NOCHE as $tono)
                    <li class="rounded-xl border-2 border-texto p-4" style="background-color: #161A18; color: #FBFAF5">
                        <span class="block h-10 rounded-md" style="background-color: {{ $tono['hex'] }}"></span>
                        <span class="mt-3 block font-extrabold leading-tight">{{ $tono['nombre'] }}</span>
                        <span class="block text-sm tabular-nums">{{ $tono['hex'] }}</span>
                        <span class="mt-1 block text-sm leading-snug">{{ $tono['uso'] }}</span>
                    </li>
                @endforeach
            </ul>

            <h3 class="mt-12 text-xl font-extrabold">Contraste medido</h3>
            <p class="mt-2 max-w-[62ch] leading-relaxed">
                Cada combinación de texto y fondo que usa el sitio, medida con la fórmula de WCAG.
                El texto normal necesita 4,5 a 1; el texto grande y los gráficos, 3 a 1.
                Un test automático falla si alguna deja de cumplir.
            </p>

            <div class="mt-6 overflow-x-auto">
                <table class="tabla min-w-[36rem]">
                    <thead>
                        <tr>
                            <th scope="col">Muestra</th>
                            <th scope="col">Combinación</th>
                            <th scope="col">Dónde se usa</th>
                            <th scope="col" class="numero">Contraste</th>
                            <th scope="col" class="numero">Mínimo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ([...$combinaciones, ...Paleta::combinacionesDeNoche()] as $c)
                            <tr>
                                <td>
                                    <span class="inline-block rounded-md px-3 py-1.5 text-lg font-bold"
                                        style="color: {{ Paleta::hex($c['texto']) }}; background-color: {{ Paleta::hex($c['fondo']) }}; border: 1px solid currentColor">Truco</span>
                                </td>
                                <td>{{ Paleta::nombre($c['texto']) }} sobre {{ Paleta::nombre($c['fondo']) }}</td>
                                <td>{{ $c['donde'] }}</td>
                                <td class="numero font-bold">{{ number_format(Paleta::contraste($c['texto'], $c['fondo']), 1, ',') }} a 1</td>
                                <td class="numero">{{ number_format($c['minimo'], 1, ',') }} a 1</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="titulo-tipografia" class="border-t-2 border-texto py-12">
            <h2 id="titulo-tipografia" class="text-3xl font-extrabold tracking-tight">Tipografía</h2>
            <p class="mt-3 max-w-[62ch] leading-relaxed">
                Dos familias, las dos de fundidoras argentinas. Chivo, de Omnibus-Type, hace toda la interfaz.
                Piazzolla, de Huerta Tipográfica, es la voz de los jugadores: solo se usa para los cantos y el logotipo.
            </p>

            <div class="mt-10 grid gap-12 lg:grid-cols-2">
                <div>
                    <h3 class="text-xl font-extrabold">Chivo</h3>
                    <ul class="mt-4">
                        @foreach ($pesos as $peso => $nombre)
                            <li class="flex items-baseline justify-between gap-4 border-b border-texto/15 py-2.5">
                                <span class="text-2xl sm:text-3xl" style="font-weight: {{ $peso }}">Mano a mano</span>
                                <span class="text-sm tabular-nums">{{ $nombre }}, {{ $peso }}</span>
                            </li>
                        @endforeach
                        <li class="flex items-baseline justify-between gap-4 py-2.5">
                            <span class="text-2xl italic sm:text-3xl">Parda la primera</span>
                            <span class="text-sm">Itálica</span>
                        </li>
                    </ul>
                    <p class="mt-6 text-5xl font-black tabular-nums tracking-tight" aria-label="Los números de las cartas">1 2 3 4 5 6 7 10 11 12</p>
                    <p class="mt-2 text-sm">Los números de las cartas van en Chivo Negra.</p>
                </div>

                <div>
                    <h3 class="text-xl font-extrabold">Piazzolla Black Italic</h3>
                    <div class="superficie-pano mt-4 flex flex-col items-start gap-5 overflow-hidden rounded-xl p-6 sm:p-8">
                        <p class="canto canto-oro text-6xl sm:text-7xl">Envido</p>
                        <p class="canto canto-ficha canto-copa -rotate-2 text-6xl sm:text-7xl">Truco</p>
                        <p class="canto canto-ficha canto-basto rotate-1 text-5xl sm:text-6xl">Quiero</p>
                        <p class="canto canto-ficha canto-copa -rotate-1 text-4xl sm:text-6xl">Vale cuatro</p>
                    </div>
                    <p class="mt-3 max-w-[52ch] text-sm leading-relaxed">
                        Oro va como texto grande directo sobre el paño. Copa y Basto no alcanzan contraste como texto
                        sobre el paño, así que aparecen como fichas llenas con el texto en Naipe.
                    </p>
                </div>
            </div>
        </section>

        <section aria-labelledby="titulo-tanteador" class="border-t-2 border-texto py-12">
            <h2 id="titulo-tanteador" class="text-3xl font-extrabold tracking-tight">Tanteador de fósforos</h2>
            <p class="mt-3 max-w-[62ch] leading-relaxed">
                Los puntos se anotan como en el club: grupos de cinco, con cuatro fósforos en cuadrado y uno cruzado.
                Las quince malas a la izquierda de la raya y las quince buenas a la derecha.
            </p>

            <div class="mt-8 grid gap-x-10 gap-y-7 rounded-xl bg-pano-hondo p-6 text-[1.05rem] sm:grid-cols-2 sm:p-8 lg:grid-cols-3">
                @foreach ([3, 7, 15, 18, 26, 30] as $puntos)
                    <x-tanteador nombre="Ejemplo" :puntos="$puntos" />
                @endforeach
            </div>
        </section>

        <section aria-labelledby="titulo-mazo" class="border-t-2 border-texto py-12">
            <h2 id="titulo-mazo" class="text-3xl font-extrabold tracking-tight">Mazo</h2>
            <p class="mt-3 max-w-[62ch] leading-relaxed">
                Las 40 cartas, dibujadas para este proyecto. Cada palo tiene su tinta y su pinta:
                los cortes en el marco que lo identifican en la baraja española.
                El oro no tiene cortes, la copa tiene uno, la espada dos y el basto tres.
            </p>

            @foreach (Carta::PALOS as $palo)
                <h3 class="mt-9 text-xl font-extrabold capitalize">{{ $palo }}</h3>
                <ul class="mt-3 grid grid-cols-5 gap-2.5 sm:gap-3 lg:grid-cols-10">
                    @foreach ($mazo as [$paloDeLaCarta, $numero])
                        @if ($paloDeLaCarta === $palo)
                            <li><x-carta :palo="$palo" :numero="$numero" /></li>
                        @endif
                    @endforeach
                </ul>
            @endforeach

            <h3 class="mt-12 text-xl font-extrabold">Sobre el paño</h3>
            <p class="mt-2 max-w-[62ch] leading-relaxed">
                Apoyadas en la mesa, las cartas llevan una sombra corta y dura. Es la única sombra del sitio.
            </p>
            <div class="superficie-pano mt-5 flex flex-wrap items-center justify-center gap-5 rounded-xl p-8 sm:gap-8">
                <div class="w-24 sm:w-32"><x-carta palo="espada" :numero="1" /></div>
                <div class="w-24 sm:w-32"><x-carta palo="basto" :numero="1" /></div>
                <div class="w-24 sm:w-32"><x-carta palo="espada" :numero="7" /></div>
                <div class="w-24 sm:w-32"><x-carta palo="oro" :numero="7" /></div>
                <div class="w-24 sm:w-32"><x-dorso /></div>
            </div>
        </section>

        <section aria-labelledby="titulo-iconos" class="border-t-2 border-texto py-12">
            <h2 id="titulo-iconos" class="text-3xl font-extrabold tracking-tight">Íconos</h2>
            <p class="mt-3 max-w-[62ch] leading-relaxed">
                Dibujados sobre una grilla de 24 px con el trazo de los fósforos:
                línea recta de 2 px que termina en un punto lleno, la cabeza.
            </p>

            <ul class="mt-8 grid grid-cols-3 gap-x-4 gap-y-8 sm:grid-cols-4 lg:grid-cols-6">
                @foreach ($iconos as $icono => $nombre)
                    <li class="flex flex-col items-start gap-3">
                        <span class="flex items-end gap-3">
                            <x-icono :nombre="$icono" class="size-12" />
                            <x-icono :nombre="$icono" class="size-6" />
                        </span>
                        <span class="text-sm font-semibold">{{ $nombre }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="superficie-pano mt-10 flex flex-wrap gap-3 rounded-xl p-6">
                <span class="boton boton-oro"><x-icono nombre="envido" /> Envido</span>
                <span class="boton boton-copa"><x-icono nombre="truco" /> Truco</span>
                <span class="boton boton-basto"><x-icono nombre="quiero" /> Quiero</span>
                <span class="boton boton-copa"><x-icono nombre="no-quiero" /> No quiero</span>
                <span class="boton boton-linea"><x-icono nombre="mazo" /> Me voy al mazo</span>
            </div>
        </section>
    </div>
</x-layouts.base>
