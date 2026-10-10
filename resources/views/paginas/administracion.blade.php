@php
    use App\Juego\Historial;
    use App\Juego\Mesa;
    use App\Models\Jugador;

    /*
     | El panel de administración. Todo lo que muestra viene resuelto de App\Administracion\Panel:
     | de una partida en curso llegan quiénes juegan, el tanteo y cuándo se movió. Nunca una carta.
     */
    $quietas = $partidas['quietas'];
    $enJuego = $partidas['enJuego'];
    // Los totales cuentan todas las partidas, también las que no entraron en las listas.
    $cuantasQuietas = $partidas['cuantasQuietas'];
    $jugandose = $partidas['cuantasEnJuego'];
    $fallidos = $cola['fallidos'];
    $paraLimpiar = $porLimpiar['salas'] + $porLimpiar['invitados'];

    // Cuántos, con su palabra: "1 partida", "3 partidas".
    $contar = fn (int $cuantos, string $uno, string $varios) => $cuantos.' '.($cuantos === 1 ? $uno : $varios);

    $ordinales = ['primera', 'segunda', 'tercera', 'cuarta', 'quinta', 'sexta', 'séptima', 'octava', 'novena', 'décima'];
    $mano = fn (int $numero) => isset($ordinales[$numero - 1]) ? $ordinales[$numero - 1].' mano' : "mano {$numero}";

    // El día como se dice hablando, igual que en el historial.
    $dia = Historial::dia(...);

    // Cómo va una partida, o que no se pudo leer (un evento dañado): esa también se puede cerrar.
    $comoVa = fn (array $partida) => $partida['tanteo'] === null
        ? 'No se pudo leer cómo va.'
        : "Va {$partida['tanteo'][0]} a {$partida['tanteo'][1]}, ".$mano($partida['mano']).'.';

    /*
     | El estado del sitio, dicho en cuatro frases: es lo primero que se lee. Cada una lleva a su parte
     | del panel, y las que piden que alguien haga algo van en el color de las alertas.
     */
    $estado = [
        ['#en-juego', $jugandose === 0 ? 'No hay partidas en juego.' : ($jugandose === 1 ? 'Hay 1 partida en juego.' : "Hay {$jugandose} partidas en juego."), $jugandose, false],
        ['#sin-movimiento', $cuantasQuietas === 0 ? 'Ninguna quedó sin movimiento.' : ($cuantasQuietas === 1 ? '1 quedó sin movimiento.' : "{$cuantasQuietas} quedaron sin movimiento."), $cuantasQuietas, $cuantasQuietas > 0],
        ['#cola', $fallidos === [] ? 'Ningún trabajo falló.' : (count($fallidos) === 1 ? 'Falló 1 trabajo.' : 'Fallaron '.count($fallidos).' trabajos.'), count($fallidos), $fallidos !== []],
        ['#limpieza', $paraLimpiar === 0 ? 'No hay nada para limpiar.' : 'La limpieza tiene '.$contar($paraLimpiar, 'cosa', 'cosas').' para llevarse.', $paraLimpiar, false],
    ];

    // El apodo de cada lado en el tanteador de una partida: corto, para que entren los dos.
    $enElTanteador = fn (array $partida) => [$partida['uno'], $partida['contraElBot'] ? 'Bot' : $partida['otro']];
@endphp

<x-layouts.base titulo="Administración" descripcion="Panel de administración de Vale Cuatro.">
    <div class="mx-auto max-w-5xl px-5 pb-24 sm:px-8">
        <header class="pb-10 pt-10 sm:pt-16">
            {{-- Una sola palabra larga: en el celular se mide contra el ancho para que entre entera. --}}
            <h1 class="text-[clamp(1.9rem,9.6vw,3rem)] font-black leading-none tracking-tight sm:text-7xl">Administración</h1>

            <p class="mt-5 max-w-[60ch] text-lg leading-relaxed">
                Acá no se ven cartas ni se tocan resultados. Cada cosa que hagas queda anotada en el cuaderno, al final.
            </p>
        </header>

        {{--
            El pizarrón del club: cómo está el sitio, dicho en cuatro frases y contado con fósforos, igual
            que el tanteo. Cada frase lleva a su parte del panel. La que pide que alguien haga algo lleva
            una ficha en Copa (sobre el paño, Copa va como ficha llena y nunca como texto).
        --}}
        <section aria-labelledby="titulo-pizarron" class="superficie-pano mb-12 rounded-xl px-5 py-2 sm:px-9 sm:py-4">
            <h2 id="titulo-pizarron" class="sr-only">Cómo está el sitio</h2>

            <ul>
                @foreach ($estado as [$ancla, $frase, $cuantos, $pideAtencion])
                    <li class="grid items-center gap-x-8 gap-y-2.5 border-b border-naipe/20 py-4 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_auto] sm:py-5">
                        <p class="flex flex-wrap items-center gap-x-3.5 gap-y-1.5 text-xl font-extrabold leading-tight tracking-tight sm:text-[1.7rem]">
                            <a href="{{ $ancla }}" class="enlace-nav">{{ $frase }}</a>
                            @if ($pideAtencion)
                                <span class="rounded-md bg-copa px-2 py-1 text-sm font-bold leading-none tracking-normal text-naipe">Para revisar</span>
                            @endif
                        </p>
                        <x-administracion.cuenta :cantidad="$cuantos" class="text-[1rem] sm:justify-end sm:text-[1.4rem]" />
                    </li>
                @endforeach
            </ul>
        </section>

        <section id="sin-movimiento" aria-labelledby="titulo-sin-movimiento" class="border-t-2 border-texto pb-8 pt-5">
            <h2 id="titulo-sin-movimiento" class="flex items-center gap-2.5 text-2xl font-extrabold tracking-tight"><x-icono nombre="tiempo" class="size-6" /> Sin movimiento</h2>
            <p class="mt-2 max-w-[64ch] leading-relaxed">
                Una partida contra el bot queda acá después de {{ Mesa::HORAS_QUIETA_CONTRA_EL_BOT }} horas sin jugadas; una entre dos personas, después de {{ Mesa::MINUTOS_QUIETA_ENTRE_PERSONAS }} minutos.
                Cerrarla no borra sus jugadas: se anota al final de su lista y deja de ser la partida en curso de quienes la jugaban. No la gana nadie, así que no cuenta para el ranking ni aparece en el historial.
            </p>

            @if ($cuantasQuietas === 0)
                <p class="mt-5 text-lg font-bold">Ninguna partida quedó sin movimiento.</p>
            @else
                @if ($cuantasQuietas > count($quietas))
                    <p class="mt-4 text-[0.95rem]">Se muestran las {{ count($quietas) }} que hace más que están quietas, de {{ $cuantasQuietas }}.</p>
                @endif

                <ul class="mt-3">
                    @foreach ($quietas as $partida)
                        <li class="grid items-center gap-x-6 gap-y-3 border-b border-texto/15 py-4 last:border-b-0 sm:grid-cols-[1fr_auto] lg:grid-cols-[1fr_auto_auto]">
                            <p class="min-w-0 leading-snug">
                                <span class="block text-lg font-bold [overflow-wrap:anywhere]">{{ $partida['uno'] }} contra {{ $partida['otro'] }}</span>
                                <span class="block text-[0.95rem]">
                                    Partida {{ $partida['id'] }}{{ $partida['enSerie'] ? ', de una serie' : '' }}. {{ $comoVa($partida) }}
                                    <span class="font-semibold">Se movió {{ $partida['movida']->locale('es')->diffForHumans() }}.</span>
                                </span>
                            </p>
                            {{-- El tanteo como en la mesa: es dibujo, el número ya está en el renglón. --}}
                            <div class="flex w-fit gap-5 rounded-lg bg-pano-hondo px-3.5 py-2.5 text-[0.66rem] max-lg:hidden" aria-hidden="true">
                                <x-tanteador :nombre="$enElTanteador($partida)[0]" :puntos="$partida['tanteo'][0] ?? 0" class="w-36" clase-nombre="min-w-0 truncate text-sm font-semibold" />
                                <x-tanteador :nombre="$enElTanteador($partida)[1]" :puntos="$partida['tanteo'][1] ?? 0" class="w-36" clase-nombre="min-w-0 truncate text-sm font-semibold" />
                            </div>
                            <x-administracion.accion :ruta="route('administracion.partida.cerrar', $partida['id'])" texto="Cerrar partida" confirma="Sí, cerrar la partida {{ $partida['id'] }}" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section id="en-juego" aria-labelledby="titulo-en-juego" class="border-t-2 border-texto pb-8 pt-5">
            <h2 id="titulo-en-juego" class="flex items-center gap-2.5 text-2xl font-extrabold tracking-tight"><x-icono nombre="repartir" class="size-6" /> En juego</h2>

            @if ($jugandose === 0)
                <p class="mt-4 text-lg font-bold">Ahora no se está jugando ninguna partida.</p>
            @else
                <ul class="mt-2">
                    @foreach ($enJuego as $partida)
                        <li class="grid items-center gap-x-6 gap-y-1 border-b border-texto/15 py-3.5 last:border-b-0 sm:grid-cols-[1fr_auto]">
                            <p class="min-w-0 leading-snug">
                                <span class="block text-lg font-bold [overflow-wrap:anywhere]">{{ $partida['uno'] }} contra {{ $partida['otro'] }}</span>
                                <span class="block text-[0.95rem]">Partida {{ $partida['id'] }}{{ $partida['enSerie'] ? ', de una serie' : '' }}. {{ $comoVa($partida) }} Se movió {{ $partida['movida']->locale('es')->diffForHumans() }}.</span>
                            </p>
                            <div class="flex w-fit gap-5 rounded-lg bg-pano-hondo px-3.5 py-2.5 text-[0.66rem] max-lg:hidden" aria-hidden="true">
                                <x-tanteador :nombre="$enElTanteador($partida)[0]" :puntos="$partida['tanteo'][0] ?? 0" class="w-36" clase-nombre="min-w-0 truncate text-sm font-semibold" />
                                <x-tanteador :nombre="$enElTanteador($partida)[1]" :puntos="$partida['tanteo'][1] ?? 0" class="w-36" clase-nombre="min-w-0 truncate text-sm font-semibold" />
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if ($jugandose > count($enJuego))
                    <p class="mt-4 text-[0.95rem]">Se muestran las {{ count($enJuego) }} que se movieron último, de {{ $jugandose }}.</p>
                @endif
            @endif
        </section>

        <section id="cola" aria-labelledby="titulo-cola" class="border-t-2 border-texto pb-8 pt-5">
            <h2 id="titulo-cola" class="flex items-center gap-2.5 text-2xl font-extrabold tracking-tight"><x-icono nombre="bot" class="size-6" /> La cola</h2>
            <p class="mt-2 max-w-[64ch] leading-relaxed">
                De la cola salen el turno del bot y los plazos de las partidas entre dos personas.
                {{ $cola['esperan'] === 0 ? 'Ahora no espera ningún trabajo.' : 'Ahora '.($cola['esperan'] === 1 ? 'espera 1 trabajo.' : "esperan {$cola['esperan']} trabajos.") }}
            </p>

            @if ($fallidos === [])
                <p class="mt-5 text-lg font-bold">Ningún trabajo falló.</p>
            @else
                <ul class="mt-3">
                    @foreach ($fallidos as $trabajo)
                        <li class="grid items-center gap-x-6 gap-y-3 border-b border-texto/15 py-4 last:border-b-0 sm:grid-cols-[1fr_auto]">
                            <p class="min-w-0 leading-snug">
                                <span class="block text-lg font-bold [overflow-wrap:anywhere]">{{ $trabajo['trabajo'] }}</span>
                                {{-- Del error va solo su clase: el mensaje completo queda en el registro del servidor. --}}
                                <span class="block text-[0.95rem] [overflow-wrap:anywhere]">Falló {{ mb_strtolower($dia($trabajo['cuando'])) }} a las {{ $trabajo['cuando']->format('H:i') }}, con un error {{ $trabajo['error'] }}.</span>
                            </p>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-administracion.accion :ruta="route('administracion.trabajo.reintentar', $trabajo['id'])" texto="Reintentar" icono="repetir" />
                                <x-administracion.accion :ruta="route('administracion.trabajo.descartar', $trabajo['id'])" texto="Descartar" confirma="Sí, descartarlo" />
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section id="limpieza" aria-labelledby="titulo-limpieza" class="border-t-2 border-texto pb-8 pt-5">
            <h2 id="titulo-limpieza" class="flex items-center gap-2.5 text-2xl font-extrabold tracking-tight"><x-icono nombre="mazo" class="size-6" /> Limpieza</h2>
            <p class="mt-2 max-w-[64ch] leading-relaxed">
                El sitio se limpia solo: cierra cada cinco minutos las salas que esperaron rival más de {{ Mesa::MINUTOS_DE_SALA }} minutos, y borra una vez por día a los invitados que no juegan hace más de {{ Jugador::DIAS_DE_INVITADO }} días.
            </p>
            <p class="mt-4 text-lg font-bold">
                {{ $paraLimpiar === 0 ? 'Ahora no hay nada para limpiar.' : 'Ahora hay '.$contar($porLimpiar['salas'], 'sala vencida', 'salas vencidas').' y '.$contar($porLimpiar['invitados'], 'invitado por borrar', 'invitados por borrar').'.' }}
            </p>
            <x-administracion.accion :ruta="route('administracion.limpiar')" texto="Correr la limpieza ahora" class="mt-4" />
        </section>

        <section id="apodos" aria-labelledby="titulo-apodos" class="border-t-2 border-texto pb-8 pt-5">
            <h2 id="titulo-apodos" class="flex items-center gap-2.5 text-2xl font-extrabold tracking-tight"><x-icono nombre="jugador" class="size-6" /> Apodos</h2>
            <p class="mt-2 max-w-[64ch] leading-relaxed">
                Los apodos son públicos. Ocultar uno lo reemplaza por "Jugador" y un número en todas las pantallas; su dueño puede elegir otro desde el perfil.
            </p>

            <form method="GET" action="{{ route('administracion') }}#apodos" class="mt-5 flex max-w-md flex-wrap items-end gap-x-4 gap-y-3">
                <div class="min-w-[12rem] flex-1">
                    <x-campo nombre="apodo" rotulo="Apodo, o una parte" :valor="$buscado" maxlength="20" autocomplete="off" />
                </div>
                <button type="submit" class="boton boton-tinta min-h-11 px-5 py-2">Buscar</button>
            </form>

            @if ($buscado !== '')
                @if ($jugadores === [])
                    <p class="mt-5 text-lg font-bold">Nadie tiene un apodo con "{{ $buscado }}".</p>
                @else
                    <ul class="mt-3">
                        @foreach ($jugadores as $jugador)
                            <li class="grid items-center gap-x-6 gap-y-3 border-b border-texto/15 py-4 last:border-b-0 sm:grid-cols-[1fr_auto]">
                                <p class="min-w-0 leading-snug">
                                    <span class="block text-lg font-bold [overflow-wrap:anywhere]">{{ $jugador['apodo'] }}</span>
                                    <span class="block text-[0.95rem]">Jugador {{ $jugador['id'] }}, {{ $jugador['conCuenta'] ? 'con cuenta' : 'sin cuenta' }}.</span>
                                </p>
                                @if ($jugador['neutro'])
                                    <p class="text-[0.95rem]">Su apodo ya es de los sorteados.</p>
                                @else
                                    <x-administracion.accion :ruta="route('administracion.apodo.ocultar', $jugador['id'])" texto="Ocultar apodo" confirma="Sí, ocultarlo" />
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </section>

        <section id="cuaderno" aria-labelledby="titulo-cuaderno" class="border-t-2 border-texto pt-5">
            <h2 id="titulo-cuaderno" class="flex items-center gap-2.5 text-2xl font-extrabold tracking-tight"><x-icono nombre="repetir" class="size-6" /> El cuaderno</h2>

            @if ($cuaderno === [])
                <p class="mt-4 text-lg font-bold">Todavía no hay nada anotado.</p>
            @else
                {{-- El margen de la libreta: una línea al costado, y cada anotación en su renglón. --}}
                <ol class="mt-4 border-l-2 border-texto pl-4 sm:pl-6">
                    @foreach ($cuaderno as $anotacion)
                        <li class="grid gap-x-6 gap-y-0.5 border-b border-texto/15 py-3 leading-snug first:pt-1 last:border-b-0 last:pb-1 sm:grid-cols-[9.5rem_1fr]">
                            <time datetime="{{ $anotacion['cuando']->toIso8601String() }}" class="text-[0.95rem] font-semibold tabular-nums">{{ $dia($anotacion['cuando']) }}, {{ $anotacion['cuando']->format('H:i') }}</time>
                            <span class="[overflow-wrap:anywhere]"><strong class="font-bold">{{ $anotacion['quien'] }}</strong> {{ $anotacion['detalle'] }}.</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
</x-layouts.base>
