@props(['lugar', 'nombre', 'nombreVivo' => null])

{{--
    Uno de los otros tres en la repetición de una partida de a cuatro: las cartas que le quedan, dibujadas
    en chico (número y palo, que a ese tamaño es lo que se lee), y debajo quién es. Si se fue al mazo, lo dice.
    Es dibujo: cada jugada va contada con palabras en el renglón de la mesa.
--}}
<div class="repeticion-otro" aria-hidden="true">
    <div class="repeticion-otro-cartas">
        @foreach ([0, 1, 2] as $numero)
            <x-mesa.mini-naipe de="dibujoDe(estado.otros.{{ $lugar }}[{{ $numero }}])" ::class="{ 'invisible': ! estado.otros.{{ $lugar }}[{{ $numero }}] }" />
        @endforeach
    </div>
    <p class="otro-nombre">
        <x-icono nombre="bot" class="size-4" />
        {{-- "nombreVivo" (una expresión) cambia el nombre según la partida: en el gallito, por su lado. --}}
        <span x-text="seFue('{{ $lugar }}') ? 'Al mazo' : {{ $nombreVivo ?? \Illuminate\Support\Js::from($nombre) }}">{{ $nombre }}</span>
    </p>
</div>
