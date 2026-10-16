@php
    use App\Juego\Camino;
    use App\Juego\Experiencia;

    /*
     | El perfil. Arriba, "tu mesa": sobre el paño, tu mazo apilado y tu carta grande, que se reparte desde el
     | mazo al entrar; al lado, quién sos, tu nivel con su tira y lo que viene. Debajo, un índice y las
     | secciones: los números como la planilla del club, la Colección con su probador (sobre el paño, porque
     | es lo que usás en la mesa), el camino, las últimas partidas y la cuenta.
     |
     | El mismo archivo dibuja el perfil de otro ($propio en false): su mesa, su carta, su nivel, sus números
     | y sus últimas partidas. Nada de la cuenta, nada de lo que eligió para ver él.
     */
    $partes = fn (string $id) => ['numero' => (int) explode('-', $id)[0], 'palo' => explode('-', $id)[1]];
    $nombreDe = function (string $id) use ($partes) {
        ['numero' => $numero, 'palo' => $palo] = $partes($id);

        return 'el '.($numero === 1 ? 'ancho' : $numero).' de '.$palo;
    };

    $suya = $partes($carta);
    $tipos = ['carta' => 'Carta', 'dorso' => 'Dorso', 'mesa' => 'Mesa', 'mazo' => 'Mazo', 'anotar' => 'Anotar', 'distintivo' => 'Distintivo'];
    $esDeEjemplo = $jugador->esDeEjemplo();

    if ($propio) {
        $dorsoElegido = collect($dorsos)->firstWhere('elegido', true);
        // Si se acaba de elegir algo con el formulario común, la Colección vuelve abierta en esa pestaña, y si
        // fue un dorso, la carta del probador arranca dada vuelta: es lo que cambió.
        $muestra = session('muestra');
        $muestraElDorso = $muestra === 'dorso';
        $pestanaInicial = ['dorso' => 'dorsos', 'mesa' => 'mesas', 'mazo' => 'mazos', 'anotar' => 'anotar', 'distintivo' => 'distintivos', 'titulo' => 'titulos'][$muestra] ?? 'cartas';
        $pestanas = ['cartas' => 'Cartas', 'dorsos' => 'Dorsos', 'mesas' => 'Mesas', 'mazos' => 'Mazos', 'anotar' => 'Anotar', 'titulos' => 'Títulos', 'distintivos' => 'Distintivos'];
        $rutas = [
            'carta' => route('perfil.carta'),
            'dorso' => route('perfil.dorso'),
            'mesa' => route('perfil.mesa'),
            'mazo' => route('perfil.mazo'),
            'anotar' => route('perfil.anotar'),
            'distintivo' => route('perfil.distintivo'),
            'titulo' => route('perfil.titulo'),
        ];
        // Cada mazo se muestra como una mano de tres: un as o una baja a cada lado y una figura al medio. Cada uno con
        // su trío, así la fila no se repite y se ve qué cambia: la tinta, los palos y la figura.
        $manoDelMazo = fn (string $mazo) => [
            'fabrica' => [['espada', 1], ['oro', 7], ['copa', 12]],
            'noche' => [['basto', 1], ['copa', 3], ['oro', 11]],
            'una-tinta' => [['espada', 7], ['oro', 4], ['basto', 10]],
            'palos-de-fosforo' => [['basto', 7], ['copa', 5], ['espada', 11]],
            'retratos' => [['oro', 1], ['copa', 6], ['espada', 12]],
            'fosforo-de-noche' => [['oro', 7], ['basto', 4], ['copa', 10]],
            'imprenta' => [['copa', 1], ['espada', 5], ['basto', 11]],
            'billete' => [['espada', 1], ['copa', 4], ['oro', 12]],
            'afiche' => [['copa', 7], ['oro', 3], ['espada', 12]],
            'catalan' => [['oro', 1], ['basto', 6], ['copa', 10]],
            'fileteado' => [['basto', 1], ['oro', 5], ['copa', 11]],
            'criollo' => [['copa', 1], ['espada', 7], ['oro', 12]],
        ][$mazo] ?? [['espada', 1], ['oro', 7], ['copa', 12]];
        $deTipo = fn (string $tipo) => array_values(array_filter($proximamente, fn (array $ficha) => $ficha['tipo'] === $tipo));

        // Lo que el probador muestra de entrada: tu carta, o tu dorso si recién lo cambiaste.
        $prueba = $muestraElDorso
            ? ['tipo' => 'dorso', 'clave' => $dorsoElegido['clave'], 'nombre' => 'Dorso '.$dorsoElegido['nombre'], 'abierta' => true, 'elegida' => true, 'pide' => '']
            : ['tipo' => 'carta', 'clave' => $carta, 'nombre' => ucfirst($nombreDe($carta)), 'abierta' => true, 'elegida' => true, 'pide' => ''];
    }

    $secciones = $propio
        ? ['resumen' => 'Resumen', 'libreta' => 'Libreta', 'coleccion' => 'Colección', 'camino' => 'Camino', 'partidas' => 'Partidas', 'cuenta' => 'Cuenta']
        : ['resumen' => 'Resumen', 'libreta' => 'Libreta', 'partidas' => 'Partidas'];

    // La libreta del club: cuántos sellos tiene de cuántos, y el tema que se ve primero (el primero con algo nuevo).
    $sellosTiene = array_sum(array_column($libreta, 'tiene'));
    $sellosTotal = array_sum(array_column($libreta, 'total'));
    $temaInicial = collect($libreta)->search(fn ($tema) => collect($tema['logros'])->contains('nuevo', true)) ?: 'envido';
@endphp

<x-layouts.base :titulo="$propio ? 'Tu perfil' : $jugador->apodo" :descripcion="$propio ? 'Tu mesa, tu nivel, tus números y tu colección en Vale Cuatro.' : 'El perfil de '.$jugador->apodo.' en Vale Cuatro: su carta, su nivel y sus números.'">
    <div class="pb-16 lg:pb-24" x-data="perfil({ jugador: {{ $jugador->id }}, fosforos: {{ (int) $jugador->fosforos }}, propio: {{ $propio ? 'true' : 'false' }}, prueba: {{ Js::from($prueba ?? null) }}, enUso: {{ Js::from($enUso ?? null) }}, rutas: {{ Js::from($rutas ?? null) }}, pestana: {{ Js::from($pestanaInicial ?? 'cartas') }}, titulos: {{ Js::from(collect($titulos ?? [])->map(fn ($t) => ['clave' => $t['clave'], 'nombre' => $t['nombre'], 'femenino' => $t['femenino']])->values()) }} })">
        {{-- Tu mesa. --}}
        <section aria-labelledby="titulo-perfil" class="perfil-mesa superficie-pano" data-mesa="{{ $propio ? $enUso['mesa'] : 'fabrica' }}" @if ($propio) :data-mesa="enUso.mesa" @endif>
            <div class="pagina perfil-mesa-grilla">
                {{-- El mazo, apilado con el dorso de quien mira, y la carta de este jugador, que sale de ahí al entrar. --}}
                <div class="perfil-naipes" aria-hidden="true">
                    <div class="perfil-mazo" x-ref="mazo">
                        @for ($apilada = 0; $apilada < 5; $apilada++)
                            <x-dorso />
                        @endfor
                    </div>
                    <div class="perfil-naipe" x-ref="carta">
                        <x-carta :palo="$suya['palo']" :numero="$suya['numero']" />
                    </div>
                </div>

                <div class="perfil-quien">
                    <h1 id="titulo-perfil" class="pagina-titulo">
                        {{ $jugador->apodo }}
                        @if ($propio)
                            {{-- El que usa, y los demás que ganó listos para cambiar sin recargar. --}}
                            @foreach ($distintivos as $opcion)
                                @if ($opcion['abierto'])
                                    <span @if (! $opcion['elegido']) x-cloak @endif x-show="enUso.distintivo === '{{ $opcion['clave'] }}'"><x-distintivo :de="$opcion['clave']" class="size-[0.8em]" /></span>
                                @endif
                            @endforeach
                        @else
                            <x-distintivo :de="$distintivo" class="size-[0.8em]" />
                        @endif
                    </h1>
                    <p class="perfil-lema"><span class="sr-only">Título: </span><span @if ($propio) x-text="tituloDe(enUso.titulo, enUso.femenino)" @endif>{{ $titulo }}</span></p>
                    <p class="sr-only">{{ $propio ? 'Tu carta' : 'Su carta' }}: {{ $nombreDe($carta) }}.</p>

                    <p class="perfil-rango">
                        <span>{{ $categoria->nombre() }}</span>
                        <span class="perfil-nivel">Nivel <strong>{{ $nivel }}</strong></span>
                        @if ($esDeEjemplo)
                            <span class="inline-flex items-center gap-1 self-center rounded-md bg-naipe px-1.5 py-0.5 text-xs font-bold text-tinta">
                                <x-icono nombre="bot" class="size-4" /> máquina
                            </span>
                        @endif
                    </p>

                    {{-- La tira del nivel. Los fósforos ganados desde la última visita caen al entrar (perfil.js). --}}
                    <div class="perfil-tira">
                        <x-tira :lleva="$enLaTira" modelo="tira" class="text-[1.9rem] sm:text-[2.2rem]" />
                        <p class="text-sm font-semibold leading-snug">
                            @if ($nivel < Camino::NIVELES)
                                {{ $enLaTira }} de {{ Camino::POR_NIVEL }} para el nivel {{ $nivel + 1 }}
                            @else
                                Llegó al último nivel
                            @endif
                        </p>
                    </div>

                    @if ($proximo !== [])
                        <p class="perfil-proximo">
                            En el {{ $nivel + 1 }}:
                            @foreach ($proximo as $i => $premio)
                                <strong>{{ mb_strtolower($tipos[$premio['tipo']]) }} {{ $premio['nombre'] }}</strong>{{ $premio['listo'] ? '' : ' (próximamente)' }}{{ $i < count($proximo) - 1 ? ' y' : '' }}
                            @endforeach
                        </p>
                    @endif

                    @if ($propio)
                        <form method="POST" action="{{ route('jugar') }}" class="mt-7">
                            @csrf
                            <button type="submit" class="boton boton-naipe"><x-icono nombre="repartir" /> Jugar una partida</button>
                        </form>
                    @endif
                </div>
            </div>
        </section>

        {{-- El índice: links quietos a cada sección. En el celular se desliza de costado. --}}
        <nav aria-label="En este perfil" class="perfil-indice">
            <ul class="pagina">
                @foreach ($secciones as $ancla => $nombre)
                    <li><a href="#{{ $ancla }}" class="enlace-nav">{{ $nombre }}</a></li>
                @endforeach
            </ul>
        </nav>

        {{-- Los números, como la planilla del club. --}}
        <section id="resumen" aria-labelledby="titulo-resumen" class="pagina perfil-seccion">
            <h2 id="titulo-resumen" class="seccion-titulo perfil-titulo">Resumen</h2>

            @if ($numeros['partidas'] === 0 && $numeros['torneos'] === 0 && $numeros['escalones'] === 0 && $numeros['desafios'] === 0)
                <p class="mt-6 text-lg font-semibold">{{ $propio ? 'Todavía no jugaste partidas que cuenten.' : 'Todavía no jugó partidas que cuenten.' }}</p>
            @else
                <dl class="planilla-numeros">
                    <div>
                        <dt>Partidas</dt>
                        <dd>{{ $numeros['partidas'] }}</dd>
                    </div>
                    <div>
                        <dt>Ganadas</dt>
                        <dd>{{ $numeros['ganadas'] }}@if ($numeros['porcentaje'] !== null)<small>{{ $numeros['porcentaje'] }} %</small>@endif</dd>
                    </div>
                    <div>
                        <dt>Racha</dt>
                        <dd>{{ $numeros['racha'] }}<small>la mejor, {{ $numeros['mejorRacha'] }}</small></dd>
                    </div>
                    <div>
                        <dt>Envidos ganados</dt>
                        <dd>{{ $numeros['envidos'] === null ? '–' : $numeros['envidos'].' %' }}</dd>
                    </div>
                    <div>
                        <dt>Torneos ganados</dt>
                        <dd>{{ $numeros['torneos'] }}</dd>
                    </div>
                    <div>
                        <dt>Escalera</dt>
                        <dd>{{ $numeros['escalones'] }}<small>de {{ $numeros['escalera'] }}</small></dd>
                    </div>
                    <div>
                        <dt>Desafíos</dt>
                        <dd>{{ $numeros['desafios'] }}<small>de {{ $numeros['desafiosEnTotal'] }}</small></dd>
                    </div>
                </dl>
                <p class="mt-3 text-sm">Partidas y ganadas son las que cuentan para el ranking.</p>
            @endif
        </section>

        {{--
            La libreta del club (M23): una libreta de socio en papel, con un sello de goma por cada escalón ganado,
            entintado del color de su tema. Una hoja por tema. Lo que falta es el círculo punteado donde va a ir el
            sello; un secreto que no se ganó no dice qué es. Es papel: no cambia con el modo de día y de noche.
        --}}
        <section id="libreta" aria-labelledby="titulo-libreta" class="pagina perfil-seccion" x-data="{ tema: {{ Js::from($temaInicial) }} }">
            <h2 id="titulo-libreta" class="seccion-titulo perfil-titulo">Libreta</h2>

            <div class="libreta mt-6">
                <div class="libreta-tapa">
                    <p class="libreta-socio">Libreta de socio <span class="tabular-nums">Nº {{ str_pad((string) $jugador->id, 4, '0', STR_PAD_LEFT) }}</span></p>
                    <p class="libreta-desde">{{ $jugador->apodo }}, socio desde {{ $jugador->created_at?->locale('es')->translatedFormat('F \d\e Y') }}</p>
                    <p class="libreta-cuenta"><span class="tabular-nums">{{ $sellosTiene }}</span> de {{ $sellosTotal }} sellos</p>
                </div>

                <div class="libreta-temas" role="tablist" aria-label="Temas de la libreta">
                    @foreach ($libreta as $clave => $tema)
                        <button type="button" role="tab" id="tema-{{ $clave }}" aria-controls="hoja-{{ $clave }}" class="enlace-nav libreta-tema"
                            :aria-selected="(tema === '{{ $clave }}').toString()" :tabindex="tema === '{{ $clave }}' ? 0 : -1" @click="tema = '{{ $clave }}'"
                            style="--tinta: {{ $tema['tinta'] }}; --tinta-noche: {{ $tema['noche'] }}">
                            {{ $tema['nombre'] }} <span class="libreta-tema-cuenta tabular-nums">{{ $tema['tiene'] }}/{{ $tema['total'] }}</span>
                        </button>
                    @endforeach
                </div>

                @foreach ($libreta as $clave => $tema)
                    <ol id="hoja-{{ $clave }}" role="tabpanel" aria-labelledby="tema-{{ $clave }}" class="libreta-hoja" x-show="tema === '{{ $clave }}'" @if ($clave !== $temaInicial) x-cloak @endif>
                        @foreach ($tema['logros'] as $logro)
                            <li @class(['libreta-logro', 'es-nuevo' => $logro['nuevo'] && $propio])>
                                <x-sello :logro="$logro['clave']" :escalon="$logro['escalon']" :fecha="$logro['fecha']" :secreto="$logro['secreto']" class="libreta-sello" />
                                <div>
                                    <p class="font-black leading-tight">{{ $logro['nombre'] }}@if ($logro['nuevo'] && $propio) <span class="libreta-nuevo">Nuevo</span>@endif</p>
                                    <p class="mt-0.5 text-sm leading-snug">{{ $logro['pide'] }}</p>
                                    @unless ($logro['secreto'])
                                        <p class="mt-1.5 flex items-center gap-2 text-sm">
                                            @if ($logro['escalones'] > 1)
                                                <span class="libreta-escalones" aria-hidden="true">
                                                    @for ($k = 1; $k <= $logro['escalones']; $k++)
                                                        <span @class(['es-ganado' => $k <= $logro['escalon']])></span>
                                                    @endfor
                                                </span>
                                            @endif
                                            <span class="font-semibold tabular-nums">{{ $logro['completo'] ? 'Completo' : min($logro['lleva'], $logro['meta']).' de '.$logro['meta'] }}</span>
                                        </p>
                                        @if ($logro['titulo'])
                                            <p class="mt-0.5 text-sm">Da el título {{ $logro['titulo'] }}{{ $logro['escalones'] > 1 ? ' en el escalón '.$logro['tituloEn'] : '' }}.</p>
                                        @endif
                                        <p class="mt-0.5 text-xs">{{ $logro['rareza'] > 0 ? 'Lo tiene el '.$logro['rareza'].' % de los socios.' : 'Todavía no lo tiene ningún socio.' }}</p>
                                    @endunless
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endforeach
            </div>
        </section>

        @if ($propio)
            {{-- La Colección: lo que se usa en la mesa, sobre el paño. Tocar una pieza la prueba; "Usar esta" la elige. --}}
            <section id="coleccion" aria-labelledby="titulo-coleccion" class="perfil-coleccion superficie-pano">
                <div class="pagina">
                    <h2 id="titulo-coleccion" class="seccion-titulo perfil-titulo">Colección</h2>
                    <p class="mt-4 text-lg">Tocá una pieza para probarla en la mesa. Lo punteado todavía no está dibujado.</p>

                    @if ($loTieneTodo)
                        <p class="mt-2 font-bold">Sos la administración: tenés todo abierto para probarlo. Tu nivel sale de tus partidas, como el de todos.</p>
                    @endif

                    @error('carta')
                        <p role="alert" class="mt-4 inline-flex rounded-[0.625rem] bg-copa px-4 py-2.5 font-bold leading-snug text-naipe">{{ $message }}</p>
                    @enderror
                    @error('dorso')
                        <p role="alert" class="mt-4 inline-flex rounded-[0.625rem] bg-copa px-4 py-2.5 font-bold leading-snug text-naipe">{{ $message }}</p>
                    @enderror

                    <div class="coleccion-grilla">
                        <div class="min-w-0">
                            <div role="tablist" aria-label="Qué mirar" class="coleccion-pestanas">
                                @foreach ($pestanas as $clave => $nombre)
                                    <button type="button" role="tab" id="pestana-{{ $clave }}" class="pestana" aria-controls="piezas-{{ $clave }}"
                                        :aria-selected="(pestana === '{{ $clave }}').toString()" aria-selected="{{ $clave === $pestanaInicial ? 'true' : 'false' }}"
                                        @click="pestana = '{{ $clave }}'">{{ $nombre }}</button>
                                @endforeach
                            </div>

                            {{-- Solo se dibuja la pestaña abierta. --}}
                            <template x-if="pestana === 'cartas'">
                                <ol id="piezas-cartas" role="tabpanel" aria-labelledby="pestana-cartas" class="coleccion-piezas">
                                    @foreach ($camino as $paso)
                                        @php($nombre = ucfirst($nombreDe($paso['carta'])))
                                        <li>
                                            <button type="button" class="naipe-boton" :aria-pressed="(prueba.tipo === 'carta' && prueba.clave === '{{ $paso['carta'] }}').toString()"
                                                aria-label="{{ $nombre }}{{ $paso['elegida'] ? ', en uso' : ($paso['abierta'] ? ', probar' : ', llega en el nivel '.$paso['nivel']) }}"
                                                :aria-label="'{{ $nombre }}' + (enUso.carta === '{{ $paso['carta'] }}' ? ', en uso' : '{{ $paso['abierta'] ? ', probar' : ', llega en el nivel '.$paso['nivel'] }}')"
                                                @click="probar($el, {{ Js::from(['tipo' => 'carta', 'clave' => $paso['carta'], 'nombre' => $nombre, 'abierta' => $paso['abierta'], 'elegida' => $paso['elegida'], 'pide' => 'Llega en el nivel '.$paso['nivel'].'.']) }})">
                                                <span @class(['block', 'pieza-cerrada' => ! $paso['abierta']])><x-carta :palo="$partes($paso['carta'])['palo']" :numero="$partes($paso['carta'])['numero']" /></span>
                                            </button>
                                            <p class="pieza-rotulo" x-text="enUso.carta === '{{ $paso['carta'] }}' ? 'En uso' : '{{ $paso['abierta'] ? '' : 'Nivel '.$paso['nivel'] }}'"></p>
                                        </li>
                                    @endforeach
                                </ol>
                            </template>

                            <template x-if="pestana === 'dorsos'">
                                <ol id="piezas-dorsos" role="tabpanel" aria-labelledby="pestana-dorsos" class="coleccion-piezas">
                                    @foreach ($dorsos as $opcion)
                                        @php($pide = isset($opcion['escalon']) ? 'Se gana en el escalón '.$opcion['escalon'].' de la escalera.' : 'Llega en el nivel '.$opcion['nivel'].'.')
                                        <li>
                                            <button type="button" class="naipe-boton" :aria-pressed="(prueba.tipo === 'dorso' && prueba.clave === '{{ $opcion['clave'] }}').toString()"
                                                aria-label="Dorso {{ $opcion['nombre'] }}{{ $opcion['elegido'] ? ', en uso' : ($opcion['abierto'] ? ', probar' : '') }}"
                                                :aria-label="'Dorso {{ $opcion['nombre'] }}' + (enUso.dorso === '{{ $opcion['clave'] }}' ? ', en uso' : '{{ $opcion['abierto'] ? ', probar' : '' }}')"
                                                @click="probar($el, {{ Js::from(['tipo' => 'dorso', 'clave' => $opcion['clave'], 'nombre' => 'Dorso '.$opcion['nombre'], 'abierta' => $opcion['abierto'], 'elegida' => $opcion['elegido'], 'pide' => $pide]) }})">
                                                <span @class(['block', 'pieza-cerrada' => ! $opcion['abierto']])><x-dorso :de="$opcion['clave']" /></span>
                                            </button>
                                            <p class="pieza-rotulo">
                                                {{ $opcion['nombre'] }}
                                                <span class="block font-semibold" x-text="enUso.dorso === '{{ $opcion['clave'] }}' ? 'En uso' : '{{ $opcion['abierto'] ? '' : (isset($opcion['escalon']) ? 'Escalón '.$opcion['escalon'] : 'Nivel '.$opcion['nivel']) }}'"></span>
                                            </p>
                                        </li>
                                    @endforeach
                                    @foreach ($deTipo('dorso') as $ficha)
                                        <li>
                                            <div class="pieza-proxima" role="img" aria-label="Dorso {{ $ficha['nombre'] }}, próximamente, llega en el nivel {{ $ficha['nivel'] }}"></div>
                                            <p class="pieza-rotulo">{{ $ficha['nombre'] }}<span class="block font-semibold">Nivel {{ $ficha['nivel'] }}</span></p>
                                        </li>
                                    @endforeach
                                </ol>
                            </template>

                            {{-- Las mesas: cada una en chico, con su color y su dibujo. --}}
                            <template x-if="pestana === 'mesas'">
                                <ol id="piezas-mesas" role="tabpanel" aria-labelledby="pestana-mesas" class="coleccion-piezas">
                                    @foreach ($mesas as $opcion)
                                        <li>
                                            <button type="button" class="naipe-boton" :aria-pressed="(prueba.tipo === 'mesa' && prueba.clave === '{{ $opcion['clave'] }}').toString()"
                                                aria-label="Mesa {{ $opcion['nombre'] }}{{ $opcion['elegido'] ? ', en uso' : ($opcion['abierto'] ? ', probar' : ', llega en el nivel '.$opcion['nivel']) }}"
                                                @click="probar($el, {{ Js::from(['tipo' => 'mesa', 'clave' => $opcion['clave'], 'nombre' => 'Mesa '.$opcion['nombre'], 'abierta' => $opcion['abierto'], 'pide' => 'Llega en el nivel '.$opcion['nivel'].'.']) }})">
                                                <span @class(['block', 'pieza-cerrada' => ! $opcion['abierto']])><span class="pieza-mesa" data-mesa="{{ $opcion['clave'] }}" aria-hidden="true"></span></span>
                                            </button>
                                            <p class="pieza-rotulo">
                                                {{ $opcion['nombre'] }}
                                                <span class="block font-semibold" x-text="enUso.mesa === '{{ $opcion['clave'] }}' ? 'En uso' : '{{ $opcion['abierto'] ? '' : 'Nivel '.$opcion['nivel'] }}'"></span>
                                            </p>
                                        </li>
                                    @endforeach
                                    @foreach ($deTipo('mesa') as $ficha)
                                        <li>
                                            <div class="pieza-proxima" role="img" aria-label="Mesa {{ $ficha['nombre'] }}, próximamente, llega en el nivel {{ $ficha['nivel'] }}"></div>
                                            <p class="pieza-rotulo">{{ $ficha['nombre'] }}<span class="block font-semibold">Nivel {{ $ficha['nivel'] }}</span></p>
                                        </li>
                                    @endforeach
                                </ol>
                            </template>

                            <template x-if="pestana === 'mazos'">
                                <ol id="piezas-mazos" role="tabpanel" aria-labelledby="pestana-mazos" class="coleccion-piezas coleccion-mazos">
                                    @foreach ($mazos as $opcion)
                                        <li>
                                            <button type="button" class="naipe-boton" :aria-pressed="(prueba.tipo === 'mazo' && prueba.clave === '{{ $opcion['clave'] }}').toString()"
                                                aria-label="Mazo {{ $opcion['nombre'] }}{{ $opcion['elegido'] ? ', en uso' : ($opcion['abierto'] ? ', probar' : ', llega en el nivel '.$opcion['nivel']) }}"
                                                @click="probar($el, {{ Js::from(['tipo' => 'mazo', 'clave' => $opcion['clave'], 'nombre' => 'Mazo '.$opcion['nombre'], 'abierta' => $opcion['abierto'], 'pide' => 'Llega en el nivel '.$opcion['nivel'].'.']) }})">
                                                <span @class(['pieza-mazo', 'pieza-cerrada' => ! $opcion['abierto']]) aria-hidden="true">
                                                    @foreach ($manoDelMazo($opcion['clave']) as [$palo, $numero])
                                                        <x-carta :palo="$palo" :numero="$numero" :mazo="$opcion['clave']" />
                                                    @endforeach
                                                </span>
                                            </button>
                                            <p class="pieza-rotulo">
                                                {{ $opcion['nombre'] }}
                                                <span class="block font-semibold" x-text="enUso.mazo === '{{ $opcion['clave'] }}' ? 'En uso' : '{{ $opcion['abierto'] ? '' : 'Nivel '.$opcion['nivel'] }}'"></span>
                                            </p>
                                        </li>
                                    @endforeach
                                    @foreach ($deTipo('mazo') as $ficha)
                                        <li>
                                            <div class="pieza-proxima" role="img" aria-label="{{ $ficha['nombre'] }}, próximamente, llega en el nivel {{ $ficha['nivel'] }}"></div>
                                            <p class="pieza-rotulo">{{ $ficha['nombre'] }}<span class="block font-semibold">Nivel {{ $ficha['nivel'] }}</span></p>
                                        </li>
                                    @endforeach
                                </ol>
                            </template>

                            {{-- Cómo anotás: un grupo de cinco con cada forma, sobre su superficie. --}}
                            <template x-if="pestana === 'anotar'">
                                <ol id="piezas-anotar" role="tabpanel" aria-labelledby="pestana-anotar" class="coleccion-piezas">
                                    @foreach ($formas as $opcion)
                                        <li>
                                            <button type="button" class="naipe-boton" :aria-pressed="(prueba.tipo === 'anotar' && prueba.clave === '{{ $opcion['clave'] }}').toString()"
                                                aria-label="Anotar con {{ mb_strtolower($opcion['nombre']) }}{{ $opcion['elegido'] ? ', en uso' : ($opcion['abierto'] ? ', probar' : ', llega en el nivel '.$opcion['nivel']) }}"
                                                @click="probar($el, {{ Js::from(['tipo' => 'anotar', 'clave' => $opcion['clave'], 'nombre' => $opcion['nombre'], 'abierta' => $opcion['abierto'], 'pide' => 'Llega en el nivel '.$opcion['nivel'].'.']) }})">
                                                <span @class(['pieza-anotar', 'pieza-cerrada' => ! $opcion['abierto']]) data-anotar="{{ $opcion['clave'] }}"><x-fosforos :grupo="0" :puntos="5" :forma="$opcion['clave']" /></span>
                                            </button>
                                            <p class="pieza-rotulo">
                                                {{ $opcion['nombre'] }}
                                                <span class="block font-semibold" x-text="enUso.anotar === '{{ $opcion['clave'] }}' ? 'En uso' : '{{ $opcion['abierto'] ? '' : 'Nivel '.$opcion['nivel'] }}'"></span>
                                            </p>
                                        </li>
                                    @endforeach
                                    @foreach ($deTipo('anotar') as $ficha)
                                        <li>
                                            <div class="pieza-proxima pieza-proxima-cuadrada" role="img" aria-label="{{ $ficha['nombre'] }}, próximamente, llega en el nivel {{ $ficha['nivel'] }}"></div>
                                            <p class="pieza-rotulo">{{ $ficha['nombre'] }}<span class="block font-semibold">Nivel {{ $ficha['nivel'] }}</span></p>
                                        </li>
                                    @endforeach
                                </ol>
                            </template>

                            {{-- Los títulos: se ganan haciendo cosas en la mesa, y cada uno elige cómo se lee el suyo. --}}
                            <template x-if="pestana === 'titulos'">
                                <ol id="piezas-titulos" role="tabpanel" aria-labelledby="pestana-titulos" class="coleccion-titulos">
                                    @foreach ($titulos as $opcion)
                                        <li>
                                            <button type="button" class="titulo-boton" :aria-pressed="(prueba.tipo === 'titulo' && prueba.clave === '{{ $opcion['clave'] }}').toString()"
                                                @click="probar($el, {{ Js::from(['tipo' => 'titulo', 'clave' => $opcion['clave'], 'nombre' => $opcion['nombre'], 'abierta' => $opcion['abierto'], 'pide' => 'Pide '.$opcion['pide'].'.']) }})">
                                                <span class="titulo-nombre">{{ $opcion['nombre'] === $opcion['femenino'] ? $opcion['nombre'] : $opcion['nombre'].' o '.mb_strtolower($opcion['femenino']) }}</span>
                                                <span class="titulo-pide">{{ $opcion['pide'] }}</span>
                                                <span class="titulo-estado" x-text="enUso.titulo === '{{ $opcion['clave'] }}' ? 'En uso' : '{{ $opcion['abierto'] ? 'Ganado' : 'Llevás '.min($opcion['lleva'], $opcion['hace']).' de '.$opcion['hace'] }}'"></span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ol>
                            </template>

                            {{-- Los distintivos: el sello que va al lado del apodo. También se puede no llevar ninguno. --}}
                            <template x-if="pestana === 'distintivos'">
                                <ol id="piezas-distintivos" role="tabpanel" aria-labelledby="pestana-distintivos" class="coleccion-piezas">
                                    <li>
                                        <button type="button" class="naipe-boton" :aria-pressed="(prueba.tipo === 'distintivo' && prueba.clave === 'ninguno').toString()" aria-label="Sin distintivo"
                                            @click="probar($el, {{ Js::from(['tipo' => 'distintivo', 'clave' => 'ninguno', 'nombre' => 'Sin distintivo', 'abierta' => true, 'pide' => '']) }})">
                                            <span class="pieza-distintivo" aria-hidden="true"></span>
                                        </button>
                                        <p class="pieza-rotulo">Ninguno<span class="block font-semibold" x-text="enUso.distintivo === 'ninguno' ? 'En uso' : ''"></span></p>
                                    </li>
                                    @foreach ($distintivos as $opcion)
                                        <li>
                                            <button type="button" class="naipe-boton" :aria-pressed="(prueba.tipo === 'distintivo' && prueba.clave === '{{ $opcion['clave'] }}').toString()"
                                                aria-label="Distintivo {{ $opcion['nombre'] }}{{ $opcion['elegido'] ? ', en uso' : ($opcion['abierto'] ? ', probar' : ', llega en el nivel '.$opcion['nivel']) }}"
                                                @click="probar($el, {{ Js::from(['tipo' => 'distintivo', 'clave' => $opcion['clave'], 'nombre' => $opcion['nombre'], 'abierta' => $opcion['abierto'], 'pide' => 'Llega en el nivel '.$opcion['nivel'].'.']) }})">
                                                <span @class(['pieza-distintivo', 'pieza-cerrada' => ! $opcion['abierto']])><x-distintivo :de="$opcion['clave']" class="size-[2.2rem]" /></span>
                                            </button>
                                            <p class="pieza-rotulo">
                                                {{ $opcion['nombre'] }}
                                                <span class="block font-semibold" x-text="enUso.distintivo === '{{ $opcion['clave'] }}' ? 'En uso' : '{{ $opcion['abierto'] ? '' : 'Nivel '.$opcion['nivel'] }}'"></span>
                                            </p>
                                        </li>
                                    @endforeach
                                    @foreach ($deTipo('distintivo') as $ficha)
                                        <li>
                                            <div class="pieza-proxima pieza-proxima-cuadrada" role="img" aria-label="Distintivo {{ $ficha['nombre'] }}, próximamente, llega en el nivel {{ $ficha['nivel'] }}"></div>
                                            <p class="pieza-rotulo">{{ $ficha['nombre'] }}<span class="block font-semibold">Nivel {{ $ficha['nivel'] }}</span></p>
                                        </li>
                                    @endforeach
                                </ol>
                            </template>
                        </div>

                        {{--
                            El probador: la pieza armada sobre la mesa antes de elegirla. Una carta se ve de frente; un dorso,
                            con la carta dada vuelta. "Usar esta" la elige; lo que falta dice cuándo llega.
                        --}}
                        <aside class="probador" aria-label="Probador">
                            <div class="probador-mesa" data-mesa="{{ $enUso['mesa'] }}" :data-mesa="prueba.tipo === 'mesa' ? prueba.clave : enUso.mesa">
                                <div class="probador-naipe" x-show="['carta', 'dorso', 'mesa'].includes(prueba.tipo)">
                                    <span class="giro block" data-vuelta="{{ $muestraElDorso ? 'true' : 'false' }}" :data-vuelta="(prueba.tipo === 'dorso').toString()">
                                        <span class="block" x-ref="cara"><x-carta :palo="$suya['palo']" :numero="$suya['numero']" /></span>
                                        <span class="giro-cara block" x-ref="dorso"><x-dorso :de="$dorso" /></span>
                                    </span>
                                </div>

                                {{-- Un mazo se prueba con tres cartas suyas abiertas en la mano: un as, un número y una figura. --}}
                                <div class="probador-mazo" x-show="prueba.tipo === 'mazo'" x-cloak>
                                    @foreach ($mazos as $opcion)
                                        <div class="probador-abanico" x-show="prueba.clave === '{{ $opcion['clave'] }}'">
                                            @foreach ($manoDelMazo($opcion['clave']) as [$palo, $numero])
                                                <x-carta :palo="$palo" :numero="$numero" :mazo="$opcion['clave']" />
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Una forma de anotar se prueba en un tanteador de verdad, con un tanteo a la mitad. --}}
                                <div class="probador-tanteo" x-show="prueba.tipo === 'anotar'" x-cloak>
                                    @foreach ($formas as $opcion)
                                        <div x-show="prueba.clave === '{{ $opcion['clave'] }}'">
                                            <x-tanteador nombre="Vos" :puntos="17" :forma="$opcion['clave']" class="rounded-md px-3 py-2.5" />
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Un título o un distintivo se prueban junto al apodo, como los van a ver los demás. --}}
                                <div class="probador-firma" x-show="['titulo', 'distintivo'].includes(prueba.tipo)" x-cloak>
                                    <p class="text-2xl font-black leading-tight [overflow-wrap:anywhere]">
                                        {{ $jugador->apodo }}
                                        @foreach ($distintivos as $opcion)
                                            <span x-show="(prueba.tipo === 'distintivo' ? prueba.clave : enUso.distintivo) === '{{ $opcion['clave'] }}'"><x-distintivo :de="$opcion['clave']" class="size-[0.85em]" /></span>
                                        @endforeach
                                    </p>
                                    <p class="perfil-lema mt-1" x-text="prueba.tipo === 'titulo' ? tituloDe(prueba.clave, femenino) : tituloDe(enUso.titulo, enUso.femenino)"></p>
                                </div>
                            </div>

                            <div class="probador-pie" aria-live="polite">
                                <p class="text-xl font-extrabold leading-tight" x-text="prueba.tipo === 'titulo' ? tituloDe(prueba.clave, femenino) : prueba.nombre">{{ $prueba['nombre'] }}</p>
                                <p class="mt-1 min-h-[1lh] font-semibold" x-text="esLaQueUsa ? 'Es lo que usás.' : (prueba.abierta ? 'Es tuyo.' : prueba.pide)">Es la que usás.</p>

                                {{-- Cómo se lee el título: lo elige cada uno. --}}
                                <div class="mt-3 flex gap-2" role="group" aria-label="Cómo se lee" x-show="prueba.tipo === 'titulo' && prueba.abierta && tituloDe(prueba.clave, false) !== tituloDe(prueba.clave, true)" x-cloak>
                                    <button type="button" class="pestana" :aria-pressed="(! femenino).toString()" @click="femenino = false" x-text="tituloDe(prueba.clave, false)"></button>
                                    <button type="button" class="pestana" :aria-pressed="femenino.toString()" @click="femenino = true" x-text="tituloDe(prueba.clave, true)"></button>
                                </div>

                                <form method="POST" :action="rutas[prueba.tipo]" class="mt-4"
                                    x-show="prueba.abierta && ! esLaQueUsa" x-cloak @submit.prevent="usar()">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" :name="prueba.tipo" :value="prueba.clave">
                                    <input type="hidden" name="femenino" :value="femenino ? 1 : 0" :disabled="prueba.tipo !== 'titulo'">
                                    <button type="submit" class="boton boton-naipe" :disabled="guardando" x-text="textoDeUsar">Usar esta carta</button>
                                </form>
                            </div>
                        </aside>
                    </div>
                </div>
            </section>

            {{-- El camino: unos niveles antes y después del tuyo, y el camino entero a un toque. --}}
            <section id="camino" aria-labelledby="titulo-camino" class="pagina perfil-seccion">
                <h2 id="titulo-camino" class="seccion-titulo perfil-titulo">El camino</h2>
                <p class="mt-4 text-lg">Diez fósforos por nivel. Ganar anota tres; perder, uno.</p>

                <ol class="camino-cerca">
                    @foreach ($cerca as $numero => $premios)
                        <li @class(['camino-paso', 'es-pasado' => $numero <= $nivel, 'es-proximo' => $numero === $nivel + 1])>
                            <p class="camino-numero"><span class="sr-only">Nivel</span> {{ $numero }}</p>
                            <p class="camino-estado">{{ $numero <= $nivel ? 'Tuyo' : ($numero === $nivel + 1 ? 'El que sigue' : '') }}</p>
                            <ul class="camino-premios">
                                @foreach ($premios as $premio)
                                    <li>
                                        <span class="block text-sm">{{ $tipos[$premio['tipo']] }}</span>
                                        <span class="block font-bold leading-tight">{{ $premio['nombre'] }}</span>
                                        @unless ($premio['listo'])
                                            <span class="block text-sm">Próximamente</span>
                                        @endunless
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ol>

                <x-pagina.desplegable titulo="Ver el camino entero">
                    <table class="tabla">
                        <caption class="sr-only">Los cuarenta niveles y lo que destraba cada uno</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="w-16">Nivel</th>
                                <th scope="col">Premios</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($niveles as $numero => $premios)
                                <tr @class(['font-bold' => $numero === $nivel])>
                                    <td class="text-xl font-black tabular-nums">{{ $numero }}</td>
                                    <td>
                                        @foreach ($premios as $i => $premio)
                                            {{ $tipos[$premio['tipo']] }} {{ $premio['nombre'] }}{{ $premio['listo'] ? '' : ' (próximamente)' }}{{ $i < count($premios) - 1 ? ',' : '' }}
                                        @endforeach
                                        @if ($numero <= $nivel)
                                            <span class="text-sm font-semibold">, tuyo</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-pagina.desplegable>
            </section>
        @endif

        {{-- Las últimas partidas. --}}
        <section id="partidas" aria-labelledby="titulo-partidas" class="pagina perfil-seccion">
            <h2 id="titulo-partidas" class="seccion-titulo perfil-titulo">Últimas partidas</h2>

            @if ($ultimas === [])
                <p class="mt-6 text-lg font-semibold">{{ $propio ? 'Todavía no terminaste ninguna partida.' : 'Todavía no terminó ninguna partida.' }}</p>
            @else
                <ol class="perfil-partidas">
                    @foreach ($ultimas as $partida)
                        <li>
                            <p @class(['font-bold', 'text-gana' => $partida['gano'], 'text-pierde' => ! $partida['gano']])>
                                {{ $partida['esDesafio'] ? ($partida['gano'] ? 'Superado' : 'No salió') : ($propio ? ($partida['gano'] ? 'Ganaste' : 'Perdiste') : ($partida['gano'] ? 'Ganó' : 'Perdió')) }}
                            </p>
                            <p class="text-2xl font-black tabular-nums leading-none">{{ $partida['vos'] }} a {{ $partida['ellos'] }}</p>
                            <p class="min-w-0 leading-snug"><span class="font-bold">Contra {{ $partida['rival'] }}</span> <span class="block text-sm">{{ $partida['dia'] }}, {{ $partida['hora'] }}</span></p>
                            @if ($propio)
                                <a href="{{ route('historial.ver', $partida['id']) }}" class="enlace-nav font-bold">Ver de nuevo</a>
                            @endif
                        </li>
                    @endforeach
                </ol>

                @if ($propio)
                    <p class="mt-5"><a href="{{ route('historial') }}" class="enlace-nav font-bold">Ver el historial</a></p>
                @endif
            @endif
        </section>

        @if ($propio)
            {{-- La cuenta, compacta, al final: lo que se declara y lo que se escribe. --}}
            <section id="cuenta" aria-labelledby="titulo-cuenta-entera" class="pagina perfil-seccion">
                <h2 id="titulo-cuenta-entera" class="seccion-titulo perfil-titulo">Tu cuenta</h2>

                <div class="mt-8 grid gap-x-16 gap-y-12 lg:grid-cols-2">
                    <section aria-labelledby="titulo-experiencia">
                        <h3 id="titulo-experiencia" class="text-xl font-extrabold">¿Cuánto truco sabés?</h3>
                        <p class="mt-2 max-w-[46ch] leading-relaxed">Cambia contra qué nivel de la máquina entrás si no elegís y qué ayudas ves. Nada más.</p>

                        <form method="POST" action="{{ route('perfil.experiencia') }}" class="mt-5 flex flex-col">
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
                        <h3 id="titulo-cuenta" class="text-xl font-extrabold">Apodo y sesión</h3>

                        <form method="POST" action="{{ route('perfil') }}" class="mt-5 flex max-w-[29rem] flex-col gap-6">
                            @csrf
                            @method('PUT')

                            <x-campo nombre="apodo" rotulo="Apodo" ayuda="De 3 a 20 caracteres. Es el nombre que ve tu rival."
                                :valor="$jugador->apodo" x-model="apodo" maxlength="20" autocomplete="nickname" autocapitalize="words" required />

                            <div>
                                <x-boton-enviar texto="Guardar apodo" enviando="Guardando…" class="boton-tinta" />
                            </div>
                        </form>

                        <dl class="mt-8 max-w-[29rem] border-t border-current/15 pt-5">
                            <dt class="font-bold">Email</dt>
                            <dd class="mt-0.5 break-words">{{ $jugador->email }}</dd>
                        </dl>

                        <form method="POST" action="{{ route('salir') }}" class="mt-6">
                            @csrf
                            <button type="submit" class="boton boton-linea">
                                <x-icono nombre="salir" /> Cerrar sesión
                            </button>
                        </form>
                    </section>
                </div>
            </section>
        @endif
    </div>
</x-layouts.base>
