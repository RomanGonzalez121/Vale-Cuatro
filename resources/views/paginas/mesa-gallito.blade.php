@php
    /*
     | La mesa del gallito contra la máquina: vos y dos máquinas, una a cada costado. En cada mano uno es el
     | gallo y juega solo contra los otros dos. Le pide al servidor lo mismo que la mesa del mano a mano.
     | No hay plazos, series, torneo ni señas.
     */
    $pedidos = [
        'accion' => route('mesa.accion'),
        'repartir' => route('mesa.repartir'),
        'estado' => route('mesa.estado'),
        'bot' => route('mesa.bot'),
        'token' => csrf_token(),
        'entrePersonas' => false,
        'rival' => null,
        'torneo' => null,
        'serie' => null,
        'practica' => null,
        'presente' => null,
        'turno' => \App\Juego\Mesa::SEGUNDOS_DE_TURNO,
        'reparto' => \App\Juego\Mesa::SEGUNDOS_PARA_REPARTIR,
        'mesa' => route('mesa'),
    ];

    // Los lugares de la T con su asiento: se juega hacia la derecha, después de vos tira la de tu derecha.
    $lugares = ['izquierda' => 2, 'derecha' => 1, 'vos' => 0];
    $nivelEnPalabras = mb_strtolower($nivel->nombre());
    // La mesa, la forma de anotar y el mazo de quien mira (los eligió en su Colección).
    $mesaPropia = \App\Juego\Premios::propia('mesa');
    $anotarPropio = \App\Juego\Premios::propia('anotar');
    \App\View\MazoEnUso::usar(\App\Juego\Premios::propia('mazo'));
    // Los tres tanteadores, por su lado: el tuyo primero.
    $tanteadores = ['vos' => ['Vos', 0], 'izquierda' => ['Izquierda', 2], 'derecha' => ['Derecha', 1]];
@endphp

<x-layouts.base titulo="Gallito" superficie="pano" :encabezado="false" :pie="false">
    <x-mazo.plantillas />

    {{-- La mesa ocupa la pantalla completa y nunca hace scroll: ver .mesa-gallito en app.css. --}}
    <div x-data="mesaDelGallito(@js($vista), @js($pedidos))" @class(['mesa mesa-cuatro mesa-gallito mesa-completa relative', 'mesa-de-'.$mesaPropia => $mesaPropia !== 'fabrica', 'mesa-ultra' => $mesaPropia === 'fabrica' && $nivel === \App\Juego\Nivel::UltraDificil])>
        <h1 class="sr-only">Gallito contra la máquina: vos y dos máquinas de nivel {{ $nivelEnPalabras }}, cada uno suma aparte</h1>

        <header class="mesa-barra relative z-10" :inert="fin !== null">
            <div class="flex min-w-0 items-center gap-3 justify-self-start [grid-area:logo] lg:gap-5">
                <a href="{{ route('portada') }}" class="rounded text-lg no-underline max-sm:text-2xl max-sm:[&_.logo-nombre]:hidden lg:text-2xl" aria-label="Vale Cuatro, ir al inicio"><x-logo /></a>

                <p class="flex items-center gap-1.5 whitespace-nowrap text-sm font-semibold leading-none">
                    <x-nivel-fosforos :nivel="$nivel" class="text-[0.8rem]" />
                    <span class="sr-only">Máquinas de nivel</span> {{ $nivel->corto() }}
                </p>
            </div>

            {{-- Cada uno suma aparte: tres tanteadores. En el celular, las malas arriba y las buenas abajo. --}}
            <section aria-label="Tanteador" class="tanteo-tres text-[clamp(0.8rem,4.1cqw,1.3rem)] [grid-area:tanteo] lg:text-[clamp(0.6rem,1.1cqw,0.95rem)]">
                @foreach ($tanteadores as $lado => [$nombre, $asiento])
                    <x-tanteador :forma="$anotarPropio" :nombre="$nombre" :puntos="$vista['tanteo'][$asiento]" modelo="puntos.{{ $lado }}" class="min-w-0" clase-nombre="text-[0.8rem] font-semibold" :hasta="$vista['puntosParaGanar']" partido />
                @endforeach
            </section>

            <x-mesa.ajustes :ambiente="null" />
        </header>

        {{-- Tocar la mesa apura lo que se esté mostrando. --}}
        <div class="mesa-campo" @click="apurar()" :inert="fin !== null">
            @if ($mesaPropia === 'grafito')
                <x-mesa.placa :apodo="auth()->user()->apodo" />
            @endif
            @if ($mesaPropia === 'fileteado')
                <x-mesa.leyenda />
            @endif
            @if ($mesaPropia === 'cajon')
                <x-mesa.etiqueta />
            @endif

            {{-- El mazo, en la esquina de arriba contra el borde del campo. De acá sale el reparto. --}}
            <div x-ref="origen" class="mesa-mazo" aria-hidden="true">
                <x-dorso />
                <x-dorso />
                <x-dorso />
            </div>

            {{--
                El centro: la baza que se está jugando, en T, con cada carta delante de quien la tiró, y una
                máquina a cada lado. Cada una se nombra por lo que es para vos en esta mano.
            --}}
            <section aria-label="Cartas jugadas" class="relative flex flex-1 flex-col items-center justify-center py-2">
                <div class="mx-auto grid w-full grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-1" style="max-width: calc(var(--b) * 6.6)">
                    @foreach (['izquierda', null, 'derecha'] as $lugar)
                        @if ($lugar === null)
                            <div x-ref="cruz" class="cruz-t">
                                @foreach ($lugares as $hueco => $asiento)
                                    <div data-hueco="{{ $hueco }}" class="hueco-baza cruz-{{ $hueco }}" :data-toca="leTocaTirar({{ $asiento }}).toString()"></div>
                                @endforeach

                                {{-- Los tantos del envido, cada uno dicho desde su lugar. --}}
                                @foreach (array_keys($lugares) as $hueco)
                                    <div class="cruz-tanto cruz-tanto-{{ $hueco }}" aria-hidden="true">
                                        <p class="voz canto tanto-ficha" :data-visible="tantos.{{ $hueco }}.visible"
                                            :data-pierde="tantos.resuelto && tantos.gana !== '{{ $hueco }}'">
                                            <span class="block leading-[0.85]" x-show="tantos.{{ $hueco }}.numero !== ''" x-text="tantos.{{ $hueco }}.numero"></span>
                                            <span class="block whitespace-pre leading-none" :class="tantos.{{ $hueco }}.numero === '' ? 'text-[0.42em]' : 'text-[0.34em]'" x-text="tantos.{{ $hueco }}.frase"></span>
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            @php($asiento = $lugares[$lugar])
                            <div class="flex flex-col items-center gap-1">
                                <div class="relative">
                                    <div data-dorsos="{{ $lugar }}" class="mano-de-otro mano-acostada" aria-hidden="true"></div>
                                    <x-mesa.dicho :lugar="$lugar" />
                                </div>
                                {{-- El ícono pasa a ser un reloj mientras piensa. El nombre, lo que es en la mano: Gallo, Con vos o Pareja. --}}
                                <p class="otro-nombre" :class="{ 'otro-gallo': gallo === {{ $asiento }} }" :data-habla="(voz.visible && habla === {{ $asiento }}).toString()">
                                    <span class="relative size-4" aria-hidden="true">
                                        <x-icono nombre="bot" class="absolute inset-0 size-4 transition-opacity ease-llegada" ::class="piensa({{ $asiento }}) ? 'opacity-0 duration-100' : 'opacity-100 duration-200'" />
                                        <x-icono nombre="tiempo" class="absolute inset-0 size-4 opacity-0 transition-opacity ease-llegada" ::class="piensa({{ $asiento }}) ? 'opacity-100 duration-200' : 'opacity-0 duration-100'" />
                                    </span>
                                    <span x-text="seFue({{ $asiento }}) ? 'Al mazo' : rolDe({{ $asiento }})"></span>
                                    <span class="sr-only">, la máquina de la {{ $lugar }}</span>
                                </p>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center" aria-hidden="true">
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

            <p aria-live="polite" class="sr-only" x-text="aviso">Repartiendo.</p>
            <p class="mt-1 flex h-6 items-end justify-center text-center text-[0.95rem] font-semibold leading-snug sm:h-[1.55rem] sm:text-lg" aria-hidden="true">
                <span x-show="notaALaVista && ! cierre" x-cloak x-transition:enter="aparece" x-transition:enter-start="opacity-0"
                    x-transition:leave="se-va" x-transition:leave-end="opacity-0" x-text="notaALaVista"></span>
            </p>

            {{-- En el celular, tu lugar en la mano y el estado del truco van arriba de las cartas; en pantallas anchas, a los costados. --}}
            <section aria-label="Tu mano" class="relative grid items-center gap-x-7 pb-4 pt-1 sm:grid-cols-[1fr_auto_1fr]">
                <div class="flex min-h-7 items-center justify-center gap-3 text-sm sm:contents">
                    <p class="inline-flex items-center gap-1.5 rounded-full border border-naipe/40 px-2.5 py-0.5 font-semibold sm:order-1 sm:justify-self-end"
                        :class="{ 'opacity-0': cierre }">
                        <span x-show="soyElGallo" class="inline-flex"><x-icono nombre="mano" class="size-4" /></span>
                        <span x-text="tuLugar"></span>
                    </p>
                    <p class="font-semibold sm:order-3 sm:justify-self-start sm:text-base" :class="{ 'opacity-0': cierre }" x-text="estadoDelTruco"></p>
                </div>
                <div x-ref="mano" class="abanico mesa-mano sm:order-2" :data-turno="teToca">
                    <div></div>
                    <div></div>
                    <div></div>
                </div>

                <p class="mesa-tanto" x-show="tantoPropio !== null && baza === 0 && ! cierre" x-cloak
                    x-transition:enter="aparece" x-transition:enter-start="opacity-0" x-transition:leave="se-va" x-transition:leave-end="opacity-0">
                    <span class="sr-only">Tu tanto de envido:</span> <span x-text="tantoPropio"></span> <span aria-hidden="true">de envido</span>
                </p>

                {{-- El cierre de la mano ocupa el lugar de tus cartas: quién ganó, por qué y cuánto sumó cada uno. --}}
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

        {{-- El final de la partida: el paño hondo ocupa todo, el resultado entra grande y los tres tanteadores se cuentan de corrido. --}}
        <div x-show="fin" x-cloak x-transition:enter="aparece" x-transition:enter-start="opacity-0"
            class="sobre-pano absolute inset-0 z-30 flex overflow-y-auto bg-pano-hondo px-6 py-8 text-naipe"
            role="dialog" aria-modal="true" aria-labelledby="titulo-fin" aria-describedby="tanteo-fin">
            <div x-show="fin" x-transition:enter="aparece" x-transition:enter-start="desde-chico" class="m-auto w-full max-w-md">
                <h2 id="titulo-fin" x-ref="fin" tabindex="-1" class="text-[clamp(2.75rem,14cqw,4.75rem)] font-black leading-[0.94] tracking-[-0.035em] outline-none"
                    x-text="ganadorDelFinal"></h2>
                <p id="tanteo-fin" class="sr-only">
                    Vos <span x-text="puntos.vos"></span>, la de la izquierda <span x-text="puntos.izquierda"></span>, la de la derecha <span x-text="puntos.derecha"></span>.
                    <span x-text="fraseDelNivel"></span>
                </p>

                <div class="mt-7 grid gap-5 text-[1.5rem] sm:text-[1.75rem]" aria-hidden="true">
                    @foreach ($tanteadores as $lado => [$nombre])
                        <x-tanteador :forma="$anotarPropio" :nombre="$nombre" modelo="cuenta.{{ $lado }}" class="min-w-0" clase-nombre="text-base font-semibold" :hasta="$vista['puntosParaGanar']" />
                    @endforeach
                </div>

                <x-mesa.experiencia />

                <div class="mt-8 flex flex-col gap-2.5 sm:flex-row">
                    <form method="POST" action="{{ route('revancha.pedir') }}" class="flex flex-col">
                        @csrf
                        {{-- La revancha es con las mismas máquinas y el mismo nivel, y el primer gallo pasa al asiento que sigue. --}}
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
                <p class="mt-2 leading-relaxed">La partida queda guardada: cuando vuelvas a jugar, sigue donde la dejaste. Si la abandonás, la perdés.</p>
                <div class="mt-6 flex flex-col gap-2.5">
                    <button type="button" class="boton boton-tinta" @click="saliendo = false">Seguir jugando</button>
                    <a href="{{ route('portada') }}" class="boton boton-linea">Salir y seguir después</a>
                    <form method="POST" action="{{ route('mesa.abandonar') }}" class="flex flex-col">
                        @csrf
                        <input type="hidden" name="partida" value="{{ $vista['partida'] }}">
                        <button type="submit" class="boton boton-linea">Abandonar la partida</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.base>
