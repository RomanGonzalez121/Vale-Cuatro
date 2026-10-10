@php
    // Ruta => texto. "Jugar" lleva a elegir el modo: a la mesa se entra desde ahí o desde el botón de la portada.
    $enlaces = [
        'modos' => 'Jugar',
        'como-se-juega' => 'Cómo se juega',
        'ranking' => 'Ranking',
        'historial' => 'Historial',
    ];

    // La cuenta: con cuenta, el apodo lleva al perfil; sin ella (o jugando sin cuenta), a ingresar.
    $jugador = auth()->user();
    $conCuenta = $jugador !== null && ! $jugador->esInvitado();
    $rutaDeCuenta = $conCuenta ? 'perfil' : 'ingresar';
    $textoDeCuenta = $conCuenta ? $jugador->apodo : 'Ingresar';

    // Solo para la cuenta de administración: un lugar más en el menú, que lleva a su panel.
    if ($jugador?->esAdministrador()) {
        $enlaces['administracion'] = 'Administración';
    }
@endphp

<header class="relative z-20">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4 sm:px-8">
        <a href="{{ route('portada') }}" class="rounded text-2xl no-underline sm:text-[1.75rem]" aria-label="Vale Cuatro, ir al inicio">
            <x-logo />
        </a>

        <div class="flex items-center gap-3 lg:gap-7">
            <nav aria-label="Principal" class="hidden lg:block">
                <ul class="flex items-center gap-8">
                    @foreach ($enlaces + [$rutaDeCuenta => $textoDeCuenta] as $ruta => $texto)
                        <li>
                            <a href="{{ route($ruta) }}" class="enlace-nav" @if (request()->routeIs($ruta)) aria-current="page" @endif>{{ $texto }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <x-modo />

            {{-- En el celular la navegación va en la barra de abajo (<x-barra-inferior>); arriba queda solo la cuenta. --}}
            <a href="{{ route($rutaDeCuenta) }}" class="enlace-nav max-w-[7rem] truncate lg:hidden"
                @if (request()->routeIs($rutaDeCuenta)) aria-current="page" @endif>{{ $textoDeCuenta }}</a>
        </div>
    </div>
</header>
