{{-- El canto, con la misma pieza que en la mesa, sobre las cartas jugadas. Uno largo puede venir en dos renglones. --}}
<div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center" aria-hidden="true">
    <p class="voz canto whitespace-pre text-center" :data-visible="(estado.canto !== null).toString()" :data-quien="voz.quien" :style="{ fontSize: `${voz.letra}px` }"
        :class="{ 'canto-oro': voz.tono === 'oro', 'canto-ficha canto-copa': voz.tono === 'copa', 'canto-ficha canto-basto': voz.tono === 'basto' }"
        x-text="voz.texto"></p>
</div>
