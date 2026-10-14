@php
    use App\Juego\Categoria;
    use App\Juego\Experiencia;

    /*
     | El perfil es una mesa: sobre el paño, tu carta y el camino de las que se ganan; en un panel de
     | paño hondo, como el tanteador, quién sos y cuánto te falta para la próxima. Abajo, lo que se escribe.
     | Lo fuerte de la pantalla es una sola cosa: tu carta, que se da vuelta y muestra tu dorso.
     */
    $partes = fn (string $id) => ['numero' => (int) explode('-', $id)[0], 'palo' => explode('-', $id)[1]];
    $nombreDe = function (string $id) use ($partes) {
        ['numero' => $numero, 'palo' => $palo] = $partes($id);

        return 'el '.($numero === 1 ? 'ancho' : $numero).' de '.$palo;
    };

    $suya = $partes($carta);
    $dorsoElegido = collect($dorsos)->firstWhere('elegido', true);

    // Lo que lleva hacia la próxima carta, contado con fósforos: las ganadas desde la última que destrabó.
    $tramo = $proxima === null ? null : ['lleva' => $ganadas - $proxima['desde'], 'pide' => $proxima['ganadas'] - $proxima['desde']];

    // El camino partido en sus tres tramos: cada categoría es un grupo de cartas.
    $tramos = collect($camino)->groupBy(fn (array $paso) => $paso['categoria']->value);

    // Si se acaba de elegir un dorso, la carta arranca dada vuelta: es lo que cambió.
    $muestraElDorso = session('muestra') === 'dorso';
@endphp

<x-layouts.base titulo="Tu perfil" descripcion="Tu carta, tu categoría y el dorso de tus cartas en Vale Cuatro.">
    <div class="perfil" x-data="{ vuelta: {{ $muestraElDorso ? 'true' : 'false' }} }">
        <div class="perfil-mesa superficie-pano">
            <div class="perfil-grilla mx-auto max-w-6xl">
                {{-- Tu carta. Al tocarla se da vuelta: del otro lado está tu dorso, que es lo que ve tu rival. --}}
                <section aria-labelledby="titulo-carta" class="perfil-carta">
                    <h2 id="titulo-carta" class="sr-only">Tu carta</h2>

                    <div class="perfil-naipe se-reparte">
                        <button type="button" class="naipe-boton" @click="vuelta = ! vuelta"
                            :aria-label="vuelta ? 'Tu dorso: {{ $dorsoElegido['nombre'] }}. Dar vuelta para ver tu carta' : 'Tu carta: {{ $nombreDe($carta) }}. Dar vuelta para ver tu dorso'"
                            aria-label="Tu carta: {{ $nombreDe($carta) }}. Dar vuelta para ver tu dorso">
                            <span class="giro block" data-vuelta="{{ $muestraElDorso ? 'true' : 'false' }}" :data-vuelta="vuelta.toString()">
                                <span class="block"><x-carta :palo="$suya['palo']" :numero="$suya['numero']" /></span>
                                <span class="giro-cara block"><x-dorso :de="$dorso" /></span>
                            </span>
                        </button>
                    </div>

                    <p class="mt-6 min-h-[2lh] max-w-[22ch] text-center font-semibold leading-snug sm:text-lg" aria-live="polite">
                        <span x-show="! vuelta" @if ($muestraElDorso) x-cloak @endif>Tu carta. Así te ven en el ranking y en la mesa.</span>
                        <span x-show="vuelta" @unless ($muestraElDorso) x-cloak @endunless>Tu dorso. Es lo que ve tu rival.</span>
                    </p>

                    <button type="button" class="boton boton-linea mt-3 min-h-11 py-2" @click="vuelta = ! vuelta">
                        <x-icono nombre="repetir" /> <span x-text="vuelta ? 'Ver tu carta' : 'Ver tu dorso'">{{ $muestraElDorso ? 'Ver tu carta' : 'Ver tu dorso' }}</span>
                    </button>
                </section>

                {{-- El panel: quién sos y cuánto falta. Es de paño hondo, como el tanteador: ahí los fósforos se leen bien. --}}
                <section aria-labelledby="titulo-perfil" class="perfil-hoja sobre-pano bg-pano-hondo">
                    <h1 id="titulo-perfil" class="text-[clamp(2.5rem,5.2vw,4.5rem)] font-black leading-[0.94] tracking-[-0.035em] [overflow-wrap:anywhere]">{{ $jugador->apodo }}</h1>

                    <p class="mt-5 flex flex-wrap items-baseline gap-x-3 gap-y-1 leading-none">
                        <span class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $categoria->nombre() }}</span>
                        <span class="font-semibold"><strong class="text-xl font-black tabular-nums text-oro">{{ $ganadas }}</strong> {{ $ganadas === 1 ? 'partida ganada' : 'partidas ganadas' }}</span>
                    </p>

                    @if ($proxima)
                        @php
                            $queViene = $partes($proxima['carta']);
                            $grupos = intdiv($tramo['pide'] + 4, 5);
                        @endphp

                        <div class="mt-8 flex items-center gap-5">
                            <div class="w-16 flex-none sm:w-20" aria-hidden="true"><x-carta :palo="$queViene['palo']" :numero="$queViene['numero']" /></div>

                            <div class="min-w-0">
                                <p class="text-lg font-bold leading-snug">
                                    {{ $proxima['faltan'] === 1 ? 'Te falta' : 'Te faltan' }} <strong class="font-black tabular-nums text-oro">{{ $proxima['faltan'] }}</strong> para {{ $nombreDe($proxima['carta']) }}.
                                </p>
                                {{-- Las ganadas desde la última carta, anotadas como en el club. Lo que cuentan ya está dicho arriba. --}}
                                <p class="perfil-cuenta mt-3 flex flex-wrap gap-[0.22em] text-[2.1rem]" aria-hidden="true">
                                    @for ($g = 0; $g < $grupos; $g++)
                                        <x-fosforos :grupo="$g" :puntos="$tramo['lleva']" :hasta="$tramo['pide']" class="size-[1.45em]" />
                                    @endfor
                                </p>
                            </div>
                        </div>
                    @else
                        <p class="mt-8 max-w-[34ch] text-lg font-bold leading-snug">Tenés el ancho de espada: no hay carta más alta en el truco.</p>
                    @endif

                    @if ($categoria->siguiente())
                        <p class="mt-6 max-w-[40ch] leading-relaxed">
                            Con {{ $categoria->siguiente()->desde() }} ganadas pasás a {{ mb_strtolower($categoria->siguiente()->nombre()) }}.
                            Cuentan las mismas partidas que en el ranking.
                        </p>
                    @endif

                    <form method="POST" action="{{ route('jugar') }}" class="mt-7">
                        @csrf
                        <button type="submit" class="boton boton-naipe"><x-icono nombre="repartir" /> Jugar una partida</button>
                    </form>
                </section>

                {{-- El camino: las catorce cartas en el orden del truco. Boca arriba las ganadas, boca abajo las que faltan. --}}
                <section aria-labelledby="titulo-camino" class="perfil-camino">
                    <h2 id="titulo-camino" class="text-3xl font-extrabold tracking-tight sm:text-4xl">El camino</h2>
                    <p class="mt-3 max-w-[58ch] leading-relaxed sm:text-lg">
                        Catorce cartas en el orden del truco, del cuatro al ancho de espada. Cada una se gana con las partidas ganadas que dice debajo.
                        Tocá una de las tuyas para que te represente.
                    </p>

                    @error('carta')
                        <p role="alert" class="mt-4 inline-flex rounded-[0.625rem] bg-copa px-4 py-2.5 font-bold leading-snug text-naipe">{{ $message }}</p>
                    @enderror

                    <form method="POST" action="{{ route('perfil.carta') }}" class="camino mt-8">
                        @csrf
                        @method('PUT')

                        @foreach (Categoria::cases() as $tramoDeCategoria)
                            <fieldset class="camino-tramo min-w-0" style="--cartas: {{ count($tramos[$tramoDeCategoria->value]) }}">
                                <legend class="camino-rotulo">
                                    <span class="font-extrabold">{{ $tramoDeCategoria->nombre() }}</span>
                                </legend>

                                <ol class="camino-cartas">
                                    @foreach ($tramos[$tramoDeCategoria->value] as $paso)
                                        <li>
                                            @if ($paso['abierta'])
                                                <button type="submit" name="carta" value="{{ $paso['carta'] }}" class="naipe-boton"
                                                    aria-pressed="{{ $paso['elegida'] ? 'true' : 'false' }}"
                                                    aria-label="{{ ucfirst($nombreDe($paso['carta'])) }}{{ $paso['elegida'] ? ', tu carta' : ', elegir' }}">
                                                    <x-carta :palo="$partes($paso['carta'])['palo']" :numero="$partes($paso['carta'])['numero']" />
                                                </button>
                                            @else
                                                {{-- Lo que falta va boca abajo, con tu dorso: no se toca. --}}
                                                <div class="camino-cerrada" role="img" aria-label="{{ ucfirst($nombreDe($paso['carta'])) }}, se gana con {{ $paso['ganadas'] }} partidas ganadas">
                                                    <x-dorso :de="$dorso" aria-hidden="true" />
                                                </div>
                                            @endif

                                            <p @class(['camino-pide', 'camino-tuya' => $paso['elegida']]) aria-hidden="true">{{ $paso['elegida'] ? 'Tu carta' : $paso['ganadas'] }}</p>
                                        </li>
                                    @endforeach
                                </ol>
                            </fieldset>
                        @endforeach
                    </form>
                </section>

                {{-- Los dorsos: se ganan en la escalera, y el elegido es el que ve el rival. --}}
                <section aria-labelledby="titulo-dorsos" class="perfil-dorsos">
                    <h2 id="titulo-dorsos" class="text-3xl font-extrabold tracking-tight sm:text-4xl">Dorsos</h2>
                    <p class="mt-3 max-w-[58ch] leading-relaxed sm:text-lg">
                        El dorso de tus cartas es lo que ve quien juega contra vos. Los nuevos se ganan pasando escalones de la
                        <a href="{{ route('escalera') }}" class="enlace text-naipe">escalera del club</a>{{ $escalon <= \App\Juego\Escalera::cuantos() ? ": vas por el {$escalon}." : ': ya la pasaste entera.' }}
                    </p>

                    @error('dorso')
                        <p role="alert" class="mt-4 inline-flex rounded-[0.625rem] bg-copa px-4 py-2.5 font-bold leading-snug text-naipe">{{ $message }}</p>
                    @enderror

                    <form method="POST" action="{{ route('perfil.dorso') }}" class="mt-8">
                        @csrf
                        @method('PUT')

                        <ol class="dorsos-fila">
                            @foreach ($dorsos as $opcion)
                                <li>
                                    @if ($opcion['abierto'])
                                        <button type="submit" name="dorso" value="{{ $opcion['clave'] }}" class="naipe-boton"
                                            aria-pressed="{{ $opcion['elegido'] ? 'true' : 'false' }}"
                                            aria-label="Dorso {{ $opcion['nombre'] }}{{ $opcion['elegido'] ? ', en uso' : ', elegir' }}">
                                            <x-dorso :de="$opcion['clave']" aria-hidden="true" />
                                        </button>
                                    @else
                                        <div class="dorso-cerrado" role="img" aria-label="Dorso {{ $opcion['nombre'] }}, se gana en el escalón {{ $opcion['escalon'] }} de la escalera">
                                            <x-dorso :de="$opcion['clave']" aria-hidden="true" />
                                        </div>
                                    @endif

                                    <p class="mt-3 text-[0.9rem] font-extrabold leading-tight sm:text-base" aria-hidden="true">{{ $opcion['nombre'] }}</p>
                                    <p class="mt-0.5 text-sm font-semibold leading-snug" aria-hidden="true">
                                        @if ($opcion['elegido'])
                                            En uso
                                        @elseif ($opcion['abierto'])
                                            Ganado
                                        @else
                                            Escalón {{ $opcion['escalon'] }}
                                        @endif
                                    </p>
                                </li>
                            @endforeach
                        </ol>
                    </form>
                </section>
            </div>
        </div>

        {{-- Lo que se escribe y se declara, en la superficie de lectura. --}}
        <div class="mx-auto grid max-w-6xl gap-x-16 gap-y-14 px-5 pb-16 pt-12 sm:px-8 lg:grid-cols-2 lg:pt-16">
            <section aria-labelledby="titulo-experiencia">
                <h2 id="titulo-experiencia" class="text-3xl font-extrabold tracking-tight">¿Cuánto truco sabés?</h2>
                <p class="mt-3 max-w-[46ch] leading-relaxed">
                    Solo cambia contra qué bot entrás cuando no elegís y qué ayudas ves. No cambia las reglas, ni tu categoría, ni lo que ve tu rival.
                </p>

                <form method="POST" action="{{ route('perfil.experiencia') }}" class="mt-6 flex flex-col">
                    @csrf
                    @method('PUT')

                    @foreach (Experiencia::cases() as $opcion)
                        <button type="submit" name="experiencia" value="{{ $opcion->value }}" class="experiencia nivel-opcion"
                            aria-pressed="{{ $jugador->experiencia === $opcion ? 'true' : 'false' }}">
                            <x-nivel-fosforos :nivel="$opcion->nivel()" :puesto="$jugador->experiencia === $opcion" class="mt-0.5 text-[1.25rem]" />
                            <span class="min-w-0">
                                <span class="block text-lg font-extrabold leading-tight">{{ $opcion->nombre() }}</span>
                                <span class="mt-1 block leading-snug">{{ $opcion->detalle() }}</span>
                            </span>
                        </button>
                    @endforeach
                </form>
            </section>

            <section aria-labelledby="titulo-cuenta" x-data="{ apodo: {{ Js::from(old('apodo', $jugador->apodo)) }} }">
                <h2 id="titulo-cuenta" class="text-3xl font-extrabold tracking-tight">Tu cuenta</h2>

                <form method="POST" action="{{ route('perfil') }}" class="mt-6 flex max-w-[29rem] flex-col gap-6">
                    @csrf
                    @method('PUT')

                    <x-campo nombre="apodo" rotulo="Apodo" ayuda="De 3 a 20 caracteres. Es el nombre que ve tu rival."
                        :valor="$jugador->apodo" x-model="apodo" maxlength="20" autocomplete="nickname" autocapitalize="words" required />

                    <div>
                        <x-boton-enviar texto="Guardar apodo" enviando="Guardando…" class="boton-tinta" />
                    </div>
                </form>

                <dl class="mt-10 max-w-[29rem] border-t border-current/15 pt-6">
                    <dt class="font-bold">Email</dt>
                    <dd class="mt-0.5 break-words">{{ $jugador->email }}</dd>
                </dl>

                <form method="POST" action="{{ route('salir') }}" class="mt-8">
                    @csrf
                    <button type="submit" class="boton boton-linea">
                        <x-icono nombre="salir" /> Cerrar sesión
                    </button>
                </form>
            </section>
        </div>
    </div>
</x-layouts.base>
