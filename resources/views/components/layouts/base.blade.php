@props([
    'titulo' => null,
    'descripcion' => 'Truco argentino online, mano a mano: contra el bot o contra otra persona por un link.',
    'superficie' => 'naipe',
    'encabezado' => true,
    'pie' => true,
    // La barra de abajo del celular. Si no se dice nada, acompaña al encabezado.
    'barra' => null,
])

@php($barra ??= $encabezado)

<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $descripcion }}">
    <meta name="theme-color" content="{{ $superficie === 'pano' ? '#1f5a46' : '#fbfaf5' }}">
    {{-- Echo lo manda al autorizar un canal privado: sin este token el servidor rechaza el pedido. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Adónde se conecta el navegador para el tiempo real (config/tiempo_real.php). La clave es pública. --}}
    <meta name="tiempo-real" content="{{ json_encode(config('tiempo_real.navegador')) }}">
    <title>{{ $titulo ? "{$titulo} | Vale Cuatro" : 'Vale Cuatro, truco argentino online' }}</title>
    {{-- El modo se decide antes de pintar: lo que eligió el visitante o, si no eligió, lo que pide su sistema. --}}
    <script>
        (function () {
            var modo = null;
            try { modo = localStorage.getItem('vale-cuatro:modo'); } catch (e) {}
            if (modo !== 'dia' && modo !== 'noche') {
                modo = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'noche' : 'dia';
            }
            document.documentElement.dataset.modo = modo;
        })();
    </script>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="alternate icon" href="/favicon.ico" sizes="32x32">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- Con la barra, la página deja libre su alto para que el pie no quede tapado. --}}
<body @class(["superficie-{$superficie} min-h-dvh", 'max-lg:pb-[calc(4rem+env(safe-area-inset-bottom))]' => $barra])>
    <x-mazo.sprite />
    <a href="#contenido" class="saltar">Saltar al contenido</a>

    @if ($encabezado)
        <x-encabezado />
    @endif

    @if ($barra)
        <x-barra-inferior />
    @endif

    {{--
        Lo que se sale por los costados se recorta: una carta que llega volando en el reparto no puede
        ensanchar la página. En un celular eso achicaba todo y dejaba corta la barra de abajo.
    --}}
    <main id="contenido" class="overflow-x-clip">
        @if ($encabezado)
            <x-aviso />
        @endif

        {{ $slot }}
    </main>

    @if ($pie)
        <x-pie />
    @endif
</body>
</html>
