@php
    /*
     | La mesa de a cuatro contra bots: vos y un bot de compañero contra dos bots. Le pide al servidor
     | lo mismo que la mesa del mano a mano, más las señas. No hay plazos, series ni torneo.
     */
    $pedidos = [
        'accion' => route('mesa.accion'),
        'repartir' => route('mesa.repartir'),
        'estado' => route('mesa.estado'),
        'bot' => route('mesa.bot'),
        'sena' => route('mesa.sena'),
        'token' => csrf_token(),
        'entrePersonas' => false,
        'rival' => null,
        'torneo' => null,
        'serie' => null,
        'presente' => null,
        'turno' => \App\Juego\Mesa::SEGUNDOS_DE_TURNO,
        'reparto' => \App\Juego\Mesa::SEGUNDOS_PARA_REPARTIR,
        'mesa' => route('mesa'),
        // Todas las señas: el gesto, qué quiere decir y cómo se dibuja cada una.
        'senas' => $senas,
    ];

    // Los lugares de la cruz con su asiento, en el orden en que se apilan: los de los costados quedan debajo de los del medio.
    $lugares = ['izquierda' => 3, 'derecha' => 1, 'companero' => 2, 'vos' => 0];
    $nivelEnPalabras = mb_strtolower($nivel->nombre());
    // La mesa de quien mira (la eligió en su Colección). Con una elegida, la del Ultra no oscurece el paño.
    $mesaPropia = \App\Juego\Premios::propia('mesa');
    // Y cómo anota: los dos tanteadores de su pantalla salen con su forma.
    $anotarPropio = \App\Juego\Premios::propia('anotar');
    // Y con su mazo: toda carta de la página, también las del rival, sale de sus piezas (App\View\MazoEnUso).
    \App\View\MazoEnUso::usar(\App\Juego\Premios::propia('mazo'));
@endphp

<x-layouts.base titulo="Mesa de a cuatro" superficie="pano" :encabezado="false" :pie="false">
    <x-mazo.plantillas />

    {{-- La mesa ocupa la pantalla completa y nunca hace scroll: ver .mesa-cuatro en app.css. --}}
    <div x-data="mesaDeCuatro(@js($vista), @js($pedidos))" @class(['mesa mesa-cuatro mesa-completa relative', 'mesa-de-'.$mesaPropia => $mesaPropia !== 'fabrica', 'mesa-ultra' => $mesaPropia === 'fabrica' && $nivel === \App\Juego\Nivel::UltraDificil])>
        <h1 class="sr-only">Mesa de a cuatro contra la máquina: tu compañero y los dos rivales, de nivel {{ $nivelEnPalabras }}</h1>

        {{-- Con el final de la partida a la vista, lo de atrás queda tapado: tampoco recibe el foco ni el lector de pantalla. --}}
        <header class="mesa-barra relative z-10" :inert="fin !== null">
            <div class="flex min-w-0 items-center gap-3 justify-self-start [grid-area:logo] lg:gap-5">
                <a href="{{ route('portada') }}" class="rounded text-lg no-underline max-sm:text-2xl max-sm:[&_.logo-nombre]:hidden lg:text-2xl" aria-label="Vale Cuatro, ir al inicio"><x-logo /></a>

                {{-- El nivel de los tres bots, una sola vez y junto al logo: ahí no agrega una fila. --}}
                <p class="flex items-center gap-1.5 whitespace-nowrap text-sm font-semibold leading-none">
                    <x-nivel-fosforos :nivel="$nivel" class="text-[0.8rem]" />
                    <span class="sr-only">Bots de nivel</span> {{ $nivel->corto() }}
                </p>
            </div>

            {{-- El tanteo es de las parejas, y se anota como en el club: "nosotros" y "ellos". --}}
            <section aria-label="Tanteador" class="grid grid-cols-2 gap-x-4 text-[clamp(0.8rem,4.1cqw,1.3rem)] [grid-area:tanteo] lg:gap-x-12">
                <x-tanteador :forma="$anotarPropio" nombre="Nosotros" :puntos="$vista['tanteo'][0]" modelo="puntos.vos" class="min-w-0" :hasta="$vista['puntosParaGanar']" />
                <x-tanteador :forma="$anotarPropio" nombre="Ellos" :puntos="$vista['tanteo'][1]" modelo="puntos.rival" class="min-w-0" :hasta="$vista['puntosParaGanar']" />
            </section>

            <x-mesa.ajustes />
        </header>

        {{-- Tocar la mesa apura lo que se esté mostrando. --}}
        <div class="mesa-campo" @click="apurar()" :inert="fin !== null">
            @if ($mesaPropia === 'grafito')
                <x-mesa.placa :apodo="auth()->user()->apodo" />
            @endif
            @if ($mesaPropia === 'fileteado')
                <x-mesa.leyenda />
            @endif
            {{--
                Tu compañero, enfrente. A su izquierda, lo que te dice con sus señas, dibujado, y el botón
                para hacerle las tuyas; a su derecha, el mazo, de donde sale el reparto.
            --}}
            <section aria-label="Tu compañero" class="relative grid grid-cols-[1fr_auto_1fr] items-center gap-3 sm:gap-7"
                @click.outside.capture="cerrarSenas(false)" @keydown.escape.window="cerrarSenas()">
                <div class="flex flex-col items-end gap-1.5 justify-self-end">
                    {{-- El dibujo es para la vista; lo mismo va dicho con palabras, y la mesa lo anuncia al repartir. --}}
                    <p class="sr-only" x-text="loQueDiceElCompanero"></p>
                    <div class="senas-dichas">
                        <template x-for="sena in senasDelCompanero" :key="sena">
                            <x-mesa.mini-naipe de="dibujo(sena)" />
                        </template>
                    </div>
                    <button type="button" x-ref="botonDeSenas" class="boton min-h-9 gap-1.5 px-2.5 py-1 text-sm" :class="senando ? 'boton-naipe' : 'boton-linea'"
                        @click="senando ? cerrarSenas() : abrirSenas()" aria-controls="senas-de-la-mesa" :aria-expanded="senando.toString()">
                        <x-icono nombre="sena" class="size-5" /> Señas
                    </button>
                </div>

                <x-mesa.otro asiento="2" lugar="companero" nombre="Compañero" />

                {{-- El mazo, contra el borde del campo para que no parezca una carta más. De acá sale el reparto. --}}
                <div x-ref="origen" class="mesa-mazo mr-1 justify-self-end sm:mr-5" aria-hidden="true">
                    <x-dorso />
                    <x-dorso />
                    <x-dorso />
                </div>

                {{--
                    Las señas, en una ventanita como la de los ajustes: no traba nada y se usa también mientras
                    juegan los bots. Cada seña dice el gesto, lo que quiere decir y, dibujada, la carta de la que habla.
                    Se cierra tocando afuera, con Esc o con el mismo botón.
                --}}
                <div id="senas-de-la-mesa" x-ref="senas" x-show="senando" x-cloak @click.stop
                    x-transition:enter="aparece" x-transition:enter-start="desde-chico" x-transition:leave="se-va" x-transition:leave-end="opacity-0"
                    class="senas-panel superficie-naipe" role="dialog" aria-label="Señas">
                    <h2 class="px-3.5 pb-1 pt-3 text-sm font-black">Tu compañero te dice</h2>
                    <ul>
                        <template x-for="sena in senasDelCompanero" :key="sena">
                            <li class="sena">
                                <x-mesa.mini-naipe de="dibujo(sena)" />
                                <span>
                                    <span class="block font-bold" x-text="gestoDe(sena)"></span>
                                    <span class="text-sm" x-text="significa(sena)"></span>
                                </span>
                            </li>
                        </template>
                    </ul>
                    <p class="px-3.5 pb-2 text-sm" x-show="! senasDelCompanero.length">Con la mano cerrada no hay señas.</p>

                    <h2 class="border-t-2 border-current px-3.5 pb-1 pt-3 text-sm font-black">Hacele una seña</h2>
                    <div x-show="puedeSenar">
                        <template x-for="propia in senasPropias" :key="propia.sena">
                            <button type="button" class="sena" :disabled="propia.hecha || enviandoSena" @click="senar(propia.sena)">
                                <x-mesa.mini-naipe de="dibujo(propia.sena)" />
                                <span class="min-w-0 flex-1">
                                    <span class="block font-bold" x-text="hacerDe(propia.sena)"></span>
                                    <span class="text-sm" x-text="propia.hecha ? 'Ya se la hiciste' : significa(propia.sena, true)"></span>
                                </span>
                                <span class="text-gana" x-show="propia.hecha"><x-icono nombre="quiero" class="size-5" /></span>
                            </button>
                        </template>
                    </div>
                    <p class="px-3.5 pb-3 text-sm" x-show="! puedeSenar">Ahora no hay a quién hacerle una seña.</p>
                </div>
            </section>

            {{--
                El centro: la baza que se está jugando, en cruz, con cada carta delante de quien la tiró,
                y un rival a cada lado. Se juega hacia la derecha: después de vos tira el de tu derecha.
            --}}
            <section aria-label="Cartas jugadas" class="relative flex flex-1 flex-col items-center justify-center py-2">
                <div class="mx-auto grid w-full grid-cols-[1fr_auto_1fr] items-center gap-1.5" style="max-width: calc(var(--b) * 6.4)">
                    <x-mesa.otro asiento="3" lugar="izquierda" nombre="Rival" lado="de la izquierda" acostada />

                    <div x-ref="cruz" class="cruz">
                        @foreach ($lugares as $lugar => $asiento)
                            <div data-hueco="{{ $lugar }}" class="hueco-baza cruz-{{ $lugar }}" :data-toca="leTocaTirar({{ $asiento }}).toString()"></div>
                        @endforeach

                        {{--
                            Los tantos del envido, cada uno dicho desde su lugar. El que gana queda a pleno y los
                            demás a media tinta. Lo mismo se anuncia en el aviso de abajo.
                        --}}
                        @foreach (array_keys($lugares) as $lugar)
                            <div class="cruz-tanto cruz-tanto-{{ $lugar }}" aria-hidden="true">
                                <p class="voz canto tanto-ficha" :data-visible="tantos.{{ $lugar }}.visible"
                                    :data-pierde="tantos.resuelto && tantos.gana !== '{{ $lugar }}'">
                                    <span class="block leading-[0.85]" x-show="tantos.{{ $lugar }}.numero !== ''" x-text="tantos.{{ $lugar }}.numero"></span>
                                    <span class="block whitespace-pre leading-none" :class="tantos.{{ $lugar }}.numero === '' ? 'text-[0.42em]' : 'text-[0.34em]'" x-text="tantos.{{ $lugar }}.frase"></span>
                                </p>
                            </div>
                        @endforeach
                    </div>

                    <x-mesa.otro asiento="1" lugar="derecha" nombre="Rival" lado="de la derecha" acostada />
                </div>

                <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center" aria-hidden="true">
                    {{-- Un canto largo puede venir partido en dos renglones (mesa.js, enRenglones): el salto de línea se respeta. --}}
                    <p class="voz canto whitespace-pre text-center" :data-visible="voz.visible" :data-quien="voz.quien" :style="{ fontSize: tamanoDeVoz }"
                        :class="{ 'canto-oro': voz.tono === 'oro', 'canto-ficha canto-copa': voz.tono === 'copa', 'canto-ficha canto-basto': voz.tono === 'basto' }"
                        x-text="voz.texto"></p>
                </div>
            </section>

            {{-- De las bazas que ya pasaron queda escrito de quién fue cada una. La que se juega va a pleno. --}}
            <ol class="flex justify-center gap-x-5 text-xs font-semibold sm:text-sm" aria-label="Bazas" :class="{ 'opacity-0': cierre }">
                @foreach ([0, 1, 2] as $numero)
                    <li :class="baza === {{ $numero }} && ! cerrada ? 'font-black' : 'opacity-70'">
                        {{ $numero + 1 }}ª<span x-show="resultadoDeBaza({{ $numero }})" x-cloak> <span x-text="resultadoDeBaza({{ $numero }})"></span></span>
                    </li>
                @endforeach
            </ol>

            {{--
                Como en el mano a mano: lo que pasa se ve donde pasa, el relato entero va solo al lector de pantalla
                y este renglón queda para lo que no tiene otro lugar. Mide siempre un renglón.
            --}}
            <p aria-live="polite" class="sr-only" x-text="aviso">Repartiendo.</p>
            <p class="mt-1 flex h-6 items-end justify-center text-center text-[0.95rem] font-semibold leading-snug sm:h-[1.55rem] sm:text-lg" aria-hidden="true">
                <span x-show="notaALaVista && ! cierre" x-cloak x-transition:enter="aparece" x-transition:enter-start="opacity-0"
                    x-transition:leave="se-va" x-transition:leave-end="opacity-0" x-text="notaALaVista"></span>
            </p>

            {{-- En el celular, la marca de mano y el estado del truco van arriba de las cartas; en pantallas anchas, a los costados. --}}
            <section aria-label="Tu mano" class="relative grid items-center gap-x-7 pb-4 pt-1 sm:grid-cols-[1fr_auto_1fr]">
                <div class="flex min-h-7 items-center justify-center gap-3 text-sm sm:contents">
                    {{-- La marca guarda su lugar aunque el mano sea otro: la mesa no se corre. --}}
                    <p class="inline-flex items-center gap-1.5 rounded-full border border-naipe/40 px-2.5 py-0.5 font-semibold sm:order-1 sm:justify-self-end"
                        :class="{ 'opacity-0': cierre || ! esMano }" :aria-hidden="(! esMano).toString()">
                        <x-icono nombre="mano" class="size-4" /> Sos mano
                    </p>
                    <p class="font-semibold sm:order-3 sm:justify-self-start sm:text-base" :class="{ 'opacity-0': cierre }" x-text="estadoDelTruco"></p>
                </div>
                <div x-ref="mano" class="abanico mesa-mano sm:order-2" :data-turno="teToca">
                    <div></div>
                    <div></div>
                    <div></div>
                </div>

                {{--
                    La ayuda de quien declaró que está aprendiendo: su tanto de envido ya sumado, mientras dura la
                    primera baza. Va sobre el borde de abajo de las cartas: no le agrega una fila a la mesa.
                --}}
                <p class="mesa-tanto" x-show="tantoPropio !== null && baza === 0 && ! cierre" x-cloak
                    x-transition:enter="aparece" x-transition:enter-start="opacity-0" x-transition:leave="se-va" x-transition:leave-end="opacity-0">
                    <span class="sr-only">Tu tanto de envido:</span> <span x-text="tantoPropio"></span> <span aria-hidden="true">de envido</span>
                </p>

                {{--
                    El cierre de la mano ocupa el lugar de tus cartas, que ya volvieron al mazo: qué pareja ganó,
                    por qué, cuánto sumó cada cosa y las cartas que alguien tuvo que mostrar. No agrega una fila.
                --}}
                <div x-show="cierre" x-cloak x-transition:enter="aparece" x-transition:enter-start="opacity-0"
                    class="absolute inset-0 z-10 flex flex-col items-center justify-center px-2 text-center" aria-hidden="true">
                    <p class="text-[clamp(1.5rem,7cqw,2.5rem)] font-black leading-none tracking-tight" x-text="cierre?.titulo"></p>
                    <p class="mt-2 text-sm font-semibold sm:text-base" x-text="cierre?.motivo"></p>
                    <ul class="mt-2 flex flex-wrap justify-center gap-x-6 gap-y-1">
                        <template x-for="(linea, numero) in cierre?.lineas ?? []" :key="numero">
                            <li class="flex items-baseline gap-2 text-sm font-semibold sm:text-base">
                                <span class="puntos-del-cierre text-[1.75rem] font-black leading-none tabular-nums text-oro" x-text="linea.puntos"></span>
                                <span x-text="linea.texto"></span>
                            </li>
                        </template>
                    </ul>
                    <template x-for="quien in muestran" :key="quien.asiento">
                        <p class="mt-2 flex items-center justify-center gap-1.5 text-sm font-semibold">
                            <span x-text="`${quien.nombre} muestra`"></span>
                            <template x-for="carta in quien.cartas" :key="carta">
                                <x-mesa.mini-naipe de="dibujoDeCarta(carta)" />
                            </template>
                        </p>
                    </template>
                </div>
            </section>
        </div>

        <x-mesa.cantos />

        {{--
            El final de la partida es el gesto fuerte de la mesa, y no es un festejo: el paño hondo ocupa todo, el
            resultado entra grande y los dos tanteadores se cuentan de corrido desde cero (mesa.js, contarElFinal).
            Lo que se cuenta es dibujo: el resultado va escrito para el lector de pantalla.
        --}}
        <div x-show="fin" x-cloak x-transition:enter="aparece" x-transition:enter-start="opacity-0"
            class="sobre-pano absolute inset-0 z-30 flex overflow-y-auto bg-pano-hondo px-6 py-8 text-naipe"
            role="dialog" aria-modal="true" aria-labelledby="titulo-fin" aria-describedby="tanteo-fin">
            <div x-show="fin" x-transition:enter="aparece" x-transition:enter-start="desde-chico" class="m-auto w-full max-w-md">
                {{-- El título recibe el foco para que el lector de pantalla lo anuncie; no es un control, así que no lleva el marco del foco. --}}
                <h2 id="titulo-fin" x-ref="fin" tabindex="-1" class="text-[clamp(2.75rem,14cqw,4.75rem)] font-black leading-[0.94] tracking-[-0.035em] outline-none"
                    x-text="fin === 'vos' ? 'Ganamos la partida' : 'Ganaron ellos'"></h2>
                <p id="tanteo-fin" class="sr-only">
                    Nosotros <span x-text="puntos.vos"></span>, ellos <span x-text="puntos.rival"></span>.
                    <span x-text="fraseDelNivel"></span>
                </p>

                <div class="mt-7 grid gap-5 text-[1.5rem] sm:text-[1.75rem]" aria-hidden="true">
                    <x-tanteador :forma="$anotarPropio" nombre="Nosotros" modelo="cuenta.vos" class="min-w-0" clase-nombre="text-base font-semibold" :hasta="$vista['puntosParaGanar']" />
                    <x-tanteador :forma="$anotarPropio" nombre="Ellos" modelo="cuenta.rival" class="min-w-0" clase-nombre="text-base font-semibold" :hasta="$vista['puntosParaGanar']" />
                </div>

                <x-mesa.experiencia />

                <div class="mt-8 flex flex-col gap-2.5 sm:flex-row">
                    <form method="POST" action="{{ route('revancha.pedir') }}" class="flex flex-col">
                        @csrf
                        {{-- La revancha es con los mismos bots y el mismo nivel, y el mano pasa al asiento que sigue. --}}
                        <input type="hidden" name="partida" value="{{ $vista['partida'] }}">
                        <button type="submit" class="boton boton-naipe"><x-icono nombre="repetir" /> Jugar la revancha</button>
                    </form>
                    <a href="{{ route('historial') }}" class="boton boton-linea">Ver el historial</a>
                </div>
            </div>
        </div>

        {{-- Salir: la partida queda guardada para seguirla después, o se abandona y se pierde. --}}
        <div x-show="saliendo" x-cloak x-transition:enter="aparece" x-transition:enter-start="opacity-0" x-transition:leave="se-va" x-transition:leave-end="opacity-0"
            class="absolute inset-0 z-30 flex items-center justify-center bg-pano-hondo/90 p-6"
            @keydown.escape.window="saliendo = false" role="dialog" aria-modal="true" aria-labelledby="titulo-salir">
            <div x-show="saliendo" x-transition:enter="aparece" x-transition:enter-start="desde-chico" x-transition:leave="se-va" x-transition:leave-end="opacity-0"
                class="superficie-naipe w-full max-w-sm rounded-xl p-7 text-center">
                <h2 id="titulo-salir" class="text-3xl font-black tracking-tight">¿Salir de la mesa?</h2>
                <p class="mt-2 leading-relaxed">La partida queda guardada: cuando vuelvas a jugar, sigue donde la dejaste. Si la abandonás, la pierden vos y tu compañero.</p>
                <div class="mt-6 flex flex-col gap-2.5">
                    <button type="button" class="boton boton-tinta" @click="saliendo = false">Seguir jugando</button>
                    <a href="{{ route('portada') }}" class="boton boton-linea">Salir y seguir después</a>
                    <form method="POST" action="{{ route('mesa.abandonar') }}" class="flex flex-col">
                        @csrf
                        {{-- Se abandona la partida que esta mesa muestra, y ninguna otra. --}}
                        <input type="hidden" name="partida" value="{{ $vista['partida'] }}">
                        <button type="submit" class="boton boton-linea">Abandonar la partida</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.base>
