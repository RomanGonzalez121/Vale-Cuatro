@php
    /*
     | Todos los botones que puede llegar a tener la barra de cantos. La mesa
     | muestra solo los que el motor declara válidos en cada momento, y en el
     | orden que corresponde: [clave, texto, texto corto, clase, ícono].
     */
    /*
     | Lo que la mesa le pide al servidor y lo que necesita saber de la partida. Con otra persona
     | enfrente suma su apodo, el pedido que resuelve un plazo vencido y lo que duran el turno y
     | la espera del reparto, para dibujar la cuenta regresiva.
     */
    $pedidos = [
        'accion' => route('mesa.accion'),
        'repartir' => route('mesa.repartir'),
        'estado' => route('mesa.estado'),
        'bot' => route('mesa.bot'),
        'plazo' => route('mesa.plazo'),
        // Solo para quien abrió la sala y todavía no avisó que tiene la mesa a la vista.
        'presente' => ($faltaLlegar ?? false) ? route('mesa.presente') : null,
        'token' => csrf_token(),
        'entrePersonas' => $rival !== null,
        'rival' => $rival,
        'turno' => \App\Juego\Mesa::SEGUNDOS_DE_TURNO,
        'reparto' => \App\Juego\Mesa::SEGUNDOS_PARA_REPARTIR,
    ];

    $botones = [
        ['quiero', 'Quiero', null, 'boton-basto', 'quiero'],
        ['no_quiero', 'No quiero', null, 'boton-copa', 'no-quiero'],
        ['truco', 'Truco', null, 'boton-copa', 'truco'],
        ['retruco', 'Retruco', null, 'boton-copa', 'truco'],
        ['vale_cuatro', 'Vale cuatro', null, 'boton-copa', 'truco'],
        ['grupo-envido', 'Envido', null, 'boton-oro', 'envido'],
        ['grupo-subir', 'Subir', null, 'boton-oro', 'envido'],
        ['envido', 'Envido', null, 'boton-oro', 'envido'],
        ['real_envido', 'Real envido', 'Real', 'boton-oro', 'envido'],
        ['falta_envido', 'Falta envido', 'Falta', 'boton-oro', 'envido'],
        ['flor', 'Flor', null, 'boton-oro', 'flor'],
        ['contraflor', 'Contraflor', null, 'boton-oro', 'flor'],
        ['contraflor_al_resto', 'Contraflor al resto', 'Al resto', 'boton-oro', 'flor'],
        ['mazo', 'Al mazo', null, 'boton-linea', 'mazo'],
        ['mas', 'Más', null, 'boton-linea', null],
        ['volver', 'Volver', null, 'boton-linea', null],
    ];
@endphp

<x-layouts.base titulo="Mesa" superficie="pano" :encabezado="false" :pie="false">
    <x-mazo.plantillas />

    {{-- La mesa ocupa la pantalla completa y nunca hace scroll: ver .mesa en app.css. --}}
    <div x-data="mesa(@js($vista), @js($pedidos))" @class(['mesa mesa-completa relative', 'mesa-ultra' => $nivel === \App\Juego\Nivel::UltraDificil])>
        <h1 class="sr-only">{{ $nivel ? "Mesa contra el bot, nivel {$nivel->nombre()}" : "Mesa contra {$rival}" }}</h1>

        {{-- Con el final de la partida a la vista, lo de atrás queda tapado: tampoco recibe el foco ni el lector de pantalla. --}}
        <header class="mesa-barra relative z-10" :inert="fin !== null">
            <a href="{{ route('portada') }}" class="justify-self-start rounded text-lg no-underline [grid-area:logo] lg:text-2xl" aria-label="Vale Cuatro, ir al inicio"><x-logo /></a>

            <section aria-label="Tanteador" class="grid grid-cols-2 gap-x-4 text-[clamp(0.8rem,4.1cqw,1.3rem)] [grid-area:tanteo] lg:gap-x-12">
                {{-- El asiento propio va siempre primero: quien se sentó por invitación es el 1. --}}
                <x-tanteador nombre="Vos" :puntos="$vista['tanteo'][$vista['asiento']]" modelo="puntos.vos" class="min-w-0" />
                {{-- Un apodo puede tener 20 letras: si no entra en su mitad se corta, y los puntos quedan a la vista. --}}
                <x-tanteador :nombre="$rival ?? 'Bot'" :puntos="$vista['tanteo'][1 - $vista['asiento']]" modelo="puntos.rival" class="min-w-0"
                    clase-nombre="min-w-0 truncate text-sm font-semibold" />
            </section>

            <div class="flex items-center gap-2 justify-self-end [grid-area:acciones]">
                <x-modo />
                {{--
                    El ritmo de la mesa: tranquilo o ágil. No cambia el juego, cambia cuánto se detiene la mesa para
                    que se lea cada jugada. Lleno es ágil. La elección queda en el navegador, como el modo de día y
                    de noche, y al cambiarla la mesa lo dice en el renglón de avisos.
                --}}
                <button type="button" class="boton size-10 min-h-0 p-0" :class="agil ? 'boton-naipe' : 'boton-linea'"
                    :aria-pressed="agil.toString()" aria-label="Ritmo ágil" title="Ritmo ágil" @click="cambiarRitmo()">
                    <x-icono nombre="ritmo" class="size-5" />
                </button>
                <button type="button" class="boton boton-linea min-h-10 gap-1.5 px-3 py-1.5 text-sm" @click="saliendo = true" aria-label="Salir de la mesa">
                    <x-icono nombre="salir" class="size-5" /> Salir
                </button>
            </div>
        </header>

        {{-- Tocar la mesa apura lo que se esté mostrando (los tantos del envido). --}}
        <div class="mesa-campo" @click="apurar()" :inert="fin !== null">
            {{--
                La cuenta regresiva entre personas: un fósforo acostado en el borde del campo, arriba cuando
                le toca al rival y abajo cuando te toca a vos. No ocupa lugar: la mesa mide lo mismo que sin él.
                Lo que cuenta también se dice en el aviso, así que acá es solo dibujo.
            --}}
            @if ($rival !== null)
                @foreach (['plazoRival' => 'rival', 'plazoPropio' => 'vos'] as $referencia => $lado)
                    <div x-ref="{{ $referencia }}" class="plazo plazo-{{ $lado }}" :data-arde="plazo === '{{ $lado }}'" aria-hidden="true">
                        <span class="plazo-palito"></span>
                        <span class="plazo-carril"><span class="plazo-cabeza"></span></span>
                    </div>
                @endforeach
            @endif

            <section aria-label="Rival" class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 sm:gap-7">
                <p class="flex min-w-0 flex-col items-end gap-1 justify-self-end text-right text-sm font-semibold leading-tight [overflow-wrap:anywhere] sm:flex-row sm:items-center sm:gap-2 sm:text-base">
                    <span class="flex items-center gap-1.5">
                        {{-- Mientras el rival piensa, el reloj aparece junto a su ícono. Guarda su lugar, así nada se corre, y no gira ni late. --}}
                        <span class="transition-opacity ease-llegada" :class="piensaElRival ? 'opacity-100 duration-200' : 'opacity-0 duration-100'" aria-hidden="true">
                            <x-icono nombre="tiempo" class="size-4" />
                        </span>
                        <x-icono :nombre="$nivel ? 'bot' : 'jugador'" class="size-5" />
                        {{-- El nivel, con los mismos fósforos que se eligieron en los modos. Una persona no tiene nivel. --}}
                        @if ($nivel)
                            <x-nivel-fosforos :nivel="$nivel" class="text-[0.8rem]" />
                        @endif
                    </span>
                    {{-- Un apodo largo ocupa dos renglones como mucho: la fila del rival mide siempre lo mismo. --}}
                    <span class="line-clamp-2">{{ $nivel ? 'Bot '.mb_strtolower($nivel->nombre()) : $rival }}</span>
                </p>
                <div x-ref="rival" class="mesa-rival flex justify-center gap-1.5 sm:gap-2"></div>
                {{-- El mazo, contra el borde del campo para que no parezca una carta más del rival. De acá sale el reparto. --}}
                <div x-ref="origen" class="mesa-mazo mr-1 justify-self-end sm:mr-5" aria-hidden="true">
                    <x-dorso />
                    <x-dorso />
                    <x-dorso />
                </div>
            </section>

            <section aria-label="Cartas jugadas" class="relative flex flex-1 flex-col justify-center py-2">
                <ol x-ref="bazas" class="mx-auto grid w-full grid-cols-3 gap-3" style="max-width: calc(var(--b) * 7.5)">
                    @foreach ([0, 1, 2] as $numero)
                        <li class="flex flex-col items-center" :class="{ 'baza-actual': baza === {{ $numero }} && ! cerrada }">
                            <p class="mb-2 text-xs font-semibold sm:text-sm">
                                {{ $numero + 1 }}ª baza<span x-show="resultadoDeBaza({{ $numero }})" x-cloak>, <span x-text="resultadoDeBaza({{ $numero }})"></span></span>
                            </p>
                            {{-- Un solo lugar marcado por baza; las dos cartas caen adentro, pisándose en diagonal. --}}
                            <div class="baza-lugar">
                                <div data-hueco="rival" class="hueco-baza hueco-rival"></div>
                                <div data-hueco="vos" class="hueco-baza hueco-propio"></div>
                            </div>
                        </li>
                    @endforeach
                </ol>

                <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center" aria-hidden="true">
                    {{-- Un canto largo puede venir partido en dos renglones (mesa.js, enRenglones): el salto de línea se respeta. --}}
                    <p class="voz canto whitespace-pre text-center" :data-visible="voz.visible" :data-quien="voz.quien" :style="{ fontSize: tamanoDeVoz }"
                        :class="{ 'canto-oro': voz.tono === 'oro', 'canto-ficha canto-copa': voz.tono === 'copa', 'canto-ficha canto-basto': voz.tono === 'basto' }"
                        x-text="voz.texto"></p>
                </div>

                {{--
                    Los tantos del envido, cantados como en la mesa: arriba el rival y abajo vos.
                    El que pierde queda a media tinta. Lo mismo se anuncia en el aviso de abajo.
                --}}
                <div class="pointer-events-none absolute inset-0 z-10 flex flex-col items-center justify-center gap-[0.3em] pt-7" style="font-size: min(5.5rem, 9dvh, 23cqw)" aria-hidden="true">
                    @foreach (['rival', 'vos'] as $quien)
                        <p class="voz canto canto-oro text-center" data-quien="{{ $quien }}" :data-visible="tantos.{{ $quien }}.visible"
                            :data-pierde="tantos.resuelto && tantos.gana !== '{{ $quien }}'">
                            <span class="block leading-[0.85]" x-show="tantos.{{ $quien }}.numero !== ''" x-text="tantos.{{ $quien }}.numero"></span>
                            <span class="block leading-none" :class="tantos.{{ $quien }}.numero === '' ? 'text-[0.5em]' : 'text-[0.34em]'" x-text="tantos.{{ $quien }}.frase"></span>
                        </p>
                    @endforeach
                </div>
            </section>

            {{--
                Al cerrar la mano el aviso sigue anunciándose, pero lo que se ve es el cierre de abajo.
                Mide siempre un renglón: si el texto ocupa dos (un apodo largo), el de más crece hacia
                arriba, sobre el paño libre, y las bazas no se mueven.
            --}}
            <p aria-live="polite" class="flex h-6 items-end justify-center text-center text-[0.95rem] font-semibold leading-snug sm:h-[1.55rem] sm:text-lg" :class="{ 'opacity-0': cierre }">
                <span x-text="aviso">Repartiendo.</span>
            </p>

            {{-- En el celular, la marca de mano y el estado del truco van arriba de las cartas; en pantallas anchas, a los costados. --}}
            <section aria-label="Tu mano" class="relative grid items-center gap-x-7 pb-4 pt-1 sm:grid-cols-[1fr_auto_1fr]">
                <div class="flex min-h-7 items-center justify-center gap-3 text-sm sm:contents">
                    {{-- La marca guarda su lugar aunque el mano sea el rival: la mesa no se corre. --}}
                    <p class="inline-flex items-center gap-1.5 rounded-full border border-naipe/40 px-2.5 py-0.5 font-semibold sm:order-1 sm:justify-self-end"
                        :class="{ 'opacity-0': cierre || ! esMano }" :aria-hidden="(! esMano).toString()">
                        <x-icono nombre="mano" class="size-4" /> Sos mano
                    </p>
                    <p class="font-semibold sm:order-3 sm:justify-self-start sm:text-base" :class="{ 'opacity-0': cierre }" x-text="estadoDelTruco"></p>
                </div>
                <div x-ref="mano" class="abanico mesa-mano sm:order-2">
                    <div></div>
                    <div></div>
                    <div></div>
                </div>

                {{--
                    El cierre de la mano ocupa el lugar de tus cartas, que ya volvieron al mazo:
                    quién ganó, por qué y cuánto sumó cada cosa. No agrega una fila a la mesa.
                --}}
                <div x-show="cierre" x-cloak x-transition:enter="aparece" x-transition:enter-start="opacity-0"
                    class="absolute inset-0 z-10 flex flex-col items-center justify-center px-2 text-center" aria-hidden="true">
                    <p class="text-[clamp(1.5rem,7cqw,2.5rem)] font-black leading-none tracking-tight" x-text="cierre?.titulo"></p>
                    <p class="mt-2 text-sm font-semibold sm:text-base" x-text="cierre?.motivo"></p>
                    <ul class="mt-3 flex flex-wrap justify-center gap-x-6 gap-y-1.5">
                        <template x-for="(linea, numero) in cierre?.lineas ?? []" :key="numero">
                            <li class="flex items-baseline gap-2 text-sm font-semibold sm:text-base">
                                <span class="text-[1.75rem] font-black leading-none tabular-nums text-oro" x-text="linea.puntos"></span>
                                <span x-text="linea.texto"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </section>
        </div>

        <section aria-label="Cantos disponibles" :inert="fin !== null" class="relative z-10 bg-pano-hondo px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3">
            {{--
                Solo se muestran los cantos que el motor declara válidos en este momento, en una fila.
                Los botones están todos acá; la mesa decide cuáles se ven y en qué orden. Las dos cosas
                van en un solo :style, porque x-show y :style en el mismo botón se pisan.
            --}}
            <div x-ref="barra" class="mesa-acciones mx-auto flex max-w-2xl gap-2" :data-muchos="barra.length > 3">
                @foreach ($botones as [$clave, $texto, $corto, $clase, $icono])
                    <button type="button" class="boton {{ $clase }}" x-cloak data-boton="{{ $clave }}" :style="estiloDe('{{ $clave }}')"
                        :disabled="ocupada" @click="tocar('{{ $clave }}')" @if ($corto) aria-label="{{ $texto }}" @endif>
                        @if ($icono)
                            <x-icono :nombre="$icono" />
                        @endif
                        @if ($corto)
                            {{-- En el celular va el nombre corto, que es como se dice en la mesa. --}}
                            <span class="sm:hidden">{{ $corto }}</span><span class="hidden sm:inline">{{ $texto }}</span>
                        @else
                            {{ $texto }}
                        @endif
                    </button>
                @endforeach

                {{-- Con la mano cerrada no queda nada por cantar: lo único que se puede hacer es repartir. --}}
                <button type="button" x-ref="repartir" class="boton boton-naipe" x-cloak data-boton="repartir" :style="estiloDe('repartir')"
                    :disabled="ocupada" @click="tocar('repartir')">
                    <x-icono nombre="repartir" /> Repartir
                </button>
            </div>
        </section>

        {{--
            El final de la partida es el gesto fuerte de la mesa, y no es un festejo: el paño hondo ocupa todo, el
            resultado entra grande y los dos tanteadores se cuentan de corrido desde cero (mesa.js, contarElFinal).
            El que perdió se queda en su tanteo y el que ganó completa el último grupo. Los botones se usan desde
            que aparecen. Lo que se cuenta es dibujo: el resultado va escrito para el lector de pantalla.
        --}}
        {{-- El contenido se centra con márgenes y no con la alineación del contenedor: así, en una ventana baja, lo que no entra se alcanza bajando. --}}
        <div x-show="fin" x-cloak x-transition:enter="aparece" x-transition:enter-start="opacity-0"
            class="sobre-pano absolute inset-0 z-30 flex overflow-y-auto bg-pano-hondo px-6 py-8 text-naipe"
            role="dialog" aria-modal="true" aria-labelledby="titulo-fin" aria-describedby="tanteo-fin">
            <div x-show="fin" x-transition:enter="aparece" x-transition:enter-start="desde-chico" class="m-auto w-full max-w-md">
                {{-- El título recibe el foco para que el lector de pantalla lo anuncie; no es un control, así que no lleva el marco del foco. --}}
                <h2 id="titulo-fin" x-ref="fin" tabindex="-1" class="text-[clamp(2.75rem,14cqw,4.75rem)] font-black leading-[0.94] tracking-[-0.035em] outline-none [overflow-wrap:anywhere]"
                    x-text="fin === 'vos' ? 'Ganaste la partida' : @js($nivel ? 'Ganó el bot' : "Ganó {$rival}")"></h2>
                <p id="tanteo-fin" class="sr-only">
                    Vos <span x-text="puntos.vos"></span>, {{ $rival ?? 'el bot' }} <span x-text="puntos.rival"></span>.
                </p>

                <div class="mt-7 grid gap-5 text-[1.5rem] sm:text-[1.75rem]" aria-hidden="true">
                    <x-tanteador nombre="Vos" modelo="cuenta.vos" class="min-w-0" clase-nombre="text-base font-semibold" />
                    <x-tanteador :nombre="$rival ?? 'Bot'" modelo="cuenta.rival" class="min-w-0" clase-nombre="min-w-0 truncate text-base font-semibold" />
                </div>

                <div class="mt-8 flex flex-col gap-2.5 sm:flex-row">
                    @if ($nivel)
                        <form method="POST" action="{{ route('jugar') }}" class="flex flex-col">
                            @csrf
                            {{-- La partida siguiente es contra el mismo nivel. --}}
                            <input type="hidden" name="nivel" value="{{ $nivel->value }}">
                            <button type="submit" class="boton boton-naipe">Jugar otra partida</button>
                        </form>
                    @else
                        {{-- Con otra persona, la revancha es de M12: por ahora se vuelve a elegir cómo jugar. --}}
                        <a href="{{ route('modos') }}" class="boton boton-naipe">Elegir cómo jugar</a>
                    @endif
                    <a href="{{ route('historial') }}" class="boton boton-linea">Ver el historial</a>
                </div>
            </div>
        </div>

        {{--
            Salir: contra el bot la partida queda guardada para seguirla después, o se abandona y se pierde.
            Con otra persona no espera: el turno sigue venciendo, y el cartel lo dice antes de salir.
            El fondo entra con un fundido y el cartel apenas más chico; los dos se van juntos y más rápido.
        --}}
        <div x-show="saliendo" x-cloak x-transition:enter="aparece" x-transition:enter-start="opacity-0" x-transition:leave="se-va" x-transition:leave-end="opacity-0"
            class="absolute inset-0 z-30 flex items-center justify-center bg-pano-hondo/90 p-6"
            @keydown.escape.window="saliendo = false" role="dialog" aria-modal="true" aria-labelledby="titulo-salir">
            <div x-show="saliendo" x-transition:enter="aparece" x-transition:enter-start="desde-chico" x-transition:leave="se-va" x-transition:leave-end="opacity-0"
                class="superficie-naipe w-full max-w-sm rounded-xl p-7 text-center">
                <h2 id="titulo-salir" class="text-3xl font-black tracking-tight">¿Salir de la mesa?</h2>
                @if ($rival === null)
                    <p class="mt-2 leading-relaxed">La partida queda guardada: cuando vuelvas a jugar, sigue donde la dejaste. Si la abandonás, la perdés.</p>
                @else
                    <p class="mt-2 leading-relaxed">
                        Con otra persona la partida no se detiene. Si salís, cada turno tuyo se vence a los {{ \App\Juego\Mesa::SEGUNDOS_DE_TURNO }} segundos,
                        y con {{ \App\Juego\Mesa::VENCIMIENTOS_PARA_PERDER }} vencidos seguidos la perdés.
                    </p>
                @endif
                <div class="mt-6 flex flex-col gap-2.5">
                    <button type="button" class="boton boton-tinta" @click="saliendo = false">Seguir jugando</button>
                    <a href="{{ route('portada') }}" class="boton boton-linea">{{ $rival === null ? 'Salir y seguir después' : 'Salir un momento' }}</a>
                    <form method="POST" action="{{ route('mesa.abandonar') }}" class="flex flex-col">
                        @csrf
                        <button type="submit" class="boton boton-linea">Abandonar la partida</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.base>
