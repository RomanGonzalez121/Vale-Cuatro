@props(['practica' => null])

@php
    /*
     | Todos los botones que puede llegar a tener la barra de cantos. La mesa
     | muestra solo los que el motor declara válidos en cada momento, y en el
     | orden que corresponde: [clave, texto, texto corto, clase, ícono].
     */
    $botones = [
        ['quiero', 'Quiero', null, 'boton-basto', 'quiero'],
        ['no_quiero', 'No quiero', null, 'boton-copa', 'no-quiero'],
        ['truco', 'Truco', null, 'boton-copa', 'truco'],
        ['retruco', 'Retruco', null, 'boton-copa', 'truco'],
        ['vale_cuatro', 'Vale cuatro', null, 'boton-copa', 'truco'],
        ['grupo-envido', 'Envido', null, 'boton-oro', 'envido'],
        ['grupo-subir', 'Subir', null, 'boton-oro', 'envido'],
        ['envido', 'Envido', null, 'boton-oro', 'envido'],
        ['real_envido', 'Real envido', 'Real', 'boton-oro', 'envido'],
        ['falta_envido', 'Falta envido', 'Falta', 'boton-oro', 'envido'],
        ['flor', 'Flor', null, 'boton-oro', 'flor'],
        ['contraflor', 'Contraflor', null, 'boton-oro', 'flor'],
        ['contraflor_al_resto', 'Contraflor al resto', 'Al resto', 'boton-oro', 'flor'],
        ['mazo', 'Al mazo', null, 'boton-linea', 'mazo'],
        ['mas', 'Más', null, 'boton-linea', null],
        ['volver', 'Volver', null, 'boton-linea', null],
    ];
@endphp

{{-- Cuando te toca, la barra se enciende: un filete de oro se dibuja por su borde de arriba. --}}
<section aria-label="Cantos disponibles" :inert="fin !== null" :data-turno="teToca" class="barra-de-cantos relative z-10 bg-pano-hondo px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3">
    {{--
        Solo se muestran los cantos que el motor declara válidos en este momento, en una fila.
        Los botones están todos acá; la mesa decide cuáles se ven y en qué orden. Las dos cosas
        van en un solo :style, porque x-show y :style en el mismo botón se pisan.
    --}}
    <div x-ref="barra" class="mesa-acciones mx-auto flex max-w-2xl gap-2" :data-muchos="barra.length > 3">
        @foreach ($botones as [$clave, $texto, $corto, $clase, $icono])
            <button type="button" class="boton {{ $clase }}" x-cloak data-boton="{{ $clave }}" :style="estiloDe('{{ $clave }}')"
                :disabled="ocupada" @click="tocar('{{ $clave }}')" @if ($corto) aria-label="{{ $texto }}" @endif>
                @if ($icono)
                    <x-icono :nombre="$icono" />
                @endif
                @if ($corto)
                    {{-- En el celular va el nombre corto, que es como se dice en la mesa. --}}
                    <span class="sm:hidden">{{ $corto }}</span><span class="hidden sm:inline">{{ $texto }}</span>
                @else
                    {{ $texto }}
                @endif
            </button>
        @endforeach

        {{-- Con la mano cerrada no queda nada por cantar: lo único que se puede hacer es repartir. --}}
        <button type="button" x-ref="repartir" class="boton boton-naipe" x-cloak data-boton="repartir" :style="estiloDe('repartir')"
            :disabled="ocupada" @click="tocar('repartir')">
            <x-icono nombre="repartir" /> Repartir
        </button>

        {{--
            Un desafío es una sola mano: al cerrar no se reparte. En esta misma fila va lo que sigue: probar de
            nuevo, subir al escalón que se abrió o volver a la lista. Primero lo que más probablemente se quiere.
            Van sin ícono: a 360 px los dos textos ocupan todo el ancho.
        --}}
        @if ($practica)
            @if ($practica['siguiente'])
                <form method="POST" action="{{ $practica['siguiente'] }}" class="contents" x-show="cerroElDesafio && superado" x-cloak>
                    @csrf
                    <button type="submit" class="boton boton-naipe" data-boton="subir">{{ $practica['subir'] }}</button>
                </form>
            @endif
            <a href="{{ $practica['volver'] }}" class="boton" :class="superado && ! {{ $practica['siguiente'] ? 'true' : 'false' }} ? 'boton-naipe order-first' : 'boton-linea order-last'"
                x-show="cerroElDesafio" x-cloak data-boton="volver-a-la-lista">
                {{ $practica['volverDice'] }}
            </a>
            <form method="POST" action="{{ $practica['reintentar'] }}" class="contents" x-show="cerroElDesafio && ! (superado && {{ $practica['siguiente'] ? 'true' : 'false' }})" x-cloak>
                @csrf
                <button type="submit" class="boton" :class="superado ? 'boton-linea' : 'boton-naipe'" data-boton="reintentar">
                    <span x-text="superado ? 'Jugarlo de nuevo' : 'Probar de nuevo'">Probar de nuevo</span>
                </button>
            </form>
        @endif
    </div>
</section>
