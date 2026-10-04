@props([
    'titulo' => null,
    'descripcion' => 'Truco argentino online, mano a mano: contra el bot o contra otra persona por un link.',
    'superficie' => 'naipe',
    'encabezado' => true,
    'pie' => true,
])

<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $descripcion }}">
    <meta name="theme-color" content="{{ $superficie === 'pano' ? '#1f5a46' : '#fbfaf5' }}">
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
<body class="superficie-{{ $superficie }} min-h-dvh">
    <x-mazo.sprite />
    <a href="#contenido" class="saltar">Saltar al contenido</a>

    @if ($encabezado)
        <x-encabezado />
    @endif

    <main id="contenido">
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
