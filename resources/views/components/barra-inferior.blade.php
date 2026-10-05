@php
    // Ruta => [texto, ícono]. La cuenta no va acá: en el celular queda arriba, junto al modo.
    $lugares = [
        'modos' => ['Jugar', 'repartir'],
        'como-se-juega' => ['Reglas', 'mano'],
        'ranking' => ['Ranking', 'ranking'],
        'historial' => ['Historial', 'repetir'],
    ];
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
                <a href="{{ route($ruta) }}" class="barra-lugar" @if (request()->routeIs($ruta)) aria-current="page" @endif>
                    <x-icono :nombre="$icono" />
                    <span class="barra-rotulo">{{ $texto }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
