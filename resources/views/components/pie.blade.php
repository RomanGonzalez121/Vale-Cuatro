<footer class="border-t border-current/15">
    <div class="mx-auto flex max-w-6xl flex-col gap-6 px-5 py-10 sm:px-8 md:flex-row md:items-end md:justify-between">
        <div class="max-w-[52ch]">
            <x-logo class="text-xl" />
            <p class="mt-3 text-sm leading-relaxed opacity-90">
                Los rivales bot y los jugadores de ejemplo del ranking son simulados, y están marcados.
                El motor de reglas, el tiempo real, el historial y el ranking son reales.
            </p>
        </div>

        <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm font-semibold">
            <li><a href="{{ route('como-se-juega') }}" class="underline underline-offset-4">Cómo se juega</a></li>
            <li><a href="{{ route('identidad') }}" class="underline underline-offset-4">Identidad</a></li>
            <li><a href="https://github.com/RomanGonzalez121/Vale-Cuatro" class="underline underline-offset-4">Código en GitHub</a></li>
            {{-- En el celular el menú de arriba no está: quien administra el sitio llega a su panel desde acá. --}}
            @if (auth()->user()?->esAdministrador())
                <li><a href="{{ route('administracion') }}" class="underline underline-offset-4">Administración</a></li>
            @endif
        </ul>
    </div>
</footer>
