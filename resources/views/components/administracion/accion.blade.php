@props(['ruta', 'texto', 'confirma' => null, 'icono' => null])

{{--
    Un botón del panel que manda una acción. Las que no tienen vuelta fácil (cerrar una partida,
    descartar un trabajo, ocultar un apodo) se confirman en el lugar: el botón se abre en dos, uno
    que dice exactamente lo que va a pasar y otro para arrepentirse. No hay cartel ni ventana aparte.
--}}
<form method="POST" action="{{ $ruta }}" {{ $attributes->class('flex flex-wrap items-center gap-2') }} @if ($confirma) x-data="{ seguro: false }" @keydown.escape="seguro = false; $nextTick(() => $refs.abrir.focus())" @endif>
    @csrf

    @if ($confirma)
        <button type="button" x-ref="abrir" class="boton boton-linea min-h-11 px-4 py-2 text-[0.95rem]" x-show="! seguro"
            @click="seguro = true; $nextTick(() => $refs.confirmar.focus())">
            @if ($icono)
                <x-icono :nombre="$icono" />
            @endif
            {{ $texto }}
        </button>
        {{-- Los dos entran apenas más chicos y se acomodan: responden al toque, no corren solos. --}}
        <button type="submit" x-ref="confirmar" class="boton boton-copa min-h-11 px-4 py-2 text-[0.95rem]" x-show="seguro" x-cloak
            x-transition:enter="aparece" x-transition:enter-start="desde-chico">{{ $confirma }}</button>
        <button type="button" class="boton boton-linea min-h-11 px-4 py-2 text-[0.95rem]" x-show="seguro" x-cloak
            x-transition:enter="aparece" x-transition:enter-start="desde-chico" @click="seguro = false; $nextTick(() => $refs.abrir.focus())">No</button>
    @else
        <button type="submit" class="boton boton-linea min-h-11 px-4 py-2 text-[0.95rem]">
            @if ($icono)
                <x-icono :nombre="$icono" />
            @endif
            {{ $texto }}
        </button>
    @endif
</form>
