{{--
    El apodo, puesto donde lo va a ver el rival: en el tanteador. Lee la
    variable "apodo" de Alpine de la pantalla que lo usa, así cambia mientras
    se escribe. Los puntos son de ejemplo, y lo dice.
--}}
<div class="relative my-auto px-7 py-9 sm:px-12 sm:py-12 lg:px-16">
    <p class="font-semibold leading-snug lg:text-lg">Así queda tu apodo en el tanteador.</p>

    <div class="mt-6 flex flex-col gap-6 text-[clamp(1.1rem,2.7vw,2.25rem)] lg:mt-9 lg:gap-9">
        <x-tanteador nombre="Tu apodo" nombre-vivo="apodo.trim() || 'Tu apodo'" :puntos="12"
            clase-nombre="break-all text-[max(1.0625rem,0.72em)] font-extrabold tracking-tight" />
        <x-tanteador nombre="Bot, nivel 2" :puntos="9" clase-nombre="text-[max(1.0625rem,0.72em)] font-extrabold tracking-tight" />
    </div>

    <p class="mt-6 max-w-[40ch] text-sm leading-relaxed lg:mt-9">Los puntos son de ejemplo: cada fósforo es uno, de a cinco por grupo.</p>

    {{ $slot }}
</div>
