@php
    $total = count($escalones);
    $completa = $actual > $total;
    // Entra elegido el escalón que toca; con la escalera completa, el último.
    $elegido = min($actual, $total);
    $enCurso ??= null;

    // En qué está cada escalón: ya pasado, el que toca, o todavía cerrado.
    $estadoDe = fn (int $numero) => match (true) {
        $numero < $actual => 'pasado',
        $numero === $actual => 'toca',
        default => 'cerrado',
    };
    $rotulo = ['pasado' => 'Pasado', 'toca' => 'Te toca', 'cerrado' => 'Cerrado'];
@endphp

{{--
    La escalera del club: ocho naipes apoyados en el paño, subiendo en diagonal. Los escalones abiertos
    están boca arriba (la carta del rival, o la de una mano con objetivo) y los que faltan, boca abajo.
    Los rivales suben por la jerarquía del truco: del cuatro de espada al ancho de espada.
    Tocar un naipe muestra su escalón al lado; no se juega nada hasta apretar el botón.
--}}
<x-layouts.base titulo="Escalera del club" descripcion="Ocho escalones en orden: partidas cortas contra rivales cada vez más difíciles y manos con objetivo. Pasás uno y se abre el siguiente." superficie="pano">
    <div class="overflow-x-clip">
        {{--
            En el celular va de arriba hacia abajo: el título, los naipes y el escalón elegido con su botón, que
            entra en la primera pantalla. En pantallas anchas el texto va a la izquierda y los naipes a la derecha.
        --}}
        <div class="mx-auto grid max-w-6xl gap-y-5 px-5 pb-16 pt-4 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)] lg:grid-rows-[auto_1fr] lg:gap-x-14 lg:gap-y-8 lg:pb-24 lg:pt-8"
            x-data="{ elegido: {{ $elegido }}, eligio: false }">
            <header class="lg:col-start-1 lg:row-start-1">
                <h1 class="pagina-titulo">Escalera del club</h1>
                <p class="pagina-bajada">
                    {{ $completa ? 'Llegaste arriba: jugá el que quieras.' : 'Pasás un escalón y se abre el siguiente.' }}
                </p>

                @if ($enCurso)
                    <p class="mt-3 max-w-[40ch] font-bold leading-snug">
                        Tenés {{ $enCurso->esDePractica() ? ($enCurso->escalon ? 'un escalón' : 'un desafío') : 'una partida' }} sin terminar: para jugar otro escalón, primero terminalo o abandonalo desde la mesa.
                    </p>
                @endif
            </header>

            <div class="relative lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:self-end">
                {{-- Cuántos van, contados como se cuenta en el club. Va en el rincón que la escalera deja libre: no le agrega un renglón a la pantalla. --}}
                <p class="sobre-pano absolute left-0 top-0 flex items-center gap-2.5 rounded-xl bg-pano-hondo px-3.5 py-2.5 font-semibold leading-none">
                    <span class="text-2xl font-black tabular-nums text-oro sm:text-3xl">{{ $pasados }}</span>
                    <span>de {{ $total }}<span class="sr-only"> escalones pasados</span></span>
                    <x-administracion.cuenta :cantidad="$pasados" class="text-[1.05rem]" />
                </p>

                {{-- Los naipes. El reparto es el momento coreografiado de la pantalla: llegan de a uno y los abiertos se dan vuelta. --}}
                <ol class="escalera" style="--escalones: {{ $total }}" aria-label="Escalones">
                    @foreach ($escalones as $i => $escalon)
                        @php
                            $estado = $estadoDe($escalon['numero']);
                            $carta = $escalon['carta'] ? explode('-', $escalon['carta']) : null;
                        @endphp
                        <li style="--i: {{ $i }}">
                            <button type="button" class="escalon" data-estado="{{ $estado }}"
                                aria-label="Escalón {{ $escalon['numero'] }}, {{ mb_strtolower($rotulo[$estado]) }}{{ $estado === 'cerrado' ? '' : ': '.$escalon['titulo'] }}"
                                aria-pressed="{{ $escalon['numero'] === $elegido ? 'true' : 'false' }}"
                                :aria-pressed="(elegido === {{ $escalon['numero'] }}).toString()"
                                @click="eligio = eligio || elegido !== {{ $escalon['numero'] }}; elegido = {{ $escalon['numero'] }}">
                                <span class="se-reparte block" style="--orden: {{ $i }}">
                                    @if ($estado === 'cerrado')
                                        <x-dorso />
                                    @else
                                        {{-- Llega boca abajo como las demás y se da vuelta: está abierto. --}}
                                        <span class="se-da-vuelta" style="animation-delay: {{ 520 + $i * 90 }}ms">
                                            @if ($carta)
                                                <x-carta :palo="$carta[1]" :numero="(int) $carta[0]" />
                                            @else
                                                <x-carta-modo icono="desafio" :nombre="$escalon['titulo']" :renglones="['Mano']" />
                                            @endif
                                            <x-dorso class="reverso" />
                                        </span>
                                    @endif
                                </span>
                                <span class="escalon-numero" aria-hidden="true">{{ $escalon['numero'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ol>
            </div>

            {{-- El escalón elegido: qué es, en qué está y, si está abierto, el botón para jugarlo. --}}
            <div class="lg:col-start-1 lg:row-start-2">
                    @foreach ($escalones as $escalon)
                        @php($estado = $estadoDe($escalon['numero']))
                        <section aria-labelledby="escalon-{{ $escalon['numero'] }}" x-show="elegido === {{ $escalon['numero'] }}" @if ($escalon['numero'] !== $elegido) x-cloak @endif
                            :class="{ 'detalle-entra': eligio }">
                            <p class="flex items-center gap-3 font-semibold">
                                Escalón {{ $escalon['numero'] }}
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-naipe/40 px-2.5 py-0.5 text-sm">
                                    @if ($estado === 'pasado')
                                        <x-icono nombre="quiero" class="size-4" />
                                    @elseif ($estado === 'toca')
                                        <x-icono nombre="mano" class="size-4" />
                                    @endif
                                    {{ $rotulo[$estado] }}
                                </span>
                            </p>

                            @if ($estado === 'cerrado')
                                <h2 id="escalon-{{ $escalon['numero'] }}" class="mt-2 text-3xl font-black leading-[1.02] tracking-tight sm:text-4xl">Boca abajo</h2>
                                <p class="mt-2 max-w-[36ch] leading-snug sm:text-lg sm:leading-relaxed">Se da vuelta cuando pases el escalón {{ $escalon['numero'] - 1 }}.</p>
                            @else
                                <h2 id="escalon-{{ $escalon['numero'] }}" class="mt-2 text-3xl font-black leading-[1.02] tracking-tight sm:text-4xl">{{ $escalon['titulo'] }}</h2>
                                <p class="mt-2 max-w-[36ch] leading-snug sm:text-lg sm:leading-relaxed">{{ $escalon['detalle'] }}</p>

                                <div class="mt-4 sm:mt-5">
                                    @if ($enCurso)
                                        <a href="{{ route('mesa') }}" class="boton boton-naipe min-h-14 px-6 text-lg"><x-icono nombre="repartir" /> Seguir en la mesa</a>
                                    @else
                                        <form method="POST" action="{{ route('escalera.jugar', $escalon['numero']) }}">
                                            @csrf
                                            <button type="submit" @class(['boton min-h-14 px-6 text-lg', $estado === 'toca' ? 'boton-naipe' : 'boton-linea'])>
                                                <x-icono nombre="escalera" /> {{ $estado === 'toca' ? 'Jugar el escalón' : 'Jugarlo de nuevo' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </section>
                    @endforeach
            </div>
        </div>
    </div>
</x-layouts.base>
