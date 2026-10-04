@props(['titulo', 'frase' => null, 'ladoPrimero' => false])

{{--
    Las pantallas de cuenta: de un lado el formulario sobre naipe, del otro la
    mesa (paño hondo) de borde a borde y a toda la altura. En el celular la
    mesa es una franja debajo del formulario, salvo que muestre algo que se ve
    mientras se escribe (ladoPrimero). El orden en el HTML no cambia: título,
    formulario, mesa. "frase" es el título grande; si no viene, es el de la pestaña.
--}}
<x-layouts.base :titulo="$titulo">
    <div {{ $attributes->class('cuenta') }}>
        <div class="cuenta-columna order-1 pb-8 pt-6 lg:pb-9 lg:pt-14">
            <h1 class="text-[clamp(2.75rem,6vw,4.75rem)] font-black leading-[0.94] tracking-[-0.035em]">{{ $frase ?? $titulo }}</h1>
            @isset($bajada)
                <p class="mt-5 max-w-[36ch] text-lg leading-relaxed sm:text-xl">{{ $bajada }}</p>
            @endisset
        </div>

        <div class="cuenta-columna order-3 pb-14 lg:pb-20">
            <div class="max-w-[27rem]">
                {{ $slot }}
            </div>
        </div>

        <aside @class(['cuenta-mesa sobre-pano bg-pano-hondo text-naipe', $ladoPrimero ? 'order-2 mb-9 lg:mb-0' : 'order-4'])>
            {{ $lado }}
        </aside>
    </div>
</x-layouts.base>
