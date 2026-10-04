<x-cuenta titulo="Tu perfil" lado-primero x-data="{ apodo: {{ Js::from(old('apodo', $jugador->apodo)) }} }">
    <form method="POST" action="{{ route('perfil') }}" class="flex flex-col gap-6">
        @csrf
        @method('PUT')

        <x-campo nombre="apodo" rotulo="Apodo" ayuda="De 3 a 20 caracteres. Es el nombre que ve tu rival."
            :valor="$jugador->apodo" x-model="apodo" maxlength="20" autocomplete="nickname" autocapitalize="words" required />

        <div>
            <x-boton-enviar texto="Guardar apodo" enviando="Guardando…" class="boton-tinta" />
        </div>
    </form>

    <dl class="mt-10 border-t border-current/15 pt-6">
        <dt class="font-bold">Email</dt>
        <dd class="mt-0.5 break-words">{{ $jugador->email }}</dd>
    </dl>

    <form method="POST" action="{{ route('salir') }}" class="mt-8">
        @csrf
        <button type="submit" class="boton boton-linea">
            <x-icono nombre="salir" /> Cerrar sesión
        </button>
    </form>

    <x-slot:lado>
        <x-cuenta.tanteo>
            <form method="POST" action="{{ route('jugar') }}" class="mt-8">
                @csrf
                <button type="submit" class="boton boton-naipe">
                    <x-icono nombre="bot" /> Jugar contra el bot
                </button>
            </form>
        </x-cuenta.tanteo>
    </x-slot:lado>
</x-cuenta>
