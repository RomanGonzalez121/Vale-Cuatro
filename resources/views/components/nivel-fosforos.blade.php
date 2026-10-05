@props(['nivel', 'puesto' => true, 'modelo' => null])

{{--
    La dificultad del bot contada con fósforos, como un punto del tanteador:
    uno es Fácil, dos Intermedio y tres Difícil. Son gruesos y de cabeza grande,
    como los de la carta de modo. Debajo de cada uno va la marca de su lugar,
    así se lee cuántos tiene un nivel aunque no esté elegido. Con "modelo"
    (una expresión de Alpine) caen de a uno cuando pasa a ser cierta.
--}}

<span {{ $attributes->class('nivel-fosforos flex gap-[0.2em]') }} aria-hidden="true">
    @for ($i = 0; $i < $nivel->value; $i++)
        <svg viewBox="0 0 10 24" class="h-[1.5em] w-[0.625em] flex-none overflow-visible" style="--orden: {{ $i }}">
            <g class="fosforo-lugar" fill="none" stroke-linecap="round">
                <path d="M5 21.5V10.5" stroke-width="3.2" />
                <path d="M5 4.4V4.4" stroke-width="7.6" />
            </g>
            <g @class(['fosforo', 'puesto' => $puesto]) @if ($modelo) :class="{ puesto: {{ $modelo }} }" @endif>
                <path d="M5 21.5V10.5" fill="none" stroke="var(--color-fosforo)" stroke-width="3.2" stroke-linecap="round" />
                <circle cx="5" cy="4.4" r="3.8" fill="var(--color-copa)" />
            </g>
        </svg>
    @endfor
</span>
