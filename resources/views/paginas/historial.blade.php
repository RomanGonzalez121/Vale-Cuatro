@php
    $tonos = ['oro' => 'canto-oro', 'copa' => 'canto-ficha canto-copa', 'basto' => 'canto-ficha canto-basto'];
    $partes = fn (string $carta) => [explode('-', $carta)[1], (int) explode('-', $carta)[0]];
@endphp

<x-layouts.base titulo="Historial" descripcion="Tus partidas de Vale Cuatro, para volver a verlas mano por mano.">
    <div class="mx-auto max-w-4xl px-5 pb-24 sm:px-8">
        <header class="pb-10 pt-10 sm:pt-16">
            <h1 class="text-5xl font-black tracking-tight sm:text-6xl">Historial</h1>
            <p class="mt-5 max-w-[60ch] text-lg leading-relaxed">
                Cada partida queda guardada jugada por jugada. Por eso se puede volver a ver entera, mano por mano.
            </p>
        </header>

        <ul class="border-t-2 border-tinta">
            @foreach ($partidas as $partida)
                @php($gano = $partida['vos'] > $partida['ellos'])
                <li class="grid grid-cols-[1fr_auto] items-center gap-x-4 gap-y-2 border-b border-tinta/15 py-5 sm:grid-cols-[11rem_1fr_auto]">
                    <p class="leading-none">
                        <span @class(['block text-sm font-bold', 'text-basto' => $gano, 'text-copa' => ! $gano])>{{ $gano ? 'Ganaste' : 'Perdiste' }}</span>
                        <span class="mt-1.5 block text-3xl font-black tabular-nums tracking-tight">{{ $partida['vos'] }} a {{ $partida['ellos'] }}</span>
                    </p>
                    <p class="col-span-2 row-start-2 leading-snug sm:col-span-1 sm:row-start-auto">
                        <span class="text-lg font-bold">Contra {{ $partida['rival'] }}</span>
                        <span class="block text-[0.95rem]">{{ $partida['cuando'] }}. {{ $partida['manos'] }} manos en {{ $partida['minutos'] }} minutos.</span>
                    </p>
                    <a href="#repeticion" class="boton boton-linea min-h-11 px-4 py-2 text-[0.95rem]">
                        <x-icono nombre="repetir" /> Ver de nuevo
                    </a>
                </li>
            @endforeach
        </ul>

        <section id="repeticion" aria-labelledby="titulo-repeticion" class="scroll-mt-6 pt-16" x-data="{ mano: 0, total: {{ count($manos) }} }">
            <h2 id="titulo-repeticion" class="text-3xl font-extrabold tracking-tight">Repetición</h2>
            <p class="mt-2 text-lg">Hoy, 21:40, contra Bot, nivel 2. Ganaste 30 a 21.</p>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <button type="button" class="boton boton-linea" :disabled="mano === 0" @click="mano--">Mano anterior</button>
                <button type="button" class="boton boton-tinta" :disabled="mano === total - 1" @click="mano++">Mano siguiente</button>
            </div>

            @foreach ($manos as $i => $mano)
                <article class="superficie-pano mt-6 overflow-hidden rounded-xl" x-show="mano === {{ $i }}" @if ($i > 0) x-cloak @endif>
                    <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-4 bg-pano-hondo px-5 py-4 sm:px-7">
                        <h3 class="text-xl font-extrabold">Mano {{ $mano['numero'] }} de 14</h3>
                        <div class="flex flex-wrap gap-x-8 gap-y-3 text-[0.8rem] sm:text-[0.95rem]">
                            <x-tanteador nombre="Vos" :puntos="$mano['tanteo'][0]" />
                            <x-tanteador nombre="Bot" :puntos="$mano['tanteo'][1]" />
                        </div>
                    </div>

                    <div class="px-5 py-7 sm:px-7">
                        <ol class="flex flex-wrap items-center gap-x-5 gap-y-4" aria-label="Cantos de la mano, en orden">
                            @foreach ($mano['cantos'] as [$quien, $canto, $tono])
                                <li class="flex items-baseline gap-2">
                                    <span class="text-sm font-semibold">{{ $quien }}</span>
                                    <span class="canto {{ $tonos[$tono] }} text-3xl sm:text-4xl">{{ $canto }}</span>
                                </li>
                            @endforeach
                        </ol>

                        <ol class="mt-8 flex flex-wrap gap-x-6 gap-y-6 sm:gap-x-10">
                            @foreach ($mano['bazas'] as $numero => [$tuya, $suya, $ganador])
                                <li>
                                    <p class="mb-2 text-sm font-semibold">{{ $numero + 1 }}ª baza, {{ $ganador === 'vos' ? 'tuya' : 'del bot' }}</p>
                                    <div class="flex gap-2.5">
                                        @foreach ([[$suya, 'Bot', 'rival'], [$tuya, 'Vos', 'vos']] as [$carta, $rotulo, $quien])
                                            <figure class="w-[4.5rem] sm:w-24">
                                                <x-carta :palo="$partes($carta)[0]" :numero="$partes($carta)[1]" :class="$quien !== $ganador ? 'opacity-55' : ''" />
                                                <figcaption class="mt-1.5 text-center text-xs font-semibold">{{ $rotulo }}</figcaption>
                                            </figure>
                                        @endforeach
                                    </div>
                                </li>
                            @endforeach
                        </ol>

                        <p class="mt-7 max-w-[52ch] text-lg font-semibold leading-snug">{{ $mano['resumen'] }}</p>
                    </div>
                </article>
            @endforeach
        </section>
    </div>
</x-layouts.base>
