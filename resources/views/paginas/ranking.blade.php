<x-layouts.base titulo="Ranking" descripcion="Ranking de Vale Cuatro por partidas ganadas, rachas y envidos.">
    <div class="mx-auto max-w-4xl px-5 pb-24 sm:px-8">
        <header class="pb-10 pt-10 sm:pt-16">
            <h1 class="text-5xl font-black tracking-tight sm:text-6xl">Ranking</h1>
            <p class="mt-5 max-w-[60ch] text-lg leading-relaxed">
                Ordenado por partidas ganadas. Los jugadores marcados como bot son de ejemplo:
                juegan solos para que la tabla no arranque vacía.
            </p>
        </header>

        <table class="tabla">
            <caption class="sr-only">Jugadores ordenados por partidas ganadas</caption>
            <thead>
                <tr>
                    <th scope="col" class="w-14 sm:w-20">Puesto</th>
                    <th scope="col">Jugador</th>
                    <th scope="col" class="numero">Ganadas</th>
                    <th scope="col" class="numero hidden sm:table-cell">Racha</th>
                    <th scope="col" class="numero">Envidos ganados</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($jugadores as $jugador)
                    <tr @class(['bg-oro/25' => ! $jugador['bot']])>
                        <td @class(['font-black tabular-nums', 'text-3xl sm:text-4xl' => $jugador['puesto'] <= 3, 'text-xl' => $jugador['puesto'] > 3, 'pl-3' => ! $jugador['bot']])>
                            {{ $jugador['puesto'] }}
                        </td>
                        <td>
                            <span class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                                <span @class(['font-bold', 'text-xl' => $jugador['puesto'] <= 3, 'text-lg' => $jugador['puesto'] > 3])>{{ $jugador['apodo'] }}</span>
                                @if ($jugador['bot'])
                                    <span class="inline-flex items-center gap-1 rounded-md bg-tinta px-1.5 py-0.5 text-xs font-bold text-naipe">
                                        <x-icono nombre="bot" class="size-4" /> bot
                                    </span>
                                @else
                                    <span class="text-sm">invitado</span>
                                @endif
                            </span>
                        </td>
                        <td class="numero">
                            <span class="text-lg font-bold">{{ $jugador['ganadas'] }}</span>
                            <span class="block text-sm sm:inline">de {{ $jugador['jugadas'] }}</span>
                        </td>
                        <td class="numero hidden sm:table-cell">
                            {{ $jugador['racha'] > 0 ? $jugador['racha'].' seguidas' : 'Sin racha' }}
                        </td>
                        <td class="numero text-lg font-bold">{{ $jugador['envidos'] }} %</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="mt-10">
            <a href="{{ route('mesa') }}" class="boton boton-tinta"><x-icono nombre="repartir" /> Jugar una partida</a>
        </p>
    </div>
</x-layouts.base>
