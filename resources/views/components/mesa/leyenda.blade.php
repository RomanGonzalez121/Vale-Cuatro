@props(['texto' => 'Quiero vale cuatro'])

{{--
    La leyenda de la mesa fileteada: todo filete lleva su frase, y la de esta mesa es un dicho del truco. Va en la
    letra de los cantos porque es un canto. Es adorno: el lector de pantalla no la lee.
--}}
<p {{ $attributes->class('leyenda-filete') }} aria-hidden="true"><span class="leyenda-filete-sombra">{{ $texto }}</span>{{ $texto }}</p>
