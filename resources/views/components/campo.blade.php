@props(['nombre', 'rotulo', 'tipo' => 'text', 'ayuda' => null, 'valor' => null])

@php
    /*
     | Un campo de formulario: rótulo, ayuda, el control y su error. La ayuda y
     | el error quedan atados al control para que el lector de pantalla los lea.
     |
     | La contraseña suma una carta para verla, porque se escribe una sola vez:
     | de un lado tiene un ojo cerrado (oculta) y del otro el ojo abierto (se
     | ve); al tocarla se da vuelta.
     */
    $id = "campo-{$nombre}";
    $esClave = $tipo === 'password';
    $describe = implode(' ', array_filter([
        $ayuda ? "{$id}-ayuda" : null,
        $errors->has($nombre) ? "{$id}-error" : null,
    ]));
@endphp

<div @if ($esClave) x-data="{ visible: false }" @endif>
    <label for="{{ $id }}" class="block font-bold">{{ $rotulo }}</label>

    @if ($ayuda)
        <p id="{{ $id }}-ayuda" class="mt-0.5 text-sm">{{ $ayuda }}</p>
    @endif

    <span class="campo-linea mt-1">
        <input id="{{ $id }}" name="{{ $nombre }}" type="{{ $tipo }}" @class(['campo', 'pr-14' => $esClave])
            @if ($esClave) :type="visible ? 'text' : 'password'" @else value="{{ old($nombre, $valor) }}" @endif
            @if ($describe) aria-describedby="{{ $describe }}" @endif
            @error($nombre) aria-invalid="true" @enderror
            {{ $attributes }}>

        @if ($esClave)
            {{-- Va después del campo en el HTML: con el teclado se llega primero a la contraseña. --}}
            <button type="button" class="ver-clave" @click="visible = ! visible" aria-controls="{{ $id }}"
                aria-label="Mostrar la contraseña" aria-pressed="false" :aria-pressed="visible.toString()"
                title="Mostrar la contraseña" :title="visible ? 'Ocultar la contraseña' : 'Mostrar la contraseña'">
                <span class="giro ver-clave-carta" data-vuelta="false" :data-vuelta="visible.toString()" aria-hidden="true">
                    {{-- Oculta: el ojo cerrado. El párpado es la mitad de abajo del ojo abierto, con tres pestañas. --}}
                    <svg viewBox="0 0 100 156">
                        <rect x="4" y="4" width="92" height="148" rx="14" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="8" />
                        <path d="M17 60 50 86 83 60M50 86V101M32 72 23 88M68 72 77 88" fill="none" stroke="var(--color-tinta)" stroke-width="8" stroke-linejoin="miter" />
                        <circle cx="50" cy="105" r="6.5" fill="var(--color-tinta)" />
                        <circle cx="21" cy="92" r="6.5" fill="var(--color-tinta)" />
                        <circle cx="79" cy="92" r="6.5" fill="var(--color-tinta)" />
                    </svg>
                    <svg class="giro-cara" viewBox="0 0 100 156">
                        <rect x="4" y="4" width="92" height="148" rx="14" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="8" />
                        <path d="M17 78 50 50 83 78 50 106Z" fill="none" stroke="var(--color-tinta)" stroke-width="8" stroke-linejoin="miter" />
                        <circle cx="50" cy="78" r="11" fill="var(--color-tinta)" />
                    </svg>
                </span>
            </button>
        @endif
    </span>

    @error($nombre)
        <p id="{{ $id }}-error" class="mt-2 text-sm font-semibold text-pierde">{{ $message }}</p>
    @enderror
</div>
