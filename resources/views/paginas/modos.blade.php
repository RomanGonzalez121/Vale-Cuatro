@php
    $elegido = $modos[0]['clave'];
@endphp

<x-layouts.base titulo="Modos de juego" descripcion="Elegí cómo jugar al truco en Vale Cuatro: contra el bot ahora mismo, y los modos que se van sumando." superficie="pano">
    {{-- Las cartas llegan desde afuera de la pantalla: se recorta el costado para que el reparto no agregue scroll. --}}
    <div class="overflow-x-clip">
    <section class="mx-auto grid max-w-6xl gap-x-12 gap-y-6 px-5 pb-16 pt-6 sm:gap-y-9 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:pb-24 lg:pt-14"
        x-data="{ elegido: '{{ $elegido }}' }">
        <div class="lg:col-start-1 lg:row-start-1">
            <h1 class="text-[clamp(2.5rem,6.4vw,4.5rem)] font-black leading-[0.96] tracking-[-0.035em]">
                ¿Cómo querés jugar?
            </h1>
            <p class="mt-4 max-w-[44ch] text-lg leading-relaxed sm:mt-5">
                Las cartas boca abajo todavía no se juegan.
                <span class="hidden sm:inline">Se dan vuelta a medida que se termina cada modo.</span>
            </p>
        </div>

        {{--
            La mano. Es un espejo de la lista de abajo para quien mira y usa el mouse o el dedo:
            el teclado y el lector de pantalla eligen desde la lista, que es la que tiene los nombres.
        --}}
        <div class="mano-modos mx-auto w-full max-w-[17.5rem] sm:max-w-sm lg:sticky lg:top-10 lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:max-w-lg lg:self-start" aria-hidden="true">
            @foreach ($modos as $i => $modo)
                <div class="mano-modos-lugar" style="--i: {{ $i }}">
                    <button type="button" tabindex="-1" class="naipe-modo"
                        @if ($modo['clave'] === $elegido) data-elegida="true" @endif
                        :data-elegida="elegido === '{{ $modo['clave'] }}'"
                        @click="elegido = '{{ $modo['clave'] }}'">
                        {{-- El reparto es el único movimiento que no responde a una acción. --}}
                        <span class="se-reparte block" style="--orden: {{ $i }}">
                            @if ($modo['disponible'])
                                <span class="se-da-vuelta">
                                    <x-carta :palo="$modo['carta'][0]" :numero="$modo['carta'][1]" />
                                    <x-dorso class="reverso" />
                                </span>
                            @else
                                <x-dorso />
                            @endif
                        </span>
                    </button>
                </div>
            @endforeach
        </div>

        <ul class="divide-y divide-naipe/20 lg:col-start-1 lg:row-start-2">
            @foreach ($modos as $modo)
                <li>
                    <button type="button" class="enlace-menu fila-modo flex w-full cursor-pointer items-center gap-4 py-3.5 pr-14 text-left"
                        aria-controls="modo-{{ $modo['clave'] }}"
                        aria-expanded="{{ $modo['clave'] === $elegido ? 'true' : 'false' }}"
                        :aria-expanded="(elegido === '{{ $modo['clave'] }}').toString()"
                        @click="elegido = '{{ $modo['clave'] }}'">
                        <x-icono :nombre="$modo['icono']" class="size-7" />
                        <span>
                            <span class="block text-xl font-extrabold leading-tight tracking-tight sm:text-2xl">{{ $modo['nombre'] }}</span>
                            <span class="mt-0.5 block text-[0.95rem]">{{ $modo['disponible'] ? 'Se juega ahora' : 'Todavía no se juega' }}</span>
                        </span>
                    </button>

                    <div id="modo-{{ $modo['clave'] }}" class="pb-6 pl-11"
                        x-show="elegido === '{{ $modo['clave'] }}'" @if ($modo['clave'] !== $elegido) x-cloak @endif
                        x-transition:enter="transition-opacity duration-150 ease-out" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                        <p class="max-w-[44ch] text-lg leading-relaxed">{{ $modo['resumen'] }}</p>

                        @if ($modo['disponible'])
                            {{-- Sin campos: apretar el botón alcanza. Quien no tiene sesión entra como invitado. --}}
                            <form method="POST" action="{{ route('jugar') }}" class="mt-5">
                                @csrf
                                <button type="submit" class="boton boton-naipe min-h-14 px-6 text-lg">
                                    <x-icono :nombre="$modo['icono']" /> {{ $modo['boton'] }}
                                </button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
    </div>
</x-layouts.base>
