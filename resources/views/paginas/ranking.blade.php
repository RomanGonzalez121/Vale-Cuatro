@php
    // Los cuatro primeros puestos llevan las cuatro cartas que mandan, en su orden.
    $cartasQueMandan = [['espada', 1], ['basto', 1], ['espada', 7], ['oro', 7]];

    $primeros = array_slice($jugadores, 0, 4);
    $resto = array_slice($jugadores, 4);
    $vos = collect($jugadores)->firstWhere('bot', false);
    $deArriba = collect($jugadores)->firstWhere('puesto', $vos['puesto'] - 1);
@endphp

<x-layouts.base titulo="Ranking" descripcion="Ranking de Vale Cuatro por partidas ganadas, rachas y envidos.">
    <div class="mx-auto max-w-5xl px-5 pb-16 sm:px-8">
        <header class="pb-10 pt-10 sm:pt-16">
            <h1 class="text-5xl font-black tracking-tight sm:text-7xl">Ranking</h1>
            <p class="mt-5 max-w-[60ch] text-lg leading-relaxed">
                Ordenado por partidas ganadas. Los jugadores marcados como bot son de ejemplo:
                juegan solos para que la tabla no arranque vacía.
            </p>
        </header>

        <section aria-labelledby="titulo-mandan" class="superficie-pano rounded-xl p-6 sm:p-9">
            <h2 id="titulo-mandan" class="text-2xl font-extrabold sm:text-3xl">Los cuatro que mandan</h2>
            <p class="mt-2 max-w-[52ch] leading-relaxed">Cada uno de los cuatro primeros tiene una de las cuatro cartas más fuertes del truco.</p>

            <ol class="mt-7 grid grid-cols-2 gap-x-5 gap-y-9 lg:grid-cols-4 lg:gap-x-8">
                @foreach ($primeros as $i => $jugador)
                    <li>
                        <div class="max-w-36"><x-carta :palo="$cartasQueMandan[$i][0]" :numero="$cartasQueMandan[$i][1]" /></div>
                        <p class="mt-4 flex items-baseline gap-2.5 leading-none">
                            <span class="text-4xl font-black text-oro">{{ $jugador['puesto'] }}</span>
                            <span class="text-xl font-extrabold leading-tight">{{ $jugador['apodo'] }}</span>
                        </p>
                        @if ($jugador['bot'])
                            <span class="mt-2 inline-flex items-center gap-1 rounded-md bg-naipe px-1.5 py-0.5 text-xs font-bold text-tinta">
                                <x-icono nombre="bot" class="size-4" /> bot
                            </span>
                        @endif
                        <p class="mt-3 text-[0.95rem] leading-snug">
                            <strong class="text-lg font-black tabular-nums">{{ $jugador['ganadas'] }}</strong> ganadas de {{ $jugador['jugadas'] }}
                        </p>
                        <p class="mt-1.5"><x-racha :cantidad="$jugador['racha']" /></p>
                    </li>
                @endforeach
            </ol>
        </section>

        <table class="tabla mt-10">
            <caption class="sr-only">Del quinto puesto en adelante</caption>
            <thead>
                <tr>
                    <th scope="col" class="w-14 sm:w-20">Puesto</th>
                    <th scope="col">Jugador</th>
                    <th scope="col" class="numero">Ganadas</th>
                    <th scope="col">Racha</th>
                    <th scope="col" class="numero hidden sm:table-cell">Envidos ganados</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($resto as $jugador)
                    <tr @class(['bg-oro/25' => ! $jugador['bot']])>
                        <td @class(['text-2xl font-black tabular-nums', 'pl-3' => ! $jugador['bot']])>{{ $jugador['puesto'] }}</td>
                        <td>
                            <span class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                                <span class="text-lg font-bold leading-tight">{{ $jugador['apodo'] }}</span>
                                @if ($jugador['bot'])
                                    <span class="inline-flex items-center gap-1 rounded-md bg-texto px-1.5 py-0.5 text-xs font-bold text-fondo">
                                        <x-icono nombre="bot" class="size-4" /> bot
                                    </span>
                                @else
                                    <span class="text-sm">invitado</span>
                                @endif
                            </span>
                        </td>
                        <td class="numero">
                            <span class="text-lg font-bold">{{ $jugador['ganadas'] }}</span>
                            <span class="hidden text-sm sm:inline">de {{ $jugador['jugadas'] }}</span>
                        </td>
                        <td class="text-[0.8rem] sm:text-[0.95rem]"><x-racha :cantidad="$jugador['racha']" /></td>
                        <td class="numero hidden text-lg font-bold sm:table-cell">{{ $jugador['envidos'] }} %</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Tu puesto queda siempre a la vista mientras se recorre la tabla. --}}
        <aside aria-label="Tu puesto" class="sticky bottom-3 z-10 mt-8 flex flex-wrap items-center gap-x-7 gap-y-3 rounded-xl bg-texto px-5 py-4 text-fondo sm:px-7">
            <p class="flex items-baseline gap-2.5 leading-none">
                <span class="text-sm font-semibold">Tu puesto</span>
                <span class="text-4xl font-black tabular-nums">{{ $vos['puesto'] }}</span>
            </p>
            <p class="leading-snug">
                <strong class="font-black">{{ $vos['ganadas'] }}</strong> ganadas de {{ $vos['jugadas'] }}.
                Te faltan <strong class="font-black">{{ $deArriba['ganadas'] - $vos['ganadas'] }}</strong> para alcanzar a {{ $deArriba['apodo'] }}.
            </p>
            <form method="POST" action="{{ route('jugar') }}" class="sm:ml-auto">
                @csrf
                <button type="submit" class="boton boton-linea min-h-11 py-2"><x-icono nombre="repartir" /> Jugar una partida</button>
            </form>
        </aside>
    </div>
</x-layouts.base>
