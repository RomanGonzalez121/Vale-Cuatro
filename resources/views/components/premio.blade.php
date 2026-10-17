@props(['tipo', 'clave', 'nombre', 'vuelta' => null, 'href' => '#', 'dice' => 'probar en tu colección'])

{{--
    Un premio recién ganado, como una carta chica: boca abajo con tu dorso y, al darse vuelta, la pieza misma
    (la carta, la figura del mazo, el dorso nuevo, un pedazo de la mesa, el grupo de cinco, el distintivo o el
    título). Debajo, qué es y cómo se llama. Es un link: abre la Colección en esa pieza, con el probador.

    Llega boca abajo y la da vuelta quien la muestra (mesa.js), cambiando data-vuelta. En la lámina, "vuelta" es una
    expresión de Alpine que lo hace.
--}}
@php
    $tipos = ['carta' => 'Carta', 'dorso' => 'Dorso', 'mesa' => 'Mesa', 'mazo' => 'Mazo', 'anotar' => 'Anotar', 'distintivo' => 'Distintivo', 'titulo' => 'Título'];
    [$numero, $palo] = $tipo === 'carta' ? explode('-', $clave) : [null, null];
@endphp

<a href="{{ $href }}" {{ $attributes->class('premio') }} data-premio aria-label="{{ $tipos[$tipo] }} {{ $nombre }}: {{ $dice }}">
    <span class="premio-naipe" aria-hidden="true">
        <span class="giro block" data-vuelta="false" @if ($vuelta) :data-vuelta="({{ $vuelta }}).toString()" @endif>
            <x-dorso />
            <span class="giro-cara">
                @if ($tipo === 'carta')
                    <x-carta :palo="$palo" :numero="(int) $numero" />
                @elseif ($tipo === 'mazo')
                    {{-- La figura es lo que más distingue a un mazo de otro. --}}
                    <x-carta palo="copa" :numero="12" :mazo="$clave" />
                @elseif ($tipo === 'dorso')
                    <x-dorso :de="$clave" />
                @elseif ($tipo === 'mesa')
                    <span class="pieza-mesa" data-mesa="{{ $clave }}"></span>
                @elseif ($tipo === 'anotar')
                    <span class="premio-cara"><span class="pieza-anotar w-[78%]" data-anotar="{{ $clave }}"><x-fosforos :grupo="0" :puntos="5" :forma="$clave" /></span></span>
                @elseif ($tipo === 'distintivo')
                    <span class="premio-cara"><x-distintivo :de="$clave" class="w-[58%]" /></span>
                @else
                    <span class="premio-cara premio-cara-titulo" lang="es">{{ $nombre }}</span>
                @endif
            </span>
        </span>
    </span>
    <span class="premio-tipo">{{ $tipos[$tipo] }}</span>
    <span class="premio-nombre">{{ $nombre }}</span>
</a>
