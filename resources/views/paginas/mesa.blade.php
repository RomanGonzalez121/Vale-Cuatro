@php
    /*
     | Lo que la mesa le pide al servidor y lo que necesita saber de la partida. Con otra persona
     | enfrente suma su apodo, el pedido que resuelve un plazo vencido y lo que duran el turno y
     | la espera del reparto, para dibujar la cuenta regresiva.
     */
    // En un torneo el bot tiene apodo (es uno de los jugadores de ejemplo del ranking) y la partida va a 15.
    $torneo ??= null;
    // Un desafío o un escalón de la escalera: en una partida de la escalera el bot también tiene apodo.
    $practica ??= null;
    $esDesafio = $practica !== null && $practica['esMano'];
    $nombreDelBot = $torneo['rival'] ?? $practica['rival'] ?? null;
    // Cómo se nombra a quien está enfrente: el apodo de la otra persona, el del bot del torneo, o nada (es "el bot").
    $enfrente = $rival ?? $nombreDelBot;
    // De otra persona se ve además su categoría, su carta y el dorso que eligió para sus cartas.
    $socio ??= null;
    $cartaDelSocio = $socio === null ? null : explode('-', $socio['carta']);

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
        // Entre personas, lo que hace falta para pedir avisos al celular y para decir si la mesa está a la vista.
        'avisos' => $avisos ?? null,
        'rival' => $enfrente,
        // Si la partida es de un torneo: si es la final y adónde pasa quien gana.
        'torneo' => $torneo === null ? null : ['esFinal' => $torneo['esFinal'], 'sigue' => $torneo['sigue']],
        // Si es un desafío o un escalón: si es una sola mano con objetivo, y qué escalón.
        'practica' => $practica === null ? null : ['esMano' => $practica['esMano'], 'escalon' => $practica['escalon'], 'esElUltimo' => $practica['esElUltimo']],
        'turno' => \App\Juego\Mesa::SEGUNDOS_DE_TURNO,
        'reparto' => \App\Juego\Mesa::SEGUNDOS_PARA_REPARTIR,
        // Si la partida es de una serie al mejor de tres: cómo iba al empezar esta y cuántas hacen falta.
        'serie' => $serie ?? null,
        // Adónde ir cuando ya hay otra partida esperando: la siguiente de la serie, o la revancha.
        'mesa' => route('mesa'),
        'revancha' => [
            'estado' => route('revancha.estado'),
            'pedir' => route('revancha.pedir'),
            'aceptar' => route('revancha.aceptar'),
            'rechazar' => route('revancha.rechazar'),
            'cancelar' => route('revancha.cancelar'),
        ],
    ];

    $serie ??= null;
@endphp

<x-layouts.base titulo="Mesa" superficie="pano" :encabezado="false" :pie="false">
    <x-mazo.plantillas />

    {{-- La mesa ocupa la pantalla completa y nunca hace scroll: ver .mesa en app.css. --}}
    <div x-data="mesa(@js($vista), @js($pedidos))" @class(['mesa mesa-completa relative', 'mesa-ultra' => $nivel === \App\Juego\Nivel::UltraDificil])>
        <h1 class="sr-only">{{ $nivel ? ($nombreDelBot ? "Mesa contra {$nombreDelBot}, máquina de nivel {$nivel->nombre()}" : "Mesa contra la máquina, nivel {$nivel->nombre()}") : "Mesa contra {$rival}" }}</h1>

        {{-- Con el final de la partida a la vista, lo de atrás queda tapado: tampoco recibe el foco ni el lector de pantalla. --}}
        {{-- Con los ajustes desplegados la barra sube un escalón: la ventanita no puede quedar debajo del fósforo de la cuenta regresiva. --}}
        @if ($esDesafio)
            {{-- El objetivo va dicho de entrada para el lector de pantalla; a la vista está en su ventanita. --}}
            <p class="sr-only">Desafío: {{ $practica['titulo'] }}. {{ $practica['consigna'] }}</p>
        @endif

        <header class="mesa-barra relative z-10" :class="{ 'z-20': ajustando || conObjetivo }" :inert="fin !== null">
            {{-- En el celular el logo va sin el nombre: con los cuatro fósforos alcanza, y ese ancho lo necesitan los botones. --}}
            <div class="relative flex min-w-0 items-center gap-3 justify-self-start [grid-area:logo] lg:gap-5"
                @if ($esDesafio) @click.outside.capture="cerrarObjetivo(false)" @keydown.escape.window="cerrarObjetivo()" @endif>
                <a href="{{ route('portada') }}" class="rounded text-lg no-underline max-sm:text-2xl max-sm:[&_.logo-nombre]:hidden lg:text-2xl" aria-label="Vale Cuatro, ir al inicio"><x-logo /></a>

                {{--
                    El marcador de la serie, junto al logo: ahí no agrega una fila y la mesa mide lo mismo.
                    Dice cómo iba la serie al empezar esta partida, con lo propio primero, igual que el tanteador.
                --}}
                @if ($serie)
                    <p class="whitespace-nowrap text-sm font-semibold leading-none">
                        <span class="sr-only">Serie al mejor de tres: vos {{ $serie['vos'] }}, {{ $rival ?? 'la máquina' }} {{ $serie['rival'] }}.</span>
                        <span aria-hidden="true">Serie <span class="font-black tabular-nums text-oro">{{ $serie['vos'] }}</span> a <span class="font-black tabular-nums text-oro">{{ $serie['rival'] }}</span></span>
                    </p>
                @elseif ($torneo)
                    {{-- En un torneo, en ese mismo lugar va qué partido es. --}}
                    <p class="whitespace-nowrap text-sm font-semibold leading-none">
                        <span class="sr-only">Torneo, {{ mb_strtolower($torneo['partido']) }}.</span>
                        <span aria-hidden="true">{{ $torneo['corto'] }}</span>
                    </p>
                @elseif ($esDesafio)
                    {{--
                        En un desafío, ahí va el objetivo: un botón que despliega una ventanita con lo que hay que
                        lograr, como la de los ajustes. Se abre sola al llegar y se corre con la primera jugada;
                        no traba la mesa ni le agrega una fila.
                    --}}
                    <button type="button" x-ref="botonDeObjetivo" class="boton min-h-10 gap-1.5 px-2.5 py-1.5 text-sm" :class="conObjetivo ? 'boton-naipe' : 'boton-linea'"
                        @click="conObjetivo ? cerrarObjetivo() : abrirObjetivo()" aria-controls="objetivo-de-la-mesa" :aria-expanded="conObjetivo.toString()">
                        <x-icono nombre="desafio" class="size-5 max-sm:hidden" /> Objetivo
                    </button>

                    <div id="objetivo-de-la-mesa" x-ref="objetivo" tabindex="-1" x-show="conObjetivo" x-cloak
                        x-transition:enter="aparece" x-transition:enter-start="desde-chico" x-transition:leave="se-va" x-transition:leave-end="opacity-0"
                        class="objetivo superficie-naipe outline-none" role="dialog" aria-labelledby="titulo-objetivo">
                        <h2 id="titulo-objetivo" class="text-xl font-black leading-[1.05] tracking-tight">{{ $practica['titulo'] }}</h2>
                        <p class="mt-2 text-[1.05rem] leading-snug">{{ $practica['consigna'] }}</p>
                        <p class="mt-2.5 text-sm leading-snug">
                            Es una sola mano{{ $practica['escalon'] ? ", el escalón {$practica['escalon']} de la escalera" : '' }}. La máquina contesta siempre igual: si no sale, probá otro camino.
                        </p>
                    </div>
                @elseif ($practica)
                    {{-- En una partida de la escalera, qué escalón es. --}}
                    <p class="whitespace-nowrap text-sm font-semibold leading-none">Escalón {{ $practica['escalon'] }}</p>
                @endif
            </div>

            <section aria-label="Tanteador" class="grid grid-cols-2 gap-x-4 text-[clamp(0.8rem,4.1cqw,1.3rem)] [grid-area:tanteo] lg:gap-x-12">
                {{-- El asiento propio va siempre primero: quien se sentó por invitación es el 1. --}}
                <x-tanteador nombre="Vos" :puntos="$vista['tanteo'][$vista['asiento']]" modelo="puntos.vos" class="min-w-0" :hasta="$vista['puntosParaGanar']" />
                {{-- Un apodo puede tener 20 letras: si no entra en su mitad se corta, y los puntos quedan a la vista. --}}
                <x-tanteador :nombre="$enfrente ?? 'Máquina'" :puntos="$vista['tanteo'][1 - $vista['asiento']]" modelo="puntos.rival" class="min-w-0"
                    clase-nombre="min-w-0 truncate text-sm font-semibold" :hasta="$vista['puntosParaGanar']" />
            </section>

            <x-mesa.ajustes />
        </header>

        {{-- Tocar la mesa apura lo que se esté mostrando (los tantos del envido). --}}
        {{-- En pantallas anchas el campo suma dos costados (ver .con-costados en app.css). --}}
        <div class="mesa-campo con-costados" @click="apurar()" :inert="fin !== null">
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
                        @if ($socio)
                            {{-- Una persona se presenta con su carta y su categoría, en el lugar del ícono: la fila mide lo mismo. --}}
                            <span class="w-[1.15rem] flex-none sm:w-6" aria-hidden="true"><x-carta :palo="$cartaDelSocio[1]" :numero="(int) $cartaDelSocio[0]" /></span>
                        @else
                            <x-icono :nombre="$nivel ? 'bot' : 'jugador'" class="size-5" />
                        @endif
                        {{-- El nivel, con los mismos fósforos que se eligieron en los modos. Una persona no tiene nivel. --}}
                        @if ($nivel)
                            <x-nivel-fosforos :nivel="$nivel" class="text-[0.8rem]" />
                        @endif
                    </span>
                    {{-- Un apodo largo ocupa dos renglones como mucho: la fila del rival mide siempre lo mismo. --}}
                    {{-- En un torneo el bot va con su apodo: que es un bot lo dicen su ícono y los fósforos de su nivel. --}}
                    <span class="line-clamp-2">{{ $nivel ? ($nombreDelBot ?? 'Máquina '.$nivel->deLaMaquina()) : $rival }}</span>
                    {{-- La categoría de la otra persona, debajo de su apodo en el celular y al lado en pantallas anchas. --}}
                    @if ($socio)
                        <span class="whitespace-nowrap text-xs font-semibold sm:text-sm"><span class="sr-only">Categoría</span> {{ $socio['categoria'] }}</span>
                    @endif
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
                <div class="pointer-events-none absolute inset-0 z-10 flex flex-col items-center justify-center gap-[0.3em] pt-7" style="font-size: min(5.5rem, calc(var(--alto, 100dvh) * 0.09), 23cqw)" aria-hidden="true">
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
                    La ayuda de quien declaró que está aprendiendo: su tanto de envido ya sumado, mientras dura la
                    primera baza. Va sobre el borde de abajo de las cartas: no le agrega una fila a la mesa.
                --}}
                <p class="mesa-tanto" x-show="tantoPropio !== null && baza === 0 && ! cierre" x-cloak
                    x-transition:enter="aparece" x-transition:enter-start="opacity-0" x-transition:leave="se-va" x-transition:leave-end="opacity-0">
                    <span class="sr-only">Tu tanto de envido:</span> <span x-text="tantoPropio"></span> <span aria-hidden="true">de envido</span>
                </p>

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

            {{--
                Los costados de la mesa ancha. A un lado, lo que se cantó en esta mano, para releerlo sin esperar
                al aviso; al otro, el mazo. Sirven para jugar: no son adorno.
                En pantallas angostas no van (el mazo está junto al rival y el aviso cuenta lo cantado).
            --}}
            <aside class="mesa-costado mesa-costado-cantado" aria-label="Lo cantado en esta mano">
                <div class="costado-hoja">
                    <p class="costado-titulo"><span class="tabular-nums" x-text="vista.numeroDeMano">{{ $vista['numeroDeMano'] }}</span>ª mano</p>
                    {{--
                        La planilla del anotador: una columna para cada uno, con la raya al medio. Cada renglón es una
                        vuelta (mesa.js, planilla). Quién ganó los tantos va de lado a lado.
                    --}}
                    {{-- Las columnas se nombran aparte: si la hoja se llena, se tapa lo más viejo y no los nombres. --}}
                    <div class="planilla-cabeza" x-show="planilla.length > 0" aria-hidden="true">
                        <span>Vos</span>
                        <span>{{ $enfrente ?? 'Máquina' }}</span>
                    </div>
                    <div class="planilla-hoja" x-show="planilla.length > 0">
                        <table class="planilla" :class="{ 'planilla-apretada': planilla.length > 6 }">
                            <thead class="sr-only">
                                <tr>
                                    <th scope="col">Vos</th>
                                    <th scope="col">{{ $enfrente ?? 'Máquina' }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(fila, numero) in planilla" :key="numero">
                                    <tr :class="{ 'planilla-resultado': fila.resultado }">
                                        <td :colspan="fila.resultado ? 2 : 1" :data-tono="fila.vos?.tono" x-text="fila.resultado ?? fila.vos?.texto ?? ''"></td>
                                        <td x-show="! fila.resultado" :data-tono="fila.rival?.tono" x-text="fila.rival?.texto ?? ''"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <p class="costado-vacio" x-show="planilla.length === 0">Todavía no se cantó nada.</p>
                </div>
            </aside>

            {{--
                El mazo queda delante de quien repartió, como en la mesa de verdad: arriba si fue el rival, abajo si
                fuiste vos. Reparte el que no es mano, así que en cada mano se pasa al otro lado (mesa.js, pasarElMazo).
            --}}
            <aside class="mesa-costado mesa-costado-mazo" aria-label="El mazo" x-ref="costadoDelMazo" data-reparte="{{ $vista['mano'] === $vista['asiento'] ? 'rival' : 'vos' }}">
                <div x-ref="pilaAlCostado" class="costado-pila">
                    <div x-ref="mazoAlCostado" class="mesa-mazo costado-mazo" aria-hidden="true">
                        @foreach (range(1, 6) as $carta)
                            <x-dorso />
                        @endforeach
                    </div>
                    <p class="costado-quien" x-text="esMano ? `Repartió ${rival}` : 'Repartiste vos'"></p>
                </div>
            </aside>
        </div>

        <x-mesa.cantos :practica="$esDesafio ? $practica : null" />

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
                    x-text="fin === 'vos' ? 'Ganaste la partida' : @js($enfrente ? "Ganó {$enfrente}" : 'Ganó la máquina')"></h2>
                <p id="tanteo-fin" class="sr-only">
                    Vos <span x-text="puntos.vos"></span>, {{ $enfrente ?? 'la máquina' }} <span x-text="puntos.rival"></span>.
                    <span x-text="fraseDelNivel"></span>
                </p>

                <div class="mt-7 grid gap-5 text-[1.5rem] sm:text-[1.75rem]" aria-hidden="true">
                    <x-tanteador nombre="Vos" modelo="cuenta.vos" class="min-w-0" clase-nombre="text-base font-semibold" :hasta="$vista['puntosParaGanar']" />
                    <x-tanteador :nombre="$enfrente ?? 'Máquina'" modelo="cuenta.rival" class="min-w-0" clase-nombre="min-w-0 truncate text-base font-semibold" :hasta="$vista['puntosParaGanar']" />
                </div>

                <x-mesa.experiencia />

                {{-- En un torneo, qué pasa con esta partida: se pasa de ronda, se es campeón o se queda afuera. Y se vuelve a las llaves. --}}
                @if ($torneo)
                    <p class="mt-6 text-lg font-bold leading-snug" x-text="fraseDelTorneo"></p>

                    <div class="mt-6 flex flex-col gap-2.5 sm:flex-row">
                        <a href="{{ $torneo['llaves'] }}" class="boton boton-naipe"><x-icono nombre="torneo" /> Ver las llaves</a>
                        <a href="{{ route('historial') }}" class="boton boton-linea">Ver el historial</a>
                    </div>
                @endif

                {{-- En la escalera, si con esta partida se pasó el escalón, y lo que sigue: subir, reintentar o volver a mirarla. --}}
                @if ($practica && ! $esDesafio)
                    <p class="mt-6 text-lg font-bold leading-snug" x-text="fraseDeLaEscalera"></p>

                    <div class="mt-6 flex flex-col gap-2.5 sm:flex-row">
                        @if ($practica['siguiente'])
                            <form method="POST" action="{{ $practica['siguiente'] }}" class="flex flex-col" x-show="fin === 'vos'" x-cloak>
                                @csrf
                                <button type="submit" class="boton boton-naipe"><x-icono nombre="escalera" /> Subir al escalón {{ $practica['escalon'] + 1 }}</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ $practica['reintentar'] }}" class="flex flex-col" x-show="fin !== 'vos'" x-cloak>
                            @csrf
                            <button type="submit" class="boton boton-naipe"><x-icono nombre="repetir" /> Probar de nuevo</button>
                        </form>
                        <a href="{{ $practica['volver'] }}" class="boton boton-linea">Ver la escalera</a>
                    </div>
                @endif

                {{-- En una serie, cómo quedó con esta partida: si sigue, o quién se la llevó. --}}
                @if ($serie)
                    <p class="mt-6 text-lg font-bold leading-snug" x-text="fraseDeLaSerie"></p>
                @endif

                {{--
                    Lo que sigue. Si la serie no terminó, la partida siguiente ya está repartida: se va a ella.
                    Si no, la revancha: contra el bot se juega en el momento (el bot quiere siempre); con otra
                    persona se pide, y el otro contesta como se contesta un canto, con "quiero" o "no quiero".
                --}}
                @if ($serie)
                    <div class="mt-6 flex flex-col gap-2.5 sm:flex-row" x-show="sigueLaSerie" x-cloak>
                        <a href="{{ route('mesa') }}" class="boton boton-naipe"><x-icono nombre="repartir" /> Jugar la siguiente partida</a>
                        <a href="{{ route('historial') }}" class="boton boton-linea">Ver el historial</a>
                    </div>
                @endif

                {{-- En un torneo no hay revancha: lo que sigue está en las llaves. En la escalera tampoco: se reintenta el escalón. --}}
                @unless ($torneo || $practica)
                <div @class(['mt-8' => ! $serie, 'mt-6' => $serie]) x-show="! sigueLaSerie">
                    @if ($nivel)
                        <div class="flex flex-col gap-2.5 sm:flex-row">
                            <form method="POST" action="{{ route('revancha.pedir') }}" class="flex flex-col">
                                @csrf
                                {{-- La revancha es contra el mismo nivel, y el mano cambia de lado. --}}
                                <input type="hidden" name="partida" value="{{ $vista['partida'] }}">
                                <button type="submit" class="boton boton-naipe"><x-icono nombre="repetir" /> Jugar la revancha</button>
                            </form>
                            <a href="{{ route('historial') }}" class="boton boton-linea">Ver el historial</a>
                        </div>
                    @else
                        {{-- Guarda siempre su renglón: cuando llega el pedido del otro, los botones no saltan. --}}
                        <p role="status" aria-live="polite" class="min-h-[1.4em] font-semibold leading-snug" x-text="avisoDeRevancha"></p>
                        {{-- La cuenta se ve pero no se anuncia: un lector de pantalla no tiene que leer cada segundo. --}}
                        <p class="text-[0.95rem] leading-snug" aria-hidden="true" x-show="revancha.restan !== null" x-cloak>
                            Se vence en <span class="font-bold tabular-nums" x-text="revancha.restan"></span> <span x-text="revancha.restan === 1 ? 'segundo' : 'segundos'"></span>.
                        </p>

                        <div class="mt-3 flex flex-col gap-2.5 sm:flex-row sm:flex-wrap">
                            <button type="button" class="boton boton-naipe" x-show="revancha.estado === 'disponible'" x-cloak :disabled="revancha.pidiendo" @click="revanchar('pedir')">
                                <x-icono nombre="repetir" /> Pedir revancha
                            </button>
                            <button type="button" class="boton boton-linea" x-show="revancha.estado === 'pedida'" x-cloak :disabled="revancha.pidiendo" @click="revanchar('cancelar')">
                                Cancelar el pedido
                            </button>
                            <button type="button" class="boton boton-basto" x-show="revancha.estado === 'te_piden'" x-cloak :disabled="revancha.pidiendo" @click="revanchar('aceptar')">
                                <x-icono nombre="quiero" /> Quiero la revancha
                            </button>
                            <button type="button" class="boton boton-copa" x-show="revancha.estado === 'te_piden'" x-cloak :disabled="revancha.pidiendo" @click="revanchar('rechazar')">
                                <x-icono nombre="no-quiero" /> No quiero
                            </button>
                            {{-- Mientras hay un pedido a la vista, lo único que se hace es contestarlo o esperarlo. --}}
                            <a href="{{ route('modos') }}" class="boton boton-linea" x-show="! revanchaALaVista">Elegir cómo jugar</a>
                            <a href="{{ route('historial') }}" class="boton boton-linea" x-show="! revanchaALaVista">Ver el historial</a>
                        </div>
                    @endif
                </div>
                @endunless
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
                @if ($practica)
                    <p class="mt-2 leading-relaxed">{{ $esDesafio ? 'El desafío' : 'El escalón' }} queda guardado: cuando vuelvas a jugar, sigue donde lo dejaste. Si lo abandonás, cuenta como un intento.</p>
                @elseif ($rival === null)
                    <p class="mt-2 leading-relaxed">La partida queda guardada: cuando vuelvas a jugar, sigue donde la dejaste. Si la abandonás, la perdés{{ $serie ? ', y con ella la serie' : '' }}{{ $torneo ? ' y quedás afuera del torneo' : '' }}.</p>
                @else
                    <p class="mt-2 leading-relaxed">
                        Con otra persona la partida no se detiene. Si salís, cada turno tuyo se vence a los {{ \App\Juego\Mesa::SEGUNDOS_DE_TURNO }} segundos,
                        y con {{ \App\Juego\Mesa::VENCIMIENTOS_PARA_PERDER }} vencidos seguidos la perdés{{ $serie ? ', y con ella la serie' : '' }}.
                    </p>
                @endif
                <div class="mt-6 flex flex-col gap-2.5">
                    <button type="button" class="boton boton-tinta" @click="saliendo = false">Seguir jugando</button>
                    <a href="{{ route('portada') }}" class="boton boton-linea">{{ $rival === null ? 'Salir y seguir después' : 'Salir un momento' }}</a>
                    <form method="POST" action="{{ route('mesa.abandonar') }}" class="flex flex-col">
                        @csrf
                        {{-- Se abandona la partida que esta mesa muestra, y ninguna otra. --}}
                        <input type="hidden" name="partida" value="{{ $vista['partida'] }}">
                        <button type="submit" class="boton boton-linea">{{ $practica ? ($esDesafio ? 'Abandonar el desafío' : 'Abandonar el escalón') : 'Abandonar la partida' }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.base>
