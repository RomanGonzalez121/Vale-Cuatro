/*
 | El service worker de Vale Cuatro: un programita que el navegador deja corriendo aparte de la página.
 | Hace tres cosas y nada más:
 |
 | 1. Guarda en el equipo lo que no cambia: los estilos, los scripts y las fuentes compilados, los íconos
 |    y la página de "sin conexión". Así el sitio instalado abre rápido.
 | 2. Si se pide una página y no hay conexión, muestra la de "sin conexión" en vez del error del navegador.
 | 3. Muestra los avisos que manda el servidor ("Te toca") y, al tocarlos, lleva a la mesa.
 |
 | Lo que NO hace, a propósito: no guarda ninguna página del sitio fuera de esa, ni ninguna respuesta del
 | servidor. Una partida, una mesa o un pedido con cartas van siempre a la red y nunca quedan en el
 | equipo: el truco se juega en vivo, y lo guardado podría mostrar cartas viejas o de otra persona.
 |
 | La página lo registra con la versión de lo compilado en la dirección (sw.js?v=...). Cuando se publica
 | una versión nueva esa dirección cambia, el navegador instala este archivo de nuevo y se borra lo
 | guardado por la versión anterior.
 */

const VERSION = new URL(self.location.href).searchParams.get('v') || 'sin-version';
const GUARDADO = `vale-cuatro-${VERSION}`;
const SIN_CONEXION = '/sin-conexion';

// Lo único que se guarda al pasar: archivos que no dependen de quién los pide.
const SUELTOS = ['/favicon.svg', '/favicon.ico', '/manifest.json'];

function seGuarda(url) {
    return url.origin === self.location.origin
        && (url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/iconos/') || SUELTOS.includes(url.pathname));
}

/**
 * Las direcciones de lo compilado que nombra un texto (la página o una hoja de estilos), resueltas contra
 * la dirección de ese texto: una hoja de estilos nombra sus fuentes con direcciones relativas.
 */
function compiladosDe(texto, base) {
    const direcciones = [...texto.matchAll(/(?:href|src)="([^"]+)"|url\(["']?([^"')]+)["']?\)/g)]
        .map((hallazgo) => new URL(hallazgo[1] ?? hallazgo[2], base))
        .filter(seGuarda)
        .map((url) => url.pathname);

    return [...new Set(direcciones)];
}

/**
 * Al instalarse deja guardada la página de "sin conexión" con todo lo que necesita para verse bien:
 * sus estilos, su script y las fuentes que esos estilos piden.
 */
async function guardarLoDeSinConexion() {
    const guardado = await caches.open(GUARDADO);
    const pagina = await fetch(SIN_CONEXION, { cache: 'reload', credentials: 'omit' });

    if (! pagina.ok) {
        throw new Error('No se pudo bajar la página de sin conexión.');
    }

    await guardado.put(SIN_CONEXION, pagina.clone());

    const piezas = compiladosDe(await pagina.text(), self.location.origin);

    for (const pieza of piezas) {
        const respuesta = await fetch(pieza, { cache: 'reload' });

        if (! respuesta.ok) {
            continue;
        }

        await guardado.put(pieza, respuesta.clone());

        // De una hoja de estilos, además, las fuentes que nombra.
        if (pieza.endsWith('.css')) {
            const fuentes = compiladosDe(await respuesta.text(), new URL(pieza, self.location.origin));

            await Promise.all(fuentes.map(async (fuente) => {
                const archivo = await fetch(fuente, { cache: 'reload' });

                if (archivo.ok) {
                    await guardado.put(fuente, archivo);
                }
            }));
        }
    }
}

self.addEventListener('install', (evento) => {
    evento.waitUntil(guardarLoDeSinConexion().then(() => self.skipWaiting()));
});

self.addEventListener('activate', (evento) => {
    // Lo que guardó una versión anterior ya no sirve: se borra.
    evento.waitUntil(
        caches.keys()
            .then((nombres) => Promise.all(nombres.filter((nombre) => nombre !== GUARDADO).map((nombre) => caches.delete(nombre))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (evento) => {
    const pedido = evento.request;

    if (pedido.method !== 'GET') {
        return;
    }

    // Una página: siempre de la red. Solo si la red no contesta se muestra la de "sin conexión".
    if (pedido.mode === 'navigate') {
        evento.respondWith(fetch(pedido).catch(() => caches.match(SIN_CONEXION, { cacheName: GUARDADO })));

        return;
    }

    // Lo compilado y los íconos: primero lo guardado, y lo que falte se baja y se guarda.
    if (seGuarda(new URL(pedido.url))) {
        evento.respondWith(
            caches.open(GUARDADO).then(async (guardado) => {
                const encontrado = await guardado.match(pedido);

                if (encontrado) {
                    return encontrado;
                }

                const respuesta = await fetch(pedido);

                if (respuesta.ok && respuesta.type === 'basic') {
                    guardado.put(pedido, respuesta.clone());
                }

                return respuesta;
            }),
        );
    }

    // Todo lo demás (la mesa, sus pedidos, cualquier respuesta del servidor) sigue de largo a la red y no se guarda.
});

// Los avisos

self.addEventListener('push', (evento) => {
    let aviso = {};

    try {
        aviso = evento.data ? evento.data.json() : {};
    } catch {
        // Un aviso que no se entiende se muestra igual, con el texto de fábrica.
    }

    evento.waitUntil(
        self.registration.showNotification(aviso.titulo || 'Vale Cuatro', {
            body: aviso.cuerpo || 'Hay novedades en tu partida.',
            icon: '/iconos/icono-192.png',
            badge: '/iconos/insignia-96.png',
            lang: 'es-AR',
            // Un aviso nuevo de la misma partida reemplaza al anterior en vez de apilarse.
            tag: aviso.etiqueta || 'vale-cuatro',
            renotify: true,
            data: { url: aviso.url || '/mesa' },
        }),
    );
});

self.addEventListener('notificationclick', (evento) => {
    const destino = new URL(evento.notification.data?.url || '/mesa', self.location.origin);

    evento.notification.close();
    evento.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((ventanas) => {
            // Si el sitio ya está abierto se usa esa ventana; si no, se abre una.
            const abierta = ventanas.find((ventana) => new URL(ventana.url).origin === destino.origin);

            if (! abierta) {
                return self.clients.openWindow(destino.href);
            }

            // Una ventana que este programa todavía no maneja no se puede llevar a otra dirección: ahí se abre una nueva.
            return abierta.focus().then((ventana) => ventana.navigate(destino.href)).catch(() => self.clients.openWindow(destino.href));
        }),
    );
});
