{{--
    La repetición como página: es lo que se ve al recargar con el cartel abierto o al entrar directo a la
    dirección de una partida. La pieza es la misma que usa el cartel del historial; acá los datos vienen
    escritos en la página en vez de pedirse aparte.
--}}

<x-layouts.base titulo="Repetición" descripcion="Una partida de Vale Cuatro, para volver a verla jugada por jugada.">
    <x-mazo.plantillas />

    {{-- Los datos van en un bloque aparte y no dentro de un atributo: ahí cada comilla ocuparía seis letras. --}}
    <script type="application/json" id="datos-de-la-repeticion">@json($datos)</script>

    {{--
        Si se llegó acá recargando con el cartel abierto, para el navegador esta página y la lista siguen
        siendo la misma: con "atrás" cambia la dirección y no carga nada. Por eso se le pide que cargue.
    --}}
    <div class="mx-auto max-w-5xl px-5 pb-24 pt-8 sm:px-8 sm:pt-12" x-data @popstate.window="window.location.reload()">
        <x-repeticion datos="JSON.parse(document.getElementById('datos-de-la-repeticion').textContent)" encabezado="h1">
            <a href="{{ route('historial') }}" class="enlace-nav mb-5 font-bold">Volver al historial</a>
        </x-repeticion>
    </div>
</x-layouts.base>
