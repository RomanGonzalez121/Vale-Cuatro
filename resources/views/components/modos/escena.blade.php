@props(['juego', 'datos'])

{{--
    Lo tuyo en ese juego, dibujado con las piezas del sitio. Va en el panel de la pantalla de modos, entre
    el nombre del juego y sus opciones: dice dónde estás antes de entrar. En desafíos, la mano del próximo
    que falta; en la gira, los seis barrios y la parada que sigue; en el torneo, el cruce que toca; en mano a mano y de
    a cuatro, las partidas ganadas, contadas con fósforos como en el club.
    Las cartas son dibujo: lo que dicen va escrito al lado.
--}}
@if (in_array($juego, ['mano-a-mano', 'de-a-cuatro'], true))
    <div>
        <p class="text-sm font-semibold">{{ $datos['jugadas'] === 0 ? 'Todavía sin partidas' : ($juego === 'de-a-cuatro' ? 'Llevan ganadas' : 'Llevás ganadas') }}</p>
        {{-- Sin nada jugado, "0 de 0" no dice nada: queda la frase y el lugar de los fósforos, vacío. --}}
        <p class="mt-1.5 flex min-h-7 items-center gap-2.5 font-semibold leading-none">
            @if ($datos['jugadas'] > 0)
                <span class="text-2xl font-black tabular-nums text-oro">{{ $datos['ganadas'] }}</span>
                <span>de {{ $datos['jugadas'] }}</span>
            @endif
            <x-administracion.cuenta :cantidad="$datos['ganadas']" class="text-[0.95rem]" />
        </p>
    </div>
@elseif ($juego === 'desafios')
    <div class="flex items-center gap-5">
        @if ($datos['proximo'])
            <div class="abanico escena-mano flex-none" aria-hidden="true">
                @foreach ($datos['proximo']['cartas']['vos'] as $carta)
                    @php([$numero, $palo] = explode('-', $carta))
                    <div><x-carta :palo="$palo" :numero="(int) $numero" /></div>
                @endforeach
            </div>
        @endif

        <div class="min-w-0">
            @if ($datos['proximo'])
                <p class="text-sm font-semibold">{{ $datos['superados'] === 0 ? 'Para empezar' : 'Te falta' }}</p>
                <p class="text-xl font-extrabold leading-tight tracking-tight">{{ $datos['proximo']['titulo'] }}</p>
            @else
                <p class="text-xl font-extrabold leading-tight tracking-tight">Los superaste todos</p>
            @endif

            <p class="mt-2 flex items-center gap-2.5 font-semibold leading-none">
                <span class="text-2xl font-black tabular-nums text-oro">{{ $datos['superados'] }}</span>
                <span>de {{ $datos['total'] }}</span>
                <x-administracion.cuenta :cantidad="$datos['superados']" class="text-[0.95rem]" />
            </p>
        </div>
    </div>
@elseif ($juego === 'gira')
    <div>
        {{-- Los seis barrios en fila, como en el índice de la guía: los completos llenos, el que se juega con borde, los cerrados apagados. --}}
        <ol class="flex gap-1.5" aria-label="Barrios de la gira">
            @foreach ($datos['barrios'] as $i => $uno)
                <li @class(['grid size-7 place-items-center rounded-full border-[1.5px] border-current text-xs font-extrabold', 'bg-naipe text-tinta' => $uno['completo'], 'opacity-45' => ! $uno['abierto']])
                    title="{{ $uno['nombre'] }}"><span class="sr-only">{{ $uno['nombre'] }}{{ $uno['completo'] ? ', completo' : ($uno['abierto'] ? '' : ', cerrado') }}</span><span aria-hidden="true">{{ $i + 1 }}</span></li>
            @endforeach
        </ol>

        <p class="mt-3 font-semibold leading-snug">
            @if ($datos['completa'])
                Terminaste la gira: los seis barrios.
            @else
                <span class="text-sm">{{ $datos['fosforos'] === 0 ? 'Para empezar' : 'Vas por '.$datos['barrio'].', parada '.$datos['parada'] }}</span>
                <span class="block text-xl font-extrabold leading-tight tracking-tight">{{ $datos['titulo'] }}</span>
            @endif
        </p>
        <p class="mt-2 flex items-center gap-2 font-semibold leading-none">
            <span class="text-2xl font-black tabular-nums text-oro">{{ $datos['fosforos'] }}</span>
            <span>de {{ $datos['deTodos'] }} fósforos</span>
        </p>
    </div>
@elseif ($juego === 'torneo' && ! isset($datos['rival']))
    <div>
        <p class="text-sm font-semibold">{{ $datos['jugados'] === 0 ? 'Todavía sin torneos' : 'Saliste campeón' }}</p>
        <p class="mt-1.5 flex min-h-7 items-center gap-2.5 font-semibold leading-none">
            @if ($datos['jugados'] > 0)
                <span class="text-2xl font-black tabular-nums text-oro">{{ $datos['campeon'] }}</span>
                <span>de {{ $datos['jugados'] }}</span>
            @endif
            <x-administracion.cuenta :cantidad="$datos['campeon']" class="text-[0.95rem]" />
        </p>
    </div>
@elseif ($juego === 'torneo')
    <div class="flex items-center gap-5">
        <x-duelo class="escena-duelo flex-none" :arriba="['carta' => $datos['carta']]" :abajo="['carta' => $datos['tuya']]" />

        <p class="min-w-0 font-semibold leading-snug">
            <span class="text-sm">Te toca {{ $datos['partido'] }}</span>
            <span class="block text-xl font-extrabold leading-tight tracking-tight">Contra {{ $datos['rival'] }}</span>
        </p>
    </div>
@endif
