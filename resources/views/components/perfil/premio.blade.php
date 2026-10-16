@props(['ficha'])
@php
    /*
     | Un premio del camino, dibujado como se ve en la Colección: la carta, el dorso, una muestra de la mesa, la mano
     | del mazo, el grupo de cinco de la forma de anotar o el distintivo.
     */
    $tipo = $ficha['tipo'];
    $clave = $ficha['clave'];
    $carta = $tipo === 'carta' ? ['numero' => (int) explode('-', $clave)[0], 'palo' => explode('-', $clave)[1]] : null;
    $distintivo = $clave === 'cuatro-palos' ? 'palo-oro' : $clave;
@endphp
<span {{ $attributes->class(['premio', 'premio-'.$tipo]) }} aria-hidden="true">
    @if ($tipo === 'carta')
        <x-carta :palo="$carta['palo']" :numero="$carta['numero']" mazo="fabrica" />
    @elseif ($tipo === 'dorso')
        <x-dorso :de="$clave" />
    @elseif ($tipo === 'mesa')
        <span class="pieza-mesa" data-mesa="{{ $clave }}"></span>
    @elseif ($tipo === 'mazo')
        <span class="pieza-mazo">
            <x-carta palo="espada" :numero="1" :mazo="$clave" />
            <x-carta palo="oro" :numero="7" :mazo="$clave" />
            <x-carta palo="copa" :numero="12" :mazo="$clave" />
        </span>
    @elseif ($tipo === 'anotar')
        <span class="pieza-anotar" data-anotar="{{ $clave }}"><x-fosforos :grupo="0" :puntos="5" :forma="$clave" /></span>
    @elseif ($tipo === 'distintivo')
        <span class="pieza-distintivo"><x-distintivo :de="$distintivo" class="size-[2.2rem]" /></span>
    @endif
</span>
