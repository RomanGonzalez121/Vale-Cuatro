{{--
    La carta del mazo Estampilla: un sello de correo pegado en el sobre. El sobre asoma alrededor del dentado y el
    sello va impreso en una sola tinta, la de su palo, como los sellos clásicos (cada valor en su color): la franja
    con "Vale Cuatro" y el valor en su círculo, arriba y abajo; en el medio la viñeta de líneas finas. Las figuras son
    el cartero, el correo de posta y el rey en su camafeo. El matasellos pisa la esquina.
--}}
<use href="#estampilla-sobre" width="100" height="156" />
<g class="estampilla-{{ $palo }}">
    <use href="#estampilla-marco" width="100" height="156" />
    @foreach ([0, 180] as $giro)
        <g transform="rotate({{ $giro }} 50 78)">
            <circle cx="18" cy="16.5" r="8.6" fill="currentColor" />
            <circle cx="18" cy="16.5" r="7.2" fill="none" stroke="#f4eedc" stroke-width=".5" />
            <text x="18" y="20.4" text-anchor="middle" class="estampilla-numero">{{ $numero }}</text>
            <text x="56" y="18.7" text-anchor="middle" class="estampilla-pais">Vale Cuatro</text>
        </g>
    @endforeach
    @if ($figura())
        <use href="#{{ $simboloDeLaFigura() }}" width="100" height="156" />
    @endif
    @foreach ($pintas() as [$x, $y, $ancho, $alto])
        <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" />
    @endforeach
</g>
<use href="#estampilla-matasellos" width="100" height="156" />
