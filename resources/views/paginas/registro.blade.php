@php($invitado = auth()->user())

<x-cuenta titulo="Crear cuenta" frase="Creá tu cuenta" lado-primero x-data="{ apodo: {{ Js::from(old('apodo', '')) }} }">
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

    <x-slot:lado>
        <x-cuenta.tanteo />
    </x-slot:lado>
</x-cuenta>
