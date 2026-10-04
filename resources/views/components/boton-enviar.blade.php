@props(['texto', 'enviando', 'icono' => null])

{{--
    Botón de envío que avisa que está trabajando: al enviar el formulario
    cambia el texto y se desactiva, para que no se envíe dos veces. Si se
    vuelve atrás con el navegador, se rearma.
--}}
<button type="submit" {{ $attributes->class('boton') }}
    x-data="{ enviando: false }" x-init="$el.form.addEventListener('submit', () => enviando = true)"
    @pageshow.window="enviando = false" :disabled="enviando">
    @if ($icono)
        <x-icono :nombre="$icono" />
    @endif
    <span x-text="enviando ? {{ Js::from($enviando) }} : {{ Js::from($texto) }}">{{ $texto }}</span>
</button>
