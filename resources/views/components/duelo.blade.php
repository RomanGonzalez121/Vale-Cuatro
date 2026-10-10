@props(['arriba' => null, 'abajo' => null, 'reparte' => false])

{{--
    Dos cartas que se pisan en diagonal, como en una baza de la mesa: usa sus mismos estilos (.baza-lugar),
    así que la que gana queda arriba y, si todavía no hay cartas, se ve el lugar marcado.

    Cada lado es null (todavía no se sabe quién va ahí) o un arreglo con "carta" ([palo, número]),
    "bocaAbajo" y "gana". El ancho de las cartas lo da --ancho-baza, que pone quien lo usa.
    Con "reparte", las dos cartas llegan repartidas al cargar la página, como en el resto del sitio.
    Es un dibujo: quién es quién va escrito al lado, así que el lector de pantalla lo saltea.
--}}
<div {{ $attributes->class('duelo') }} aria-hidden="true">
    <div class="baza-lugar">
        @foreach ([['hueco-rival', $arriba], ['hueco-propio', $abajo]] as $orden => [$clase, $lado])
            @if ($lado === null)
                {{-- Vacío de verdad, sin un espacio adentro: así se ve el lugar marcado. --}}
                <div class="hueco-baza {{ $clase }}"></div>
            @else
                <div @class(['hueco-baza', $clase, 'se-reparte' => $reparte]) @if ($reparte) style="--orden: {{ $orden * 2 }}" @endif
                    @if ($lado['gana'] ?? false) data-gana @endif @if ($lado['bocaAbajo'] ?? false) data-boca-abajo @endif>
                    @if ($lado['bocaAbajo'] ?? false)
                        <x-dorso />
                        {{-- La cara de la carta que quedó boca abajo: la usa torneo.js para mostrar cómo se da vuelta. --}}
                        <template data-cara><x-carta :palo="$lado['carta'][0]" :numero="$lado['carta'][1]" /></template>
                    @else
                        <x-carta :palo="$lado['carta'][0]" :numero="$lado['carta'][1]" />
                    @endif
                </div>
            @endif
        @endforeach
    </div>
</div>
