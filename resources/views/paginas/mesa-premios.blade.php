{{--
    Las cartas de los premios de una partida, que la mesa pide al terminar y pone en su final (mesa.js, mostrarLosPremios).
    Llegan boca abajo; las da vuelta la mesa. Traen su propio sprite, que se dibuja después de las cartas: así sabe
    qué piezas usan (un mazo, un dorso) y trae solo esas, además de las de fábrica.
--}}
<div class="premios-nuevos">
    {{-- Entran tres: si hay más, el link de abajo los cuenta. --}}
    @foreach (array_slice($premios, 0, 3) as $premio)
        <x-premio :tipo="$premio['tipo']" :clave="$premio['clave']" :nombre="$premio['nombre']" :href="$sinCuenta ? route('registro') : route('perfil', ['pieza' => $premio['tipo'].':'.$premio['clave']]).'#coleccion'" :dice="$sinCuenta ? 'creá tu cuenta para usarlo' : 'probar en tu colección'" />
    @endforeach
</div>
<x-mazo.sprite />
