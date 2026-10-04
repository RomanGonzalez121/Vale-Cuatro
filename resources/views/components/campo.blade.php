@props(['nombre', 'rotulo', 'tipo' => 'text', 'ayuda' => null, 'valor' => null])

@php
    /*
     | Un campo de formulario: rótulo, ayuda, el control y su error. La ayuda y
     | el error quedan atados al control para que el lector de pantalla los lea.
     |
     | La contraseña suma dos cosas. Una carta para verla, porque se escribe una
     | sola vez: boca abajo está oculta y boca arriba, con un ojo, se ve. Y la
     | cuenta de caracteres en fósforos, de a cinco por grupo como en el
     | tanteador: cada tecla hace caer uno.
     */
    $id = "campo-{$nombre}";
    $esClave = $tipo === 'password';
    $describe = implode(' ', array_filter([
        $ayuda ? "{$id}-ayuda" : null,
        $errors->has($nombre) ? "{$id}-error" : null,
    ]));
@endphp

<div @if ($esClave) x-data="{ visible: false, largo: 0 }" @endif>
    <label for="{{ $id }}" class="block font-bold">{{ $rotulo }}</label>

    @if ($ayuda)
        <p id="{{ $id }}-ayuda" class="mt-0.5 text-sm">{{ $ayuda }}</p>
    @endif

    <span class="campo-linea mt-1">
        <input id="{{ $id }}" name="{{ $nombre }}" type="{{ $tipo }}" @class(['campo', 'pr-14' => $esClave])
            @if ($esClave) :type="visible ? 'text' : 'password'" @input="largo = $event.target.value.length" @else value="{{ old($nombre, $valor) }}" @endif
            @if ($describe) aria-describedby="{{ $describe }}" @endif
            @error($nombre) aria-invalid="true" @enderror
            {{ $attributes }}>

        @if ($esClave)
            {{-- Va después del campo en el HTML: con el teclado se llega primero a la contraseña. --}}
            <button type="button" class="ver-clave" @click="visible = ! visible" aria-controls="{{ $id }}"
                aria-label="Mostrar la contraseña" aria-pressed="false" :aria-pressed="visible.toString()"
                title="Mostrar la contraseña" :title="visible ? 'Ocultar la contraseña' : 'Mostrar la contraseña'">
                <span class="giro ver-clave-carta" data-vuelta="false" :data-vuelta="visible.toString()" aria-hidden="true">
                    <svg viewBox="0 0 100 156">
                        <rect x="4" y="4" width="92" height="148" rx="14" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="8" />
                        <rect x="22" y="22" width="56" height="112" rx="5" fill="var(--color-tinta)" />
                        <circle cx="50" cy="78" r="13" fill="var(--color-naipe)" />
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

    @if ($esClave)
        <div class="-mb-2 mt-3 flex h-7 gap-2" aria-hidden="true">
            @for ($g = 0; $g < 6; $g++)
                <x-fosforos :grupo="$g" modelo="largo" palito="currentColor" class="size-7" />
            @endfor
        </div>
    @endif

    @error($nombre)
        <p id="{{ $id }}-error" class="mt-2 text-sm font-semibold text-pierde">{{ $message }}</p>
    @enderror
</div>
