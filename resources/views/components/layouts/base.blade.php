@props([
    'titulo' => null,
    'descripcion' => 'Truco argentino online, mano a mano: contra el bot o contra otra persona por un link.',
    'superficie' => 'naipe',
    'encabezado' => true,
    'pie' => true,
    // La barra de abajo del celular. Si no se dice nada, acompaña al encabezado.
    'barra' => null,
    // La apertura de la aplicación instalada (el componente "apertura"). Solo la lleva la portada, que es donde abre.
    'apertura' => false,
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
    {{--
        El alto que se ve de verdad, medido por la página y dejado en --alto. Todo lo que se mide contra el alto
        de la ventana se calcula con él (resources/css/app.css, :root): instalada en un celular, la unidad de alto de CSS
        cuenta más alto del que hay, y los cantos de la mesa o el botón de la portada quedaban tapados.
        De las medidas que da el navegador vale la más chica. Va acá, y no con el resto del JavaScript, para
        que esté antes de pintar.
    --}}
    <script>
        (function () {
            var raiz = document.documentElement;

            function medir() {
                var vista = window.visualViewport;
                var altos = [window.innerHeight, raiz.clientHeight];

                // Con el teclado abierto lo visible se achica mucho más que una barra del sistema: eso no cuenta.
                if (vista && vista.height * vista.scale > window.innerHeight * 0.75) {
                    altos.push(vista.height * vista.scale);
                }

                altos = altos.filter(function (alto) { return alto > 200; });

                if (altos.length) {
                    raiz.style.setProperty('--alto', Math.min.apply(null, altos) + 'px');
                }
            }

            medir();
            window.addEventListener('resize', medir);
            window.addEventListener('orientationchange', medir);
            window.addEventListener('pageshow', medir);

            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', medir);
            }
        })();
    </script>
    @if ($apertura)
        {{--
            La apertura se decide antes de pintar, para que siga a la pantalla del sistema sin que se vea la
            portada en el medio: solo instalada, una vez por apertura y sin movimiento reducido. Si el resto
            del JavaScript no llegara a cargar, se retira sola.
        --}}
        <script>
            (function () {
                try {
                    var instalada = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

                    if (! instalada || window.matchMedia('(prefers-reduced-motion: reduce)').matches || sessionStorage.getItem('vale-cuatro:abierta')) {
                        return;
                    }

                    sessionStorage.setItem('vale-cuatro:abierta', 'si');
                    document.documentElement.classList.add('abriendo');
                    setTimeout(function () { document.documentElement.classList.remove('abriendo'); }, 4000);
                } catch (e) {}
            })();
        </script>
    @endif
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="alternate icon" href="/favicon.ico" sizes="32x32">
    {{-- Para instalarlo como aplicación: el manifiesto dice el nombre, los colores y los íconos; iPhone pide el suyo aparte. --}}
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/iconos/icono-apple-180.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Vale Cuatro">
    {{-- La versión de lo compilado: con ella se registra el service worker (resources/js/instalar.js). --}}
    <meta name="version" content="{{ \App\Instalable\Version::actual() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- Con la barra, la página deja libre su alto para que el pie no quede tapado. --}}
<body @class(["superficie-{$superficie} min-h-(--alto)", 'max-lg:pb-[calc(4rem+env(safe-area-inset-bottom))]' => $barra])>
    @if ($apertura)
        <x-apertura />
    @endif

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
