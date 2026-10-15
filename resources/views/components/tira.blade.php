@props(['lleva' => 0, 'modelo' => null])

{{--
    La tira de un nivel: diez fósforos en dos grupos de cinco, como el tanteador. Con "modelo" (una expresión
    de Alpine) los fósforos nuevos caen solos al sumar, igual que los puntos. Es dibujo: el número va escrito al lado.
--}}
<div {{ $attributes->class('flex items-center gap-[0.2em]') }} aria-hidden="true">
    <x-fosforos :grupo="0" :puntos="$lleva" :modelo="$modelo" class="size-[1.45em]" />
    <x-fosforos :grupo="1" :puntos="$lleva" :modelo="$modelo" class="size-[1.45em]" />
</div>
