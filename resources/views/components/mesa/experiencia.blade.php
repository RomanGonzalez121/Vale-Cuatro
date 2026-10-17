{{--
    Lo que la partida le anotó al nivel, en el final: en un renglón el nivel, la tira de diez fósforos y cuántos
    anotó; si subió, en otro renglón lo que destrabó. Los fósforos ganados caen de a uno después de contarse los
    tanteadores (mesa.js, contarLaTira). Lo que todavía no está dibujado se nombra igual, con "próximamente".
    Va en las dos mesas.
--}}
<div class="mt-5 border-t border-naipe/20 pt-4" x-show="experiencia" x-cloak>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <p class="text-base font-semibold leading-none">Nivel <span class="text-2xl font-black tabular-nums text-oro" x-text="tira.nivel"></span></p>
        <x-tira modelo="tira.lleva" class="text-[1.35rem] sm:text-[1.5rem]" />
        <p class="ml-auto font-semibold leading-none" x-text="fraseDeFosforos"></p>
    </div>

    {{-- Los sellos de la libreta que dio la partida: golpean el papel de a uno después de la tira (mesa.js, sellarLaLibreta). --}}
    {{-- Siempre en un renglón, para no empujar los botones del final: hasta tres sellos y, si hay más, cuántos. --}}
    <div class="mt-3 flex items-center gap-x-4 overflow-hidden" x-show="sellando" x-cloak>
        <template x-for="sello in (experiencia?.sellos ?? []).slice(0, 3)" :key="`${sello.logro}-${sello.escalon}`">
            <p class="flex min-w-0 items-center gap-2" data-sello-nuevo>
                <span class="final-sello shrink-0" x-html="sello.dibujo"></span>
                <span class="text-sm font-semibold leading-tight" x-text="sello.nombre"></span>
            </p>
        </template>
        <p class="shrink-0 text-sm font-semibold" x-show="(experiencia?.sellos?.length ?? 0) > 3" x-text="`y ${experiencia?.sellos?.length - 3} más en tu libreta`"></p>
    </div>

    {{--
        Lo que dio la partida (del nivel, la gira o un logro), después de los sellos: cartas que llegan boca abajo y se
        dan vuelta de a una (mesa.js, mostrarLosPremios). Entran tres; si hay más, el link las cuenta. Tocar lo apura.
        Si las cartas no llegan, se dice con palabras.
    --}}
    <div class="mt-4" x-show="premios.aLaVista" x-cloak @click="$event.target.closest('a') || apurarLosPremios()">
        <p class="font-bold leading-snug">
            <span x-text="experiencia?.nivelAhora > experiencia?.nivelAntes ? `Subiste al nivel ${experiencia?.nivelAhora}` : 'Nuevo en tu colección'"></span><span x-show="premios.enPalabras && experiencia?.premios?.length">:</span>
            <template x-if="premios.enPalabras">
                <span class="font-normal" x-text="' ' + (experiencia?.premios ?? []).map((premio) => `${tipoDePremio[premio.tipo].toLowerCase()} ${premio.nombre}`).join(', ')"></span>
            </template>
        </p>
        <div x-ref="premios"></div>
        <a :href="premios.enlace" class="enlace-nav mt-4 inline-block font-semibold" x-show="premios.enlace" x-text="textoDeLaColeccion"></a>
    </div>
</div>
