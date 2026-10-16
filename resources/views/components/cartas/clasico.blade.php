@php
    // El marco de la pinta, como en la baraja castellana: oro sin cortes, copa uno, espada dos, basto tres.
    $cortes = ['oro' => [], 'copa' => [50], 'espada' => [36, 64], 'basto' => [30, 50, 70]][$palo];
    $tramos = ['M7.5 7.5V148.5', 'M92.5 7.5V148.5'];
    foreach ([7.5, 148.5] as $y) {
        $desde = 7.5;
        foreach ($cortes as $corte) {
            $tramos[] = "M{$desde} {$y}H".($corte - 3.5);
            $desde = $corte + 3.5;
        }
        $tramos[] = "M{$desde} {$y}H92.5";
    }
@endphp
{{--
    La carta del mazo Clásico: el aire de la baraja castellana, dibujado de nuevo. Naipe blanco, el marco de la
    pinta con sus cortes, el número arriba a la izquierda y los palos y las figuras en colores planos con su contorno.
--}}
<rect x="0.75" y="0.75" width="98.5" height="154.5" rx="8" fill="#fbfaf5" stroke="var(--color-tinta)" stroke-width="1.5" />
<path d="{{ implode('', $tramos) }}" fill="none" stroke="var(--color-tinta)" stroke-width="1.1" />
{{-- El número y, debajo, el palo chiquito: las figuras visten igual en los cuatro palos y así no se confunden. --}}
@foreach ([0, 180] as $giro)
    <g transform="rotate({{ $giro }} 50 78)">
        <text x="11.5" y="23.5" class="clasico-numero">{{ $numero }}</text>
        <use href="#palo-{{ $palo }}" x="11.6" y="26.5" width="7" height="10.5" class="tinta-{{ $palo }}" />
    </g>
@endforeach
@if ($figura())
    <use href="#{{ $simboloDeLaFigura() }}" width="100" height="156" />
@endif
@foreach ($pintas() as [$x, $y, $ancho, $alto])
    <use href="#{{ $simboloDelPalo() }}" x="{{ $x }}" y="{{ $y }}" width="{{ $ancho }}" height="{{ $alto }}" />
@endforeach
