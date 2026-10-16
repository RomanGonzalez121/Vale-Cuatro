@php
    $romano = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IIII', 5 => 'V', 6 => 'VI', 7 => 'VII', 10 => 'X', 11 => 'XI', 12 => 'XII'][$numero];
    $delPalo = ['oro' => 'DE OROS', 'copa' => 'DE COPAS', 'espada' => 'DE ESPADAS', 'basto' => 'DE BASTOS'][$palo];
    $nombre = $figura() ? ['sota' => 'SOTA', 'caballo' => 'CABALLO', 'rey' => 'REY'][$figura()].' '.$delPalo : $delPalo;
@endphp
{{--
    La carta del mazo Tarot: como un naipe del tarot de Marsella, la baraja prima de la española. Papel crema, marco
    grueso de grabado, el número en romano en la cinta de arriba ("IIII", como en Marsella) y el nombre en la de abajo.
    Como en el tarot, la carta tiene un derecho: no se da vuelta.
--}}
<rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="#efe2c4" stroke="var(--color-tinta)" stroke-width="1.5" />
<rect x="6" y="6" width="88" height="144" fill="none" stroke="#161a18" stroke-width="1.8" />
<path d="M6 21H94M6 135H94" stroke="#161a18" stroke-width="1.3" />
<text x="50" y="17.6" text-anchor="middle" class="tarot-romano">{{ $romano }}</text>
<text x="50" y="145.4" text-anchor="middle" class="tarot-nombre">{{ $nombre }}</text>
@if ($figura())
    <use href="#{{ $simboloDeLaFigura() }}" width="100" height="156" />
@else
    <use href="#tarot-florcitas" width="100" height="156" />
@endif
@foreach ($pintas() as [$x, $y, $ancho, $alto])
    <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" />
@endforeach
