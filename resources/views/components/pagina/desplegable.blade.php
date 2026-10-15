@props(['titulo'])

{{--
    Una explicación larga que no todos necesitan ("Cómo se cuenta"): cerrada, es un renglón; abierta, el
    texto entra con un fundido corto. La marca es una cruz de dos trazos que gira y queda en equis.
    Es un <details> de verdad: se abre con teclado y el lector de pantalla dice si está abierto.
--}}
<details {{ $attributes->class('desplegable') }}>
    <summary class="desplegable-titulo">
        <svg class="desplegable-marca" viewBox="0 0 16 16" aria-hidden="true">
            <path d="M8 2v12M2 8h12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
        <span class="enlace-nav">{{ $titulo }}</span>
    </summary>

    <div class="desplegable-cuerpo">
        {{ $slot }}
    </div>
</details>
