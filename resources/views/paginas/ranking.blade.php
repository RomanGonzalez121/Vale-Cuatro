@php
    /*
     | $filas es la tabla ya ordenada (App\Juego\Ranking): sale de las partidas que se jugaron.
     | $propia es la fila de quien mira, con su puesto (o el que tendría, si juega sin cuenta), y
     | $deArriba la del puesto que tiene que alcanzar.
     */

    // Los cuatro primeros puestos llevan las cuatro cartas que mandan, en su orden.
    $cartasQueMandan = [['espada', 1], ['basto', 1], ['espada', 7], ['oro', 7]];

    $primeros = array_slice($filas, 0, 4);
    $resto = array_slice($filas, 4);
    $faltan = $propia && $deArriba ? $deArriba['ganadas'] - $propia['ganadas'] : null;
@endphp

<x-layouts.base titulo="Ranking" descripcion="Ranking de Vale Cuatro por partidas ganadas, rachas y envidos.">
    <div class="mx-auto max-w-5xl px-5 pb-16 sm:px-8">
        <header class="pb-10 pt-10 sm:pt-16">
            <h1 class="text-5xl font-black tracking-tight sm:text-7xl">Ranking</h1>
            <p class="mt-5 max-w-[60ch] text-lg leading-relaxed">
                Ordenado por partidas ganadas. Si empatan, va primero quien tiene mejor porcentaje y después quien trae más racha.
            </p>
            <p class="mt-3 max-w-[60ch] leading-relaxed">
                Cuentan las partidas entre personas y las de contra el bot desde Intermedio.
                Los jugadores marcados como bot son de ejemplo: juegan entre ellos para que la tabla no arranque vacía.
            </p>
        </header>

        @if ($primeros !== [])
            <section aria-labelledby="titulo-mandan" class="superficie-pano rounded-xl p-5 sm:p-9">
                <h2 id="titulo-mandan" class="text-2xl font-extrabold sm:text-3xl">Los cuatro que mandan</h2>
                <p class="mt-2 max-w-[52ch] leading-relaxed">Cada uno de los cuatro primeros tiene una de las cuatro cartas más fuertes del truco.</p>

                {{-- Los cuatro en un renglón, también en el celular. Ahí cada dato baja a su propia línea. --}}
                <ol class="mt-7 grid grid-cols-4 gap-x-2.5 max-sm:mt-5 sm:gap-x-5 lg:gap-x-8">
                    @foreach ($primeros as $i => $jugador)
                        <li class="min-w-0">
                            <div class="max-w-36"><x-carta :palo="$cartasQueMandan[$i][0]" :numero="$cartasQueMandan[$i][1]" /></div>
                            <p class="mt-4 flex items-baseline gap-2.5 leading-none max-lg:flex-col max-lg:gap-1.5 max-sm:mt-2.5">
                                <span class="text-4xl font-black text-oro max-sm:text-2xl">{{ $jugador['puesto'] }}</span>
                                <span class="text-xl font-extrabold leading-tight [overflow-wrap:anywhere] max-lg:min-h-[2lh] max-sm:text-[0.8rem]">{{ $jugador['apodo'] }}</span>
                            </p>
                            @if ($jugador['bot'])
                                <span class="mt-2 inline-flex items-center gap-1 rounded-md bg-naipe px-1.5 py-0.5 text-xs font-bold text-tinta max-sm:mt-1.5">
                                    <x-icono nombre="bot" class="size-4" /> bot
                                </span>
                            @elseif ($jugador['vos'])
                                <span class="mt-2 inline-flex rounded-md bg-oro px-1.5 py-0.5 text-xs font-bold text-tinta max-sm:mt-1.5">vos</span>
                            @endif
                            <p class="mt-3 text-[0.95rem] leading-snug max-sm:mt-2 max-sm:text-xs">
                                <strong class="text-lg font-black tabular-nums max-sm:block max-sm:leading-tight">{{ $jugador['ganadas'] }}</strong> ganadas <span class="max-lg:block">de {{ $jugador['jugadas'] }}</span>
                            </p>
                            <p class="mt-1.5 max-sm:text-[0.7rem]"><x-racha :cantidad="$jugador['racha']" /></p>
                        </li>
                    @endforeach
                </ol>
            </section>
        @else
            {{-- Sin ninguna partida que cuente (un sitio recién instalado): se dice, y no se rellena con nada. --}}
            <p class="border-t-2 border-texto pt-8 text-2xl font-black leading-tight tracking-tight">Todavía no hay partidas que cuenten.</p>
        @endif

        @if ($resto !== [])
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
                        <tr @class(['bg-oro/25' => $jugador['vos']])>
                            <td @class(['text-2xl font-black tabular-nums', 'pl-3' => $jugador['vos']])>{{ $jugador['puesto'] }}</td>
                            <td>
                                <span class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                                    <span class="text-lg font-bold leading-tight [overflow-wrap:anywhere]">{{ $jugador['apodo'] }}</span>
                                    @if ($jugador['bot'])
                                        <span class="inline-flex items-center gap-1 rounded-md bg-texto px-1.5 py-0.5 text-xs font-bold text-fondo">
                                            <x-icono nombre="bot" class="size-4" /> bot
                                        </span>
                                    @elseif ($jugador['vos'])
                                        <span class="text-sm font-semibold">vos</span>
                                    @endif
                                </span>
                            </td>
                            <td class="numero">
                                <span class="text-lg font-bold">{{ $jugador['ganadas'] }}</span>
                                <span class="hidden text-sm sm:inline">de {{ $jugador['jugadas'] }}</span>
                            </td>
                            <td class="text-[0.8rem] sm:text-[0.95rem]"><x-racha :cantidad="$jugador['racha']" /></td>
                            <td class="numero hidden sm:table-cell">
                                @if ($jugador['envidos'] === null)
                                    <span class="text-sm">sin envidos</span>
                                @else
                                    <span class="text-lg font-bold">{{ $jugador['envidos'] }} %</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- Tu puesto cierra la tabla, quieto: nada acompaña el scroll. --}}
        <aside aria-label="Tu puesto" class="mt-8 flex flex-wrap items-center gap-x-7 gap-y-3 rounded-xl bg-texto px-5 py-4 text-fondo sm:px-7">
            @if ($propia)
                <p class="flex items-baseline gap-2.5 leading-none">
                    {{-- Quien juega sin cuenta no está en la tabla: se le dice dónde estaría. --}}
                    <span class="text-sm font-semibold">{{ $esInvitado ? 'Estarías en el puesto' : 'Tu puesto' }}</span>
                    <span class="text-4xl font-black tabular-nums">{{ $propia['puesto'] }}</span>
                </p>
                {{-- El texto ocupa lo que queda entre el puesto y el botón, y baja de renglón ahí adentro. --}}
                <p class="min-w-[14rem] flex-1 leading-snug">
                    <strong class="font-black">{{ $propia['ganadas'] }}</strong> {{ $propia['ganadas'] === 1 ? 'ganada' : 'ganadas' }} de {{ $propia['jugadas'] }}.
                    @if ($deArriba === null)
                        {{ $esInvitado ? 'Irías primero.' : 'Vas primero.' }}
                    @elseif ($faltan > 0)
                        {{ $faltan === 1 ? 'Te falta' : 'Te faltan' }} <strong class="font-black">{{ $faltan }}</strong> para alcanzar a {{ $deArriba['apodo'] }}.
                    @else
                        Tenés las mismas ganadas que {{ $deArriba['apodo'] }}.
                    @endif
                    @if ($esInvitado)
                        <a href="{{ route('registro') }}" class="enlace text-fondo">Creá una cuenta</a> para aparecer en la tabla.
                    @endif
                </p>
            @else
                <p class="min-w-[14rem] flex-1 leading-snug"><strong class="font-black">Todavía no tenés partidas que cuenten.</strong></p>
            @endif

            @if ($enCurso)
                <a href="{{ route('mesa') }}" class="boton boton-linea min-h-11 py-2 sm:ml-auto">Seguir la partida</a>
            @else
                <form method="POST" action="{{ route('jugar') }}" class="sm:ml-auto">
                    @csrf
                    <button type="submit" class="boton boton-linea min-h-11 py-2"><x-icono nombre="repartir" /> Jugar una partida</button>
                </form>
            @endif
        </aside>
    </div>
</x-layouts.base>
