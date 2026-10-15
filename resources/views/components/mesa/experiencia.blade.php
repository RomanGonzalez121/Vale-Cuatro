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

    <p class="mt-3 leading-snug" x-show="tira.subio && tira.contada" x-cloak x-transition:enter="aparece" x-transition:enter-start="opacity-0">
        <strong class="font-bold" x-text="`Subiste al nivel ${experiencia?.nivelAhora}:`"></strong>
        <template x-for="(premio, numero) in experiencia?.premios ?? []" :key="premio.nombre">
            <span><span x-text="numero > 0 ? ', ' : ' '"></span><span x-text="`${tipoDePremio[premio.tipo].toLowerCase()} ${premio.nombre}${premio.listo ? '' : ' (próximamente)'}`"></span></span>
        </template>
    </p>
</div>
