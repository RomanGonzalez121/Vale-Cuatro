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
            <span @class(['flex-none', 'w-11' => ! $jefe, 'w-12 -rotate-3' => $jefe])>
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
        @if ($jefe)
            <p class="mt-3 text-sm"><strong>Su manía:</strong> {{ \Illuminate\Support\Str::after($nivel['dice'], ': ') }}</p>
        @endif
        <div class="mt-4 flex items-end justify-between gap-4">
            <ol class="grid gap-1 text-sm" aria-label="Los tres fósforos de esta parada">
                @foreach ($pide as $i => $texto)
                    <li class="ficha-fosforo"><span @class(['fosforito', 'apagado' => $i >= $nivel['fosforos']])></span> {{ $texto }}</li>
                @endforeach
            </ol>
            @if ($boton !== null && $sejuega)
                <form method="POST" action="{{ route('gira.jugar', [$barrio['clave'], $nivel['numero']]) }}" class="flex-none">
                    @csrf
                    <button type="submit" @class(['boton', 'boton-basto' => ! $jefe, 'boton-naipe' => $jefe])>{{ $boton }}</button>
                </form>
            @else
                <p class="max-w-[10rem] flex-none text-right text-sm font-semibold">{{ $porque ?? $boton }}</p>
            @endif
        </div>
    </article>
@endif
