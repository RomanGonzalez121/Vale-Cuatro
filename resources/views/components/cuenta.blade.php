@props(['titulo', 'frase' => null, 'ladoPrimero' => false, 'mesaSoloEscritorio' => false, 'franjaAlta' => false])

{{--
    Las pantallas de cuenta: de un lado el formulario sobre naipe, del otro la
    mesa (paño hondo) de borde a borde y a toda la altura. En el celular la
    mesa es una franja debajo del formulario, salvo que muestre algo que se ve
    mientras se escribe (ladoPrimero). El orden en el HTML no cambia: título,
    formulario, mesa. "frase" es el título grande; si no viene, es el de la pestaña.
    Si lo que muestra la mesa tiene que verse mientras se escribe en el celular, va
    en "franja": una tira angosta fija arriba de todo, que no se mueve al bajar.
    Con "mesaSoloEscritorio" la mesa entera no aparece en el celular, porque la
    franja ya la reemplaza. "franjaAlta" es para la que lleva dos renglones.
--}}
<x-layouts.base :titulo="$titulo">
    <div {{ $attributes->class('cuenta') }}>
        {{-- En escritorio, los márgenes y el tamaño del título se miden contra el alto de la ventana (app.css). --}}
        <div class="cuenta-columna cuenta-titulo order-1 pb-8 pt-6">
            <h1 class="cuenta-frase text-[clamp(2.75rem,6vw,4.75rem)] font-black leading-[0.94] tracking-[-0.035em]">{{ $frase ?? $titulo }}</h1>
            @isset($bajada)
                <p class="mt-5 max-w-[36ch] text-lg leading-relaxed sm:text-xl">{{ $bajada }}</p>
            @endisset
        </div>

        {{-- Solo en el celular: algo de la mesa que tiene que verse mientras se escribe. Va fija arriba de todo. --}}
        @isset($franja)
            <div @class(['cuenta-franja sobre-pano bg-pano-hondo text-naipe lg:hidden', 'cuenta-franja-alta' => $franjaAlta])>
                {{ $franja }}
            </div>
        @endisset

        <div class="cuenta-columna cuenta-formulario order-3 pb-14">
            {{-- Con este ancho, el link de abajo entra al lado del botón y el formulario gana un renglón. --}}
            <div class="max-w-[29rem]">
                {{ $slot }}
            </div>
        </div>

        <aside @class([
            'cuenta-mesa sobre-pano bg-pano-hondo text-naipe',
            $ladoPrimero ? 'order-2 mb-9 lg:mb-0' : 'order-4',
            'hidden lg:flex' => $mesaSoloEscritorio,
        ])>
            {{ $lado }}
        </aside>
    </div>
</x-layouts.base>
