@props(['mano'])

{{--
    La mano de la pantalla de ingreso: tres cartas boca abajo que se dan vuelta
    cuando se completa cada paso del formulario. Cada carta es
    [palo, número, condición de Alpine, si ya arranca dada vuelta].
    Es un adorno: no se anuncia. Va dos veces en la pantalla, en la mesa del
    escritorio y en la franja del celular, y solo una de las dos se ve.
--}}
<div {{ $attributes->class('abanico mano-asoma') }} aria-hidden="true">
    @foreach ($mano as $orden => [$palo, $numero, $condicion, $dadaVuelta])
        <div>
            <div class="se-reparte" style="--orden: {{ $orden }}">
                <div class="giro" data-vuelta="{{ $dadaVuelta ? 'true' : 'false' }}" :data-vuelta="({{ $condicion }}).toString()">
                    <div><x-dorso /></div>
                    <div class="giro-cara"><x-carta :palo="$palo" :numero="$numero" /></div>
                </div>
            </div>
        </div>
    @endforeach
</div>
