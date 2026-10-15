@props(['titulo', 'bajada' => null])

{{--
    El encabezado de toda página de lectura o de elegir juego: el título, una bajada de un renglón y, a la
    derecha, lo que la página cuenta de un vistazo (cuántos desafíos van, la tira del historial). Cierra con
    el filete doble de los impresos del club, una raya gruesa y una fina, sobre el naipe o sobre el paño.
    Lo que sobra (avisos, un desplegable con la explicación larga) va en el slot, debajo de la bajada.
--}}
<header {{ $attributes->class('pagina-encabezado') }}>
    <div class="min-w-0">
        <h1 class="pagina-titulo">{{ $titulo }}</h1>

        @if ($bajada)
            <p class="pagina-bajada">{{ $bajada }}</p>
        @endif

        {{ $slot }}
    </div>

    @isset($aparte)
        <div class="pagina-aparte">{{ $aparte }}</div>
    @endisset
</header>
