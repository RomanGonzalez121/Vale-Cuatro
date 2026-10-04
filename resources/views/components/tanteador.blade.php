@props(['nombre', 'puntos' => 0, 'modelo' => null, 'rotulos' => false, 'nombreVivo' => null, 'claseNombre' => 'text-sm font-semibold'])

{{--
    Tres grupos de malas y tres de buenas; cada grupo de cinco lo dibuja <x-fosforos>.
    Con "modelo" (una expresión de Alpine) los fósforos nuevos caen solos al sumar.
    Con "nombreVivo" (otra expresión) el nombre sigue lo que se va escribiendo.
--}}

<div {{ $attributes->class('sobre-pano bg-pano-hondo text-naipe') }}>
    <p class="flex items-baseline gap-2 leading-none">
        <span class="{{ $claseNombre }}" @if ($nombreVivo) x-text="{{ $nombreVivo }}" @endif>{{ $nombre }}</span>
        <span class="text-2xl font-black tabular-nums text-oro" @if ($modelo) x-text="{{ $modelo }}" @endif>{{ $puntos }}</span>
        <span class="sr-only">puntos</span>
    </p>

    <div class="mt-2 flex items-center gap-[0.2em]" aria-hidden="true">
        @for ($g = 0; $g < 6; $g++)
            @if ($g === 3)
                <span class="mx-[0.25em] h-[1.3em] w-px bg-naipe/45"></span>
            @endif
            <x-fosforos :grupo="$g" :puntos="$puntos" :modelo="$modelo" class="size-[1.45em]" />
        @endfor
    </div>

    @if ($rotulos)
        <p class="mt-1.5 flex text-xs text-naipe/80" aria-hidden="true">
            <span class="w-[calc(1.45em*3*1.5)]">malas</span>
            <span>buenas</span>
        </p>
    @endif
</div>
