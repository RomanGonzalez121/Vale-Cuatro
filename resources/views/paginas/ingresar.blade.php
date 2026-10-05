@php
    // Tu mano, boca abajo. Cada carta se da vuelta cuando se completa un paso:
    // [palo, número, condición de Alpine, si ya arranca dada vuelta].
    $mano = [
        ['espada', 7, 'emailListo', filter_var(old('email'), FILTER_VALIDATE_EMAIL) !== false],
        ['espada', 1, 'clave.length > 0', false],
        ['basto', 1, 'enviando', false],
    ];
@endphp

<x-cuenta titulo="Ingresar" frase="Sentate a la mesa."
    x-data="{
        email: {{ Js::from(old('email', '')) }},
        clave: '',
        enviando: false,
        get emailListo() { return /^\S+@\S+\.\S+$/.test(this.email.trim()); },
    }"
    @pageshow.window="enviando = false">
    <x-slot:bajada>Ingresá con tu cuenta y jugá con tu apodo.</x-slot:bajada>

    <form method="POST" action="{{ route('ingresar') }}" class="flex flex-col gap-7" @submit="enviando = true">
        @csrf

        <x-campo nombre="email" rotulo="Email" tipo="email" x-model="email" autocomplete="email" required autofocus />

        <x-campo nombre="password" rotulo="Contraseña" tipo="password" x-model="clave" autocomplete="current-password" required />

        <label class="flex min-h-11 cursor-pointer items-center gap-3 font-semibold">
            <input type="checkbox" name="recordarme" value="1" class="casilla sr-only" @checked(old('recordarme'))>
            <span class="casilla-caja" aria-hidden="true"><x-icono nombre="quiero" /></span>
            Recordarme en este dispositivo
        </label>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
            <x-boton-enviar texto="Ingresar" enviando="Ingresando…" icono="jugador" class="boton-tinta min-h-14 px-6 text-lg" />
            <p>¿No tenés cuenta? <a href="{{ route('registro') }}" class="enlace-nav text-enlace">Crear cuenta</a></p>
        </div>
    </form>

    <x-slot:lado>
        <div class="cuenta-mesa-texto relative px-7 pt-10 sm:px-12 sm:pt-12 lg:px-16">
            <h2 class="max-w-[14ch] text-3xl font-black leading-[1.02] tracking-tight sm:text-4xl lg:text-[2.75rem]">¿Sin cuenta? Jugá igual.</h2>
            <p class="mt-3 max-w-[34ch] leading-relaxed lg:text-lg">
                Contra el bot no hace falta registrarse: entrás como invitado, ahora mismo.
            </p>
            <form method="POST" action="{{ route('jugar') }}" class="mt-6">
                @csrf
                <button type="submit" class="boton boton-naipe">
                    <x-icono nombre="bot" /> Jugar contra el bot
                </button>
            </form>
        </div>

        {{-- La mano asoma desde el borde de abajo y responde al formulario. Es un adorno: no se anuncia. --}}
        {{-- El ancho de la carta también mira el alto de la ventana, para que la mano entre en una pantalla baja. --}}
        <div class="abanico mano-asoma cuenta-mano mt-auto pt-12" style="--ancho-carta: clamp(6rem, min(15vw, 24dvh), 13.5rem)" aria-hidden="true">
            @foreach ($mano as $orden => [$palo, $numero, $condicion, $dadaVuelta])
                <div>
                    <div class="se-reparte" style="--orden: {{ $orden }}">
                        <div class="giro" data-vuelta="{{ $dadaVuelta ? 'true' : 'false' }}" :data-vuelta="({{ $condicion }}).toString()">
                            <div><x-dorso /></div>
                            <div class="giro-cara"><x-carta :palo="$palo" :numero="$numero" /></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-slot:lado>
</x-cuenta>
