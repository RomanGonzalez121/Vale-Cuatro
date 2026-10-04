@php
    // Lo que el servidor le dice al jugador después de una acción. Cada color cumple
    // su función de palo: Basto confirma algo hecho, Espada informa.
    $avisos = [
        'hecho' => ['bg-basto', 'quiero'],
        'aviso' => ['bg-espada', 'tiempo'],
    ];
@endphp

@foreach ($avisos as $clave => [$fondo, $icono])
    @if (session($clave))
        <div class="mx-auto max-w-6xl px-5 pt-2 sm:px-8">
            <p role="status" class="{{ $fondo }} inline-flex items-center gap-2.5 rounded-[0.625rem] px-4 py-2.5 font-bold leading-snug text-naipe">
                <x-icono :nombre="$icono" class="size-5" /> {{ session($clave) }}
            </p>
        </div>
    @endif
@endforeach
