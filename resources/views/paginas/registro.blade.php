@php($invitado = auth()->user())

<x-cuenta titulo="Crear cuenta" frase="Creá tu cuenta" mesa-solo-escritorio x-data="{ apodo: {{ Js::from(old('apodo', '')) }} }">
    <x-slot:bajada>
        @if ($invitado)
            Venís jugando como {{ $invitado->apodo }}. Al crear la cuenta seguís siendo el mismo jugador, con el apodo que elijas.
        @else
            Elegí el apodo con el que te sentás a la mesa.
        @endif
    </x-slot:bajada>

    <form method="POST" action="{{ route('registro') }}" class="flex flex-col gap-7">
        @csrf

        <x-campo nombre="apodo" rotulo="Apodo" ayuda="De 3 a 20 caracteres. Es el nombre que ve tu rival."
            x-model="apodo" maxlength="20" autocomplete="nickname" autocapitalize="words" required />

        <x-campo nombre="email" rotulo="Email" tipo="email" autocomplete="email" required />

        <x-campo nombre="password" rotulo="Contraseña" tipo="password" ayuda="8 caracteres o más."
            minlength="8" autocomplete="new-password" required />

        <div class="mt-1 flex flex-wrap items-center gap-x-6 gap-y-3">
            <x-boton-enviar texto="Crear cuenta" enviando="Creando la cuenta…" icono="jugador" class="boton-tinta min-h-14 px-6 text-lg" />
            <p>¿Ya tenés cuenta? <a href="{{ route('ingresar') }}" class="enlace-nav text-enlace">Ingresar</a></p>
        </div>
    </form>

    {{-- En el celular, el apodo en el tanteador va en una franja arriba del formulario: se ve cambiar mientras se escribe. --}}
    <x-slot:franja>
        <div class="px-5 pb-3.5 pt-1">
            <p class="text-sm font-semibold">Así queda tu apodo en el tanteador.</p>
            <x-tanteador class="mt-2.5 text-[1.05rem]" nombre="Tu apodo" nombre-vivo="apodo.trim() || 'Tu apodo'" :puntos="12"
                clase-nombre="min-w-0 truncate text-[1.0625rem] font-extrabold tracking-tight" />
        </div>
    </x-slot:franja>

    <x-slot:lado>
        <x-cuenta.tanteo />
    </x-slot:lado>
</x-cuenta>
