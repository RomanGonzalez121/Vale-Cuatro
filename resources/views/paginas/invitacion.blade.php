<x-layouts.base titulo="Te invitan a jugar" descripcion="{{ $quienInvita }} te invita a jugar al truco mano a mano en Vale Cuatro. Entrás sin registrarte." superficie="pano">
    {{-- La misma carta de modo que en la pantalla de modos: acá se juega Mano a mano, y la carta lo dice. --}}
    <div class="mx-auto grid max-w-4xl grid-cols-[auto_minmax(0,1fr)] items-center gap-x-6 gap-y-6 px-5 pb-16 pt-8 sm:gap-x-12 sm:px-8 lg:pt-16">
        <div class="w-[clamp(6.25rem,28vw,12rem)] -rotate-3">
            <span class="se-reparte block" style="--orden: 0">
                <span class="se-da-vuelta">
                    <x-carta-modo icono="mano-a-mano" nombre="Mano a mano" :renglones="['Mano', 'a mano']" />
                    <x-dorso class="reverso" />
                </span>
            </span>
        </div>

        <section aria-labelledby="titulo-invitacion">
            <h1 id="titulo-invitacion" class="text-[clamp(1.875rem,5.4vw,3.5rem)] font-black leading-[1] tracking-[-0.03em] [overflow-wrap:anywhere]">
                {{ $quienInvita }} te invita a jugar
            </h1>

            @if ($disponible)
                <p class="mt-4 max-w-[34ch] leading-relaxed sm:text-lg">Un truco mano a mano, a 30 puntos y con flor. Entrás sin registrarte.</p>

                <form method="POST" action="{{ route('invitacion.entrar', $codigo) }}" class="mt-6">
                    @csrf
                    <button type="submit" class="boton boton-naipe min-h-14 px-6 text-lg">
                        <x-icono nombre="mano" /> Sentarme
                    </button>
                </form>
            @else
                <p class="mt-4 max-w-[34ch] font-bold leading-relaxed sm:text-lg">{{ $motivo }}</p>

                <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-3">
                    <form method="POST" action="{{ route('jugar') }}">
                        @csrf
                        <button type="submit" class="boton boton-naipe min-h-12 px-5">
                            <x-icono nombre="bot" /> Jugar contra el bot
                        </button>
                    </form>
                    <a href="{{ route('modos') }}" class="enlace-nav text-lg font-bold">Ver otros modos</a>
                </div>
            @endif
        </section>
    </div>
</x-layouts.base>
