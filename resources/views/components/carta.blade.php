<svg {{ $attributes->class(['carta', $tinta()]) }} viewBox="0 0 100 156" role="img" aria-label="{{ $nombre() }}" data-carta="{{ $identificador() }}">
    @if ($estilo() !== 'fabrica')
        @include('components.cartas.'.$estilo())
    @else
    <rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="var(--color-naipe)" stroke="var(--color-tinta)" stroke-width="1.5" />
    <path d="{{ $marco() }}" fill="none" stroke="var(--color-tinta)" stroke-width="1" />
    <text x="13" y="25" class="carta-indice">{{ $numero }}</text>
    <text x="13" y="25" class="carta-indice" transform="rotate(180 50 78)">{{ $numero }}</text>
    @if ($figura())
        <use href="#{{ $simboloDeLaFigura() }}" width="100" height="156" class="tinta-{{ $palo }}" />
    @endif
    @foreach ($pintas() as [$x, $y, $ancho, $alto])
        <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" />
    @endforeach
    @endif
</svg>
