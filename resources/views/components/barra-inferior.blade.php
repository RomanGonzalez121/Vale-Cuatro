@php
    // Ruta => [texto, ícono]. La cuenta no va acá: en el celular queda arriba, junto al modo.
    $lugares = [
        'modos' => ['Jugar', 'repartir'],
        'como-se-juega' => ['Reglas', 'mano'],
        'ranking' => ['Ranking', 'ranking'],
        'historial' => ['Historial', 'repetir'],
    ];

    // Con una partida sin terminar, "Jugar" lleva directo a ella y lleva el puntito encendido (App\View\Marcas).
    $sinTerminar = \App\View\Marcas::partidaSinTerminar();
@endphp

{{--
    La navegación del celular: una barra fija abajo, al alcance del pulgar. Es lo
    único del sitio que acompaña el scroll (decidido por Román). No aparece en la
    mesa ni en ingreso y registro. Desde `lg` la reemplaza el menú de arriba.
--}}
<nav aria-label="Principal" class="barra-inferior lg:hidden">
    <ul class="mx-auto grid max-w-xl grid-cols-4">
        @foreach ($lugares as $ruta => [$texto, $icono])
            <li>
                @php($marcada = $ruta === 'modos' && $sinTerminar)
                <a href="{{ $marcada ? route('mesa') : route($ruta) }}" @class(['barra-lugar', 'con-marca' => $marcada]) @if (request()->routeIs($ruta)) aria-current="page" @endif>
                    <x-icono :nombre="$icono" />
                    <span class="barra-rotulo">{{ $texto }}@if ($marcada)<span class="sr-only">. Tenés una partida sin terminar</span>@endif</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
