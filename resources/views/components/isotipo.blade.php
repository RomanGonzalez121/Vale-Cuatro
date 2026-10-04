@php
    // Los cuatro fósforos del isotipo, en el orden en que se cuentan: [palito, cabeza x, cabeza y].
    $fosforos = [
        ['M4 19.5V8', 4, 4.6],
        ['M8.5 4H20', 23.4, 4],
        ['M24 8.5V20', 24, 23.4],
        ['M19.5 24H8', 4.6, 24],
    ];
@endphp

{{-- Va dibujado en línea, y no con <use>, para poder animar cada fósforo por separado. --}}
<svg {{ $attributes->class('isotipo shrink-0 overflow-visible') }} viewBox="0 0 28 28" aria-hidden="true" focusable="false">
    @foreach ($fosforos as [$palito, $cx, $cy])
        <g class="iso-fosforo">
            <path d="{{ $palito }}" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="2.4" fill="currentColor" />
            <circle class="iso-llama" cx="{{ $cx }}" cy="{{ $cy }}" r="2.4" />
        </g>
    @endforeach
</svg>
