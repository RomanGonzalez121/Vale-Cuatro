@props(['apodo'])

{{-- La placa de latón de la mesa de grafito: grabada con el apodo de su dueño, que es el único que la ve. --}}
<p {{ $attributes->class('placa-de-laton') }}><span>La mesa de</span> {{ $apodo }}</p>
