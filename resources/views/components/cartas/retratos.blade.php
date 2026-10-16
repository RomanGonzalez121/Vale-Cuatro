{{--
    La carta del mazo Retratos: un retrato de estudio de 1900 (cabinet card) pegado en el álbum. El cartón en el color
    hondo del palo, el número en crema sobre el cartón, la foto en sepia con el óvalo claro de los retratos de época y
    sus esquineros, y abajo el pie impreso del estudio.
--}}
<rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" class="retrato-carton-{{ $palo }}" stroke="var(--color-tinta)" stroke-width="1.5" />
<use href="#retrato-foto" width="100" height="156" />
@foreach ([0, 180] as $giro)
    <text x="13" y="14.4" text-anchor="middle" class="retrato-numero" transform="rotate({{ $giro }} 50 78)">{{ $numero }}</text>
@endforeach
<use href="#retrato-pie" width="100" height="156" />
@if ($figura())
    <use href="#{{ $simboloDeLaFigura() }}" width="100" height="156" />
@endif
@foreach ($pintas() as [$x, $y, $ancho, $alto])
    <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" />
@endforeach
