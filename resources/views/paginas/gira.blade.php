{{--
    La gira de los clubes (M26), aprobada por Román en una lámina el 16 de octubre de 2026. En escritorio es una
    guía de calles abierta: a la izquierda el índice (los barrios con su línea de puntos y la ficha de la parada
    elegida, con "Jugar"), la raya doble del lomo, y a la derecha el plano medido contra el alto de la ventana.
    En el celular es una isla por pantalla (como los mapas de Candy Crush): los barrios en una fila que se
    desliza, el plano parado de borde a borde y la parada en una hoja que asoma abajo (como Google Maps).
    Todo entra en la primera pantalla, sin deslizar.
--}}
@php
    $cuantos = count($barrio['niveles']);
    $cerradoPor = $anterior['jefe']['nombre'] ?? null;
@endphp
<x-layouts.base titulo="La gira de los clubes">
    <div class="gira mx-auto w-full max-w-[88rem] px-4 sm:px-8" x-data="{ elegida: {{ $elegida }}, elegir(n) { this.elegida = n } }">
        <div class="guia-abierta">
            {{-- La página del índice --}}
            <div class="guia-indice">
                <h1 class="gira-titulo">La gira de los clubes</h1>
                <p class="gira-cuenta">Llevás {{ $fosforos }} {{ $fosforos === 1 ? 'fósforo' : 'fósforos' }} de {{ $deTodos }}.</p>

                <ol class="gira-barrios" aria-label="Barrios">
                    @foreach ($barrios as $i => $uno)
                        <li>
                            @php($clases = ['indice-barrio', 'es-actual' => $uno['clave'] === $barrio['clave'], 'esta-cerrado' => ! $uno['abierto']])
                            <a href="{{ route('gira.barrio', $uno['clave']) }}" @class($clases) @if ($uno['clave'] === $barrio['clave']) aria-current="page" @endif>
                                <span class="barrio-numero">{{ $i + 1 }}</span>
                                <span class="indice-nombre">{{ $uno['nombre'] }}</span>
                                <span class="indice-puntos" aria-hidden="true"></span>
                                <span class="indice-cuenta">
                                    @if ($uno['abierto'])
                                        {{ $uno['fosforos'] }} de {{ $uno['deTodos'] }}
                                    @else
                                        Cerrado
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ol>

                {{-- En escritorio: la ficha de la parada elegida (o el cartel del jefe, si es su bar) --}}
                <div class="solo-escritorio gira-fichas">
                    @foreach ($barrio['niveles'] as $nivel)
                        <div x-show="elegida === {{ $nivel['numero'] }}" @if ($nivel['numero'] !== $elegida) x-cloak @endif>
                            @include('paginas.gira-ficha', ['nivel' => $nivel, 'cuantos' => $cuantos])
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- La página del plano: acostada en escritorio, parada en el celular, con la hoja de la parada encima --}}
            <div class="isla">
                <figure class="guia">
                    <figcaption class="sr-only">Plano de {{ $barrio['nombre'] }}: {{ $barrio['dice'] }}</figcaption>
                    <x-gira.plano :barrio="$barrio" orientacion="acostado" :carta="$carta" class="solo-escritorio" />
                    <x-gira.plano :barrio="$barrio" orientacion="parado" :carta="$carta" class="solo-celular gira-plano-parado" />
                </figure>

                <div class="parada-hoja solo-celular">
                    <span class="hoja-manija" aria-hidden="true"></span>
                    @foreach ($barrio['niveles'] as $nivel)
                        <div class="parada-tira" x-show="elegida === {{ $nivel['numero'] }}" @if ($nivel['numero'] !== $elegida) x-cloak @endif>
                            @include('paginas.gira-ficha', ['nivel' => $nivel, 'cuantos' => $cuantos, 'corta' => true])
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @if (! $barrio['abierto'] && $cerradoPor !== null)
            <p class="sr-only">{{ $barrio['nombre'] }} se abre ganándole a {{ $cerradoPor }}.</p>
        @endif
    </div>
</x-layouts.base>
