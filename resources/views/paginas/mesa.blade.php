@php
    /*
     | Todos los botones que puede llegar a tener la barra de cantos. La mesa
     | muestra solo los que el motor declara válidos en cada momento, y en el
     | orden que corresponde: [clave, texto, texto corto, clase, ícono].
     */
    $botones = [
        ['quiero', 'Quiero', null, 'boton-basto', 'quiero'],
        ['no_quiero', 'No quiero', null, 'boton-copa', 'no-quiero'],
        ['truco', 'Truco', null, 'boton-copa', 'truco'],
        ['retruco', 'Retruco', null, 'boton-copa', 'truco'],
        ['vale_cuatro', 'Vale cuatro', null, 'boton-copa', 'truco'],
        ['grupo-envido', 'Envido', null, 'boton-oro', 'envido'],
        ['grupo-subir', 'Subir', null, 'boton-oro', 'envido'],
        ['envido', 'Envido', null, 'boton-oro', 'envido'],
        ['real_envido', 'Real envido', 'Real', 'boton-oro', 'envido'],
        ['falta_envido', 'Falta envido', 'Falta', 'boton-oro', 'envido'],
        ['flor', 'Flor', null, 'boton-oro', 'flor'],
        ['contraflor', 'Contraflor', null, 'boton-oro', 'flor'],
        ['contraflor_al_resto', 'Contraflor al resto', 'Al resto', 'boton-oro', 'flor'],
        ['mazo', 'Al mazo', null, 'boton-linea', 'mazo'],
        ['mas', 'Más', null, 'boton-linea', null],
        ['volver', 'Volver', null, 'boton-linea', null],
    ];
@endphp

<x-layouts.base titulo="Mesa" superficie="pano" :encabezado="false" :pie="false">
    <x-mazo.plantillas />

    {{-- La mesa ocupa la pantalla completa y nunca hace scroll: ver .mesa en app.css. --}}
    <div x-data="mesa(@js($vista), @js(['accion' => route('mesa.accion'), 'repartir' => route('mesa.repartir'), 'estado' => route('mesa.estado'), 'bot' => route('mesa.bot'), 'token' => csrf_token()]))" @class(['mesa mesa-completa relative', 'mesa-ultra' => $nivel === \App\Juego\Nivel::UltraDificil])>
        <h1 class="sr-only">Mesa contra el bot, nivel {{ $nivel->nombre() }}</h1>

        <header class="mesa-barra relative z-10">
            <a href="{{ route('portada') }}" class="justify-self-start rounded text-lg no-underline [grid-area:logo] lg:text-2xl" aria-label="Vale Cuatro, ir al inicio"><x-logo /></a>

            <section aria-label="Tanteador" class="grid grid-cols-2 gap-x-4 text-[clamp(0.8rem,4.1cqw,1.3rem)] [grid-area:tanteo] lg:gap-x-12">
                <x-tanteador nombre="Vos" :puntos="$vista['tanteo'][0]" modelo="puntos.vos" />
                <x-tanteador nombre="Bot" :puntos="$vista['tanteo'][1]" modelo="puntos.rival" />
            </section>

            <div class="flex items-center gap-2 justify-self-end [grid-area:acciones]">
                <x-modo />
                <button type="button" class="boton boton-linea min-h-10 gap-1.5 px-3 py-1.5 text-sm" @click="saliendo = true" aria-label="Salir de la mesa">
                    <x-icono nombre="salir" class="size-5" /> Salir
                </button>
            </div>
        </header>

        {{-- Tocar la mesa apura lo que se esté mostrando (los tantos del envido). --}}
        <div class="mesa-campo" @click="apurar()">
            <section aria-label="Rival" class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 sm:gap-7">
                <p class="flex flex-col items-end gap-1 justify-self-end text-right text-sm font-semibold leading-tight sm:flex-row sm:items-center sm:gap-2 sm:text-base">
                    <span class="flex items-center gap-1.5">
                        {{-- Mientras el bot piensa, el reloj aparece junto a su ícono. Guarda su lugar, así nada se corre, y no gira ni late. --}}
                        <span class="transition-opacity ease-out" :class="pensando ? 'opacity-100 duration-200' : 'opacity-0 duration-100'" aria-hidden="true">
                            <x-icono nombre="tiempo" class="size-4" />
                        </span>
                        <x-icono nombre="bot" class="size-5" />
                        {{-- El nivel, con los mismos fósforos que se eligieron en los modos. --}}
                        <x-nivel-fosforos :nivel="$nivel" class="text-[0.8rem]" />
                    </span>
                    Bot {{ mb_strtolower($nivel->nombre()) }}
                </p>
                <div x-ref="rival" class="mesa-rival flex justify-center gap-1.5 sm:gap-2"></div>
                {{-- El mazo, contra el borde del campo para que no parezca una carta más del rival. De acá sale el reparto. --}}
                <div x-ref="origen" class="mesa-mazo mr-1 justify-self-end sm:mr-5" aria-hidden="true">
                    <x-dorso />
                    <x-dorso />
                    <x-dorso />
                </div>
            </section>

            <section aria-label="Cartas jugadas" class="relative flex flex-1 flex-col justify-center py-2">
                <ol x-ref="bazas" class="mx-auto grid w-full grid-cols-3 gap-3" style="max-width: calc(var(--b) * 7.5)">
                    @foreach ([0, 1, 2] as $numero)
                        <li class="flex flex-col items-center" :class="{ 'baza-actual': baza === {{ $numero }} && ! cerrada }">
                            <p class="mb-2 text-xs font-semibold sm:text-sm">
                                {{ $numero + 1 }}ª baza<span x-show="resultadoDeBaza({{ $numero }})" x-cloak>, <span x-text="resultadoDeBaza({{ $numero }})"></span></span>
                            </p>
                            {{-- Un solo lugar marcado por baza; las dos cartas caen adentro, pisándose en diagonal. --}}
                            <div class="baza-lugar">
                                <div data-hueco="rival" class="hueco-baza hueco-rival"></div>
                                <div data-hueco="vos" class="hueco-baza hueco-propio"></div>
                            </div>
                        </li>
                    @endforeach
                </ol>

                <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center" aria-hidden="true">
                    <p class="voz canto text-center" :data-visible="voz.visible" :data-quien="voz.quien" :style="{ fontSize: tamanoDeVoz }"
                        :class="{ 'canto-oro': voz.tono === 'oro', 'canto-ficha canto-copa': voz.tono === 'copa', 'canto-ficha canto-basto': voz.tono === 'basto' }"
                        x-text="voz.texto"></p>
                </div>

                {{--
                    Los tantos del envido, cantados como en la mesa: arriba el rival y abajo vos.
                    El que pierde queda a media tinta. Lo mismo se anuncia en el aviso de abajo.
                --}}
                <div class="pointer-events-none absolute inset-0 z-10 flex flex-col items-center justify-center gap-[0.3em] pt-7" style="font-size: min(5.5rem, 9dvh, 23cqw)" aria-hidden="true">
                    @foreach (['rival', 'vos'] as $quien)
                        <p class="voz canto canto-oro text-center" data-quien="{{ $quien }}" :data-visible="tantos.{{ $quien }}.visible"
                            :data-pierde="tantos.resuelto && tantos.gana !== '{{ $quien }}'">
                            <span class="block leading-[0.85]" x-show="tantos.{{ $quien }}.numero !== ''" x-text="tantos.{{ $quien }}.numero"></span>
                            <span class="block leading-none" :class="tantos.{{ $quien }}.numero === '' ? 'text-[0.5em]' : 'text-[0.34em]'" x-text="tantos.{{ $quien }}.frase"></span>
                        </p>
                    @endforeach
                </div>
            </section>

            {{-- Al cerrar la mano el aviso sigue anunciándose, pero lo que se ve es el cierre de abajo. --}}
            <p aria-live="polite" class="min-h-6 text-center text-[0.95rem] font-semibold leading-snug sm:text-lg" :class="{ 'opacity-0': cierre }" x-text="aviso">Repartiendo.</p>

            {{-- En el celular, la marca de mano y el estado del truco van arriba de las cartas; en pantallas anchas, a los costados. --}}
            <section aria-label="Tu mano" class="relative grid items-center gap-x-7 pb-4 pt-1 sm:grid-cols-[1fr_auto_1fr]">
                <div class="flex min-h-7 items-center justify-center gap-3 text-sm sm:contents">
                    {{-- La marca guarda su lugar aunque el mano sea el bot: la mesa no se corre. --}}
                    <p class="inline-flex items-center gap-1.5 rounded-full border border-naipe/40 px-2.5 py-0.5 font-semibold sm:order-1 sm:justify-self-end"
                        :class="{ 'opacity-0': cierre || ! esMano }" :aria-hidden="(! esMano).toString()">
                        <x-icono nombre="mano" class="size-4" /> Sos mano
                    </p>
                    <p class="font-semibold sm:order-3 sm:justify-self-start sm:text-base" :class="{ 'opacity-0': cierre }" x-text="estadoDelTruco"></p>
                </div>
                <div x-ref="mano" class="abanico mesa-mano sm:order-2">
                    <div></div>
                    <div></div>
                    <div></div>
                </div>

                {{--
                    El cierre de la mano ocupa el lugar de tus cartas, que ya volvieron al mazo:
                    quién ganó, por qué y cuánto sumó cada cosa. No agrega una fila a la mesa.
                --}}
                <div x-show="cierre" x-cloak x-transition:enter="aparece" x-transition:enter-start="opacity-0"
                    class="absolute inset-0 z-10 flex flex-col items-center justify-center px-2 text-center" aria-hidden="true">
                    <p class="text-[clamp(1.5rem,7cqw,2.5rem)] font-black leading-none tracking-tight" x-text="cierre?.titulo"></p>
                    <p class="mt-2 text-sm font-semibold sm:text-base" x-text="cierre?.motivo"></p>
                    <ul class="mt-3 flex flex-wrap justify-center gap-x-6 gap-y-1.5">
                        <template x-for="(linea, numero) in cierre?.lineas ?? []" :key="numero">
                            <li class="flex items-baseline gap-2 text-sm font-semibold sm:text-base">
                                <span class="text-[1.75rem] font-black leading-none tabular-nums text-oro" x-text="linea.puntos"></span>
                                <span x-text="linea.texto"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </section>
        </div>

        <section aria-label="Cantos disponibles" class="relative z-10 bg-pano-hondo px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3">
            {{--
                Solo se muestran los cantos que el motor declara válidos en este momento, en una fila.
                Los botones están todos acá; la mesa decide cuáles se ven y en qué orden. Las dos cosas
                van en un solo :style, porque x-show y :style en el mismo botón se pisan.
            --}}
            <div x-ref="barra" class="mesa-acciones mx-auto flex max-w-2xl gap-2" :data-muchos="barra.length > 3">
                @foreach ($botones as [$clave, $texto, $corto, $clase, $icono])
                    <button type="button" class="boton {{ $clase }}" x-cloak data-boton="{{ $clave }}" :style="estiloDe('{{ $clave }}')"
                        :disabled="ocupada" @click="tocar('{{ $clave }}')" @if ($corto) aria-label="{{ $texto }}" @endif>
                        @if ($icono)
                            <x-icono :nombre="$icono" />
                        @endif
                        @if ($corto)
                            {{-- En el celular va el nombre corto, que es como se dice en la mesa. --}}
                            <span class="sm:hidden">{{ $corto }}</span><span class="hidden sm:inline">{{ $texto }}</span>
                        @else
                            {{ $texto }}
                        @endif
                    </button>
                @endforeach

                {{-- Con la mano cerrada no queda nada por cantar: lo único que se puede hacer es repartir. --}}
                <button type="button" x-ref="repartir" class="boton boton-naipe" x-cloak data-boton="repartir" :style="estiloDe('repartir')"
                    :disabled="ocupada" @click="tocar('repartir')">
                    <x-icono nombre="repartir" /> Repartir
                </button>
            </div>
        </section>

        <div x-show="fin" x-cloak x-transition.opacity.duration.200ms class="absolute inset-0 z-30 flex items-center justify-center bg-pano-hondo/90 p-6">
            <div class="superficie-naipe w-full max-w-sm rounded-xl p-7 text-center">
                <h2 class="text-3xl font-black tracking-tight" x-text="fin === 'vos' ? 'Ganaste la partida' : 'Ganó el bot'" x-ref="fin" tabindex="-1"></h2>
                <p class="mt-2 text-lg tabular-nums">
                    <span x-text="puntos.vos"></span> a <span x-text="puntos.rival"></span>
                </p>
                <div class="mt-6 flex flex-col gap-2.5">
                    <form method="POST" action="{{ route('jugar') }}" class="flex flex-col">
                        @csrf
                        {{-- La partida siguiente es contra el mismo nivel. --}}
                        <input type="hidden" name="nivel" value="{{ $nivel->value }}">
                        <button type="submit" class="boton boton-tinta">Jugar otra partida</button>
                    </form>
                    <a href="{{ route('historial') }}" class="boton boton-linea">Ver el historial</a>
                </div>
            </div>
        </div>

        {{-- Salir: la partida queda guardada para seguirla después, o se abandona y se pierde. --}}
        <div x-show="saliendo" x-cloak x-transition.opacity.duration.200ms class="absolute inset-0 z-30 flex items-center justify-center bg-pano-hondo/90 p-6"
            @keydown.escape.window="saliendo = false" role="dialog" aria-modal="true" aria-labelledby="titulo-salir">
            <div class="superficie-naipe w-full max-w-sm rounded-xl p-7 text-center">
                <h2 id="titulo-salir" class="text-3xl font-black tracking-tight">¿Salir de la mesa?</h2>
                <p class="mt-2 leading-relaxed">La partida queda guardada: cuando vuelvas a jugar, sigue donde la dejaste. Si la abandonás, la perdés.</p>
                <div class="mt-6 flex flex-col gap-2.5">
                    <button type="button" class="boton boton-tinta" @click="saliendo = false">Seguir jugando</button>
                    <a href="{{ route('portada') }}" class="boton boton-linea">Salir y seguir después</a>
                    <form method="POST" action="{{ route('mesa.abandonar') }}" class="flex flex-col">
                        @csrf
                        <button type="submit" class="boton boton-linea">Abandonar la partida</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.base>
