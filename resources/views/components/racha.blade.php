@props(['cantidad'])

@php
    // El mismo grupo de cinco del tanteador: cuatro fósforos en cuadrado y uno cruzado.
    $grupo = [
        ['M4 19.5V8', 4, 4.6],
        ['M8.5 4H20', 23.4, 4],
        ['M24 8.5V20', 24, 23.4],
        ['M19.5 24H8', 4.6, 24],
        ['M9 19 17.6 10.4', 19.8, 8.2],
    ];
@endphp

{{-- Una racha dibujada con fósforos. El palito toma el color del texto; la cabeza va en Copa. --}}
<span {{ $attributes->class('inline-flex items-center gap-[0.2em] align-middle') }}>
    <span class="sr-only">{{ match (true) { $cantidad === 0 => 'Sin racha', $cantidad === 1 => '1 ganada seguida', default => "{$cantidad} ganadas seguidas" } }}</span>
    @if ($cantidad === 0)
        <span aria-hidden="true" class="text-sm opacity-70">sin racha</span>
    @endif
    @for ($g = 0; $g < intdiv($cantidad + 4, 5); $g++)
        <svg viewBox="0 0 28 28" class="size-[1.45em] flex-none" aria-hidden="true">
            @foreach ($grupo as $i => [$palito, $cx, $cy])
                @if ($g * 5 + $i < $cantidad)
                    <path d="{{ $palito }}" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" />
                    <circle cx="{{ $cx }}" cy="{{ $cy }}" r="2.6" fill="var(--color-copa)" />
                @endif
            @endforeach
        </svg>
    @endfor
</span>
