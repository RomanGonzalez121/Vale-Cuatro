<footer class="border-t border-current/15">
    <div class="mx-auto flex max-w-6xl flex-col gap-6 px-5 py-10 sm:px-8 md:flex-row md:items-end md:justify-between">
        <div class="max-w-[52ch]">
            <x-logo class="text-xl" />
            {{-- Lo simulado se dice abiertamente, en todas las páginas: una línea, y el detalle a un toque. --}}
            <p class="mt-3 text-sm font-semibold leading-relaxed">La máquina es simulada; las partidas, el ranking y el historial son reales.</p>
            <x-pagina.desplegable titulo="Más" class="pie-mas text-sm">
                <p><strong>Simulado:</strong> la máquina y los jugadores de ejemplo del ranking. En todo el sitio aparecen marcados.</p>
                <p><strong>Real:</strong> el motor de reglas, el tiempo real, las partidas en vivo entre personas, el historial que se puede volver a ver mano por mano y el ranking.</p>
            </x-pagina.desplegable>

            {{-- En un iPhone el sitio se instala a mano: se dice cómo (resources/js/instalar.js). --}}
            <p class="mt-4 text-sm leading-relaxed" data-instalar-en-iphone hidden>
                Para instalarlo en el iPhone: tocá Compartir y después «Agregar a inicio».
            </p>
        </div>

        <ul class="flex flex-wrap gap-x-6 gap-y-1 text-sm font-semibold">
            <li><a href="{{ route('como-se-juega') }}" class="enlace-nav">Cómo se juega</a></li>
            <li><a href="{{ route('identidad') }}" class="enlace-nav">Identidad</a></li>
            <li><a href="https://github.com/RomanGonzalez121/Vale-Cuatro" class="enlace-nav">Código en GitHub</a></li>
            {{-- Instalarlo como aplicación: aparece solo cuando el navegador avisa que se puede. La portada tiene su franja. --}}
            <li data-instalar hidden><button type="button" class="enlace-nav cursor-pointer font-semibold">Instalar la aplicación</button></li>
            {{-- En el celular el menú de arriba no está: quien administra el sitio llega a su panel desde acá. --}}
            @if (auth()->user()?->esAdministrador())
                <li><a href="{{ route('administracion') }}" class="enlace-nav">Administración</a></li>
            @endif
        </ul>
    </div>
</footer>
