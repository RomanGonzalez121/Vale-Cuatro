@php
    // Agrupados por tema, en el orden en que aparecen en el catálogo.
    $porTema = collect($desafios)->groupBy('tema');
    $total = count($desafios);
    // Con una partida sin terminar no se abre otra: los botones llevan a esa.
    $enCurso ??= null;
@endphp

{{--
    Los desafíos sueltos. Cada uno se muestra con lo que lo distingue: la mano que te toca, con las
    cartas de verdad. Están todos abiertos y se juegan en cualquier orden.
--}}
<x-layouts.base titulo="Desafíos" descripcion="Manos de truco armadas con un objetivo: ganar el envido, hacer que la máquina no quiera, irse a tiempo. Cada una reparte siempre las mismas cartas." superficie="pano">
    <div class="overflow-x-clip">
        <div class="pagina">
            <x-pagina.encabezado titulo="Desafíos" bajada="Manos armadas con un objetivo. Se juegan en cualquier orden.">
                <x-pagina.desplegable titulo="Cómo funcionan">
                    <p>Cada desafío reparte siempre las mismas cartas, y la máquina contesta siempre igual: es un acertijo con solución. Si no sale, probá otro camino.</p>
                    <p>No cuentan para el ranking.</p>
                </x-pagina.desplegable>

                @if ($enCurso)
                    <p class="mt-3 max-w-[60ch] font-bold leading-relaxed">
                        Tenés {{ $enCurso->esDePractica() ? ($enCurso->escalon ? 'un escalón' : 'un desafío') : 'una partida' }} sin terminar.
                        Para empezar otro desafío, primero terminá o abandoná desde la mesa.
                        <a href="{{ route('mesa') }}" class="enlace">Volver a la mesa</a>
                    </p>
                @endif

                {{-- Cuántos van, contados como se cuenta en el club. --}}
                <x-slot:aparte>
                    <p class="sobre-pano flex items-center gap-3 rounded-xl bg-pano-hondo px-4 py-3 font-semibold leading-none">
                        <span class="text-3xl font-black tabular-nums text-oro">{{ $superados }}</span>
                        <span>de {{ $total }} superados</span>
                        <x-administracion.cuenta :cantidad="$superados" class="text-[1.05rem]" />
                    </p>
                </x-slot:aparte>
            </x-pagina.encabezado>

            @foreach ($porTema as $tema => $delTema)
                <section aria-labelledby="tema-{{ Str::slug($tema) }}" class="mt-12 first-of-type:mt-0 lg:mt-14">
                    <h2 id="tema-{{ Str::slug($tema) }}" class="seccion-titulo">{{ $tema }}</h2>

                    <ul class="mt-5 divide-y divide-naipe/20 border-y border-naipe/20">
                        @foreach ($delTema as $n => $desafio)
                            <li class="desafio grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-5 gap-y-4 py-6 sm:gap-x-8 lg:grid-cols-[auto_minmax(0,1fr)_auto] lg:py-7">
                                {{-- La mano con la que se arranca: las tres cartas de verdad, en abanico. Es un dibujo; lo que hay que hacer va escrito al lado. --}}
                                <div class="abanico desafio-mano" aria-hidden="true">
                                    @foreach ($desafio['cartas']['vos'] as $orden => $carta)
                                        @php([$numero, $palo] = explode('-', $carta))
                                        <div><div class="se-reparte" style="--orden: {{ $loop->parent->index + $orden }}"><x-carta :palo="$palo" :numero="(int) $numero" /></div></div>
                                    @endforeach
                                </div>

                                <div class="min-w-0">
                                    <h3 class="text-xl font-extrabold leading-tight tracking-tight sm:text-2xl">{{ $desafio['titulo'] }}</h3>
                                    <p class="mt-1.5 max-w-[44ch] leading-snug sm:text-lg sm:leading-snug">{{ $desafio['consigna'] }}</p>
                                </div>

                                <div class="col-span-2 flex items-center gap-4 lg:col-span-1 lg:flex-col lg:items-end lg:gap-2.5">
                                    @if ($enCurso)
                                        <a href="{{ route('mesa') }}" class="boton boton-linea min-h-12 px-5 max-lg:flex-1">Seguir en la mesa</a>
                                    @else
                                        <form method="POST" action="{{ route('desafios.jugar', $desafio['clave']) }}" class="flex max-lg:flex-1">
                                            @csrf
                                            <button type="submit" @class(['boton min-h-12 flex-1 px-5 text-lg', $desafio['superado'] ? 'boton-linea' : 'boton-naipe']) aria-label="{{ $desafio['superado'] ? 'Jugar de nuevo' : 'Jugar' }}: {{ $desafio['titulo'] }}">
                                                <x-icono nombre="desafio" /> {{ $desafio['superado'] ? 'Jugar de nuevo' : 'Jugar' }}
                                            </button>
                                        </form>
                                    @endif

                                    {{-- La marca de superado es la misma píldora de "Sos mano". Guarda su lugar: los botones no saltan de un renglón a otro. --}}
                                    <p @class(['inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border border-naipe/40 px-2.5 py-0.5 text-sm font-semibold', 'invisible' => ! $desafio['superado']])
                                        @if (! $desafio['superado']) aria-hidden="true" @endif>
                                        <x-icono nombre="quiero" class="size-4" /> Superado
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            <p class="mt-12 max-w-[52ch] leading-relaxed">
                ¿Querés ir de menor a mayor? La <a href="{{ route('escalera') }}" class="enlace">escalera del club</a>
                mezcla manos como estas con partidas cortas contra rivales cada vez más difíciles.
            </p>
        </div>
    </div>
</x-layouts.base>
