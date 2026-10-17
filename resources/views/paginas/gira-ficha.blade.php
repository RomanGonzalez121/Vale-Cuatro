{{--
    La ficha de una parada de la gira. Entera (escritorio): qué es, qué se juega, los tres fósforos y "Jugar"; la
    del jefe es el cartel de su bar. Corta (la hoja del celular): la carta, el número, el título, los fósforos y
    "Jugar" en un renglón.
--}}
@php
    $corta ??= false;
    $jefe = $nivel['tipo'] === 'jefe';
    $sejuega = in_array($nivel['tipo'], \App\Juego\Gira::SE_JUEGAN, true);
    $pide = [
        'Pasarlo',
        \App\Juego\FosforosDeGira::dice($nivel['segundo']),
        \App\Juego\FosforosDeGira::dice($nivel['tercero']),
    ];
    $partes = $nivel['carta'] === null ? null : ['numero' => (int) explode('-', $nivel['carta'])[0], 'palo' => explode('-', $nivel['carta'])[1]];
    $porque = match (true) {
        $nivel['abierto'] => null,
        $nivel['numero'] === 1 => 'Se abre ganándole al jefe del barrio anterior.',
        default => 'Se abre al pasar la parada '.($nivel['numero'] - 1).'.',
    };
    $boton = match (true) {
        ! $nivel['abierto'] => null,
        ! $sejuega => 'Muy pronto',
        $nivel['pasado'] => 'Jugar de nuevo',
        default => 'Jugar',
    };
@endphp

@if ($corta)
    <span class="w-9 flex-none" aria-hidden="true">
        @if ($partes)
            <x-carta :palo="$partes['palo']" :numero="$partes['numero']" />
        @else
            <x-dorso />
        @endif
    </span>
    <div class="min-w-0 flex-1">
        <p class="text-xs font-semibold">{{ $jefe ? $barrio['jefe']['bar'] : 'Parada '.$nivel['numero'].' de '.$cuantos }}</p>
        <p @class(['line-clamp-2 leading-tight', 'canto text-xl' => $jefe, 'font-black' => ! $jefe])>{{ $nivel['titulo'] }}</p>
        <p class="mt-1 flex gap-1.5" aria-label="{{ $nivel['fosforos'] }} de 3 fósforos">
            @for ($i = 0; $i < 3; $i++)
                <span @class(['fosforito', 'apagado' => $i >= $nivel['fosforos']])></span>
            @endfor
        </p>
    </div>
    @if ($boton !== null && $sejuega)
        <form method="POST" action="{{ route('gira.jugar', [$barrio['clave'], $nivel['numero']]) }}" class="flex-none">
            @csrf
            <button type="submit" class="boton boton-basto">{{ $boton }}</button>
        </form>
    @else
        <span class="flex-none text-right text-xs font-semibold leading-tight">{{ $porque ?? $boton }}</span>
    @endif
@else
    <article @class(['ficha-nivel' => ! $jefe, 'cartel-jefe' => $jefe])>
        <p @class(['text-sm font-semibold', 'cartel-bar' => $jefe])>{{ $jefe ? $barrio['jefe']['bar'] : 'Parada '.$nivel['numero'].' de '.$cuantos }}</p>
        <div class="mt-2 flex items-center gap-4">
            <span @class(['ficha-carta flex-none', 'w-11' => ! $jefe, 'w-12 -rotate-3' => $jefe])>
                @if ($partes)
                    <x-carta :palo="$partes['palo']" :numero="$partes['numero']" />
                @else
                    <x-dorso />
                @endif
            </span>
            <div>
                @if ($jefe)
                    <h2 class="canto text-3xl leading-none">{{ $nivel['titulo'] }}</h2>
                    <p class="text-sm font-semibold">{{ $barrio['jefe']['quien'] }}</p>
                @else
                    <h2 class="text-xl font-black leading-tight">{{ $nivel['titulo'] }}</h2>
                    <p class="mt-1 text-sm">{{ $nivel['dice'] }}</p>
                @endif
            </div>
        </div>
        {{-- Lo que trae el rival a la mesa del bar: su mazo y su dorso (se ven en la partida, salvo que juegues con tus cosas). --}}
        @php($trae = \App\Juego\Ambiente::loQueTrae(\App\Juego\Ambiente::delNivel($barrio, $nivel)))
        @if ($jefe)
            <p class="mt-2.5 text-sm"><strong>Su manía:</strong> {{ \Illuminate\Support\Str::after($nivel['dice'], ': ') }} {{ $trae }}</p>
        @elseif ($trae)
            <p class="mt-2 text-sm">{{ $trae }}</p>
        @endif
        {{-- Los tres fósforos como renglones de una planilla, a todo el ancho: cada condición entra en un renglón. --}}
        <ol class="ficha-fosforos mt-2.5 text-sm" aria-label="Los tres fósforos de esta parada">
            @foreach ($pide as $i => $texto)
                <li @class(['ficha-fosforo', 'ganado' => $i < $nivel['fosforos']])>
                    <span @class(['fosforito', 'apagado' => $i >= $nivel['fosforos']])></span>
                    <span>{{ $texto }}</span>
                    @if ($i < $nivel['fosforos'])
                        <span class="sr-only">, ganado</span>
                    @endif
                </li>
            @endforeach
        </ol>
        <div class="mt-2.5 flex items-center justify-end gap-4">
            @if ($boton !== null && $sejuega)
                <form method="POST" action="{{ route('gira.jugar', [$barrio['clave'], $nivel['numero']]) }}" class="flex-none">
                    @csrf
                    <button type="submit" @class(['boton', 'boton-basto' => ! $jefe, 'boton-naipe' => $jefe])>{{ $boton }}</button>
                </form>
            @else
                <p class="text-right text-sm font-semibold">{{ $porque ?? $boton }}</p>
            @endif
        </div>
    </article>
@endif
