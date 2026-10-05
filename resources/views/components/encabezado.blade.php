@php
    // Ruta => [texto, ícono para el menú del celular]
    $enlaces = [
        'como-se-juega' => ['Cómo se juega', 'mano'],
        'ranking' => ['Ranking', 'ranking'],
        'historial' => ['Historial', 'repetir'],
    ];

    // La cuenta: con cuenta, el apodo lleva al perfil; sin ella (o jugando sin cuenta), a ingresar.
    $jugador = auth()->user();
    $conCuenta = $jugador !== null && ! $jugador->esInvitado();
    $enlaces[$conCuenta ? 'perfil' : 'ingresar'] = [$conCuenta ? $jugador->apodo : 'Ingresar', 'jugador'];
@endphp

<header x-data="{ abierto: false }" @click.outside="abierto = false" @keydown.escape.window="abierto = false" class="relative z-20">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4 sm:px-8">
        <a href="{{ route('portada') }}" class="rounded text-2xl no-underline sm:text-[1.75rem]" aria-label="Vale Cuatro, ir al inicio">
            <x-logo />
        </a>

        <div class="flex items-center gap-3 lg:gap-7">
            <nav aria-label="Principal" class="hidden lg:block">
                <ul class="flex items-center gap-8">
                    {{-- "Jugar" lleva a elegir el modo. A la mesa se entra desde ahí o desde el botón de la portada. --}}
                    <li>
                        <a href="{{ route('modos') }}" class="enlace-nav" @if (request()->routeIs('modos')) aria-current="page" @endif>Jugar</a>
                    </li>
                    @foreach ($enlaces as $ruta => [$texto])
                        <li>
                            <a href="{{ route($ruta) }}" class="enlace-nav" @if (request()->routeIs($ruta)) aria-current="page" @endif>{{ $texto }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <x-modo />

            <button type="button" class="menu-boton lg:hidden" @click="abierto = ! abierto"
                :aria-expanded="abierto.toString()" aria-expanded="false" aria-controls="menu-movil">
                <span class="menu-icono" aria-hidden="true"><span></span><span></span><span></span></span>
                <span x-text="abierto ? 'Cerrar' : 'Menú'">Menú</span>
            </button>
        </div>
    </div>

    <nav id="menu-movil" aria-label="Principal, celular" x-show="abierto" x-cloak
        x-transition:enter="menu-entra" x-transition:enter-start="menu-fuera"
        x-transition:leave="menu-sale" x-transition:leave-end="menu-fuera"
        class="menu-movil absolute inset-x-0 top-full px-5 pb-6 pt-1 sm:px-8 lg:hidden">
        <ul>
            <li style="--i: 0">
                <a href="{{ route('modos') }}" class="enlace-menu flex items-center gap-4 py-3.5 text-2xl font-extrabold tracking-tight no-underline"
                    @if (request()->routeIs('modos')) aria-current="page" @endif>
                    <x-icono nombre="repartir" class="size-7" />
                    Jugar
                </a>
            </li>
            @foreach ($enlaces as $ruta => [$texto, $icono])
                <li style="--i: {{ $loop->iteration }}">
                    <a href="{{ route($ruta) }}" class="enlace-menu flex items-center gap-4 py-3.5 text-2xl font-extrabold tracking-tight no-underline"
                        @if (request()->routeIs($ruta)) aria-current="page" @endif>
                        <x-icono :nombre="$icono" class="size-7" />
                        {{ $texto }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
</header>
