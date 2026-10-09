/*
 | La conexión de tiempo real con Reverb.
 |
 | No se abre sola en todas las páginas: la pide quien la necesita (la mesa y la
 | sala de espera). Así la portada, el ranking o el historial nunca abren un
 | WebSocket ni fallan si Reverb no está prendido.
 |
 | Por el WebSocket no viajan cartas. Solo avisa que hay novedades; lo que pasó
 | se pide por HTTP, donde el servidor ya filtra por asiento.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let conexion = null;

/**
 * Adónde conectarse. Lo dice la página (la etiqueta "tiempo-real" del encabezado, que escribe el servidor)
 * y no viene compilado acá adentro: así el mismo JavaScript sirve en cualquier dirección donde se publique
 * el sitio. Sin clave no hay tiempo real configurado, y se avisa con un error para que quien llama siga
 * preguntando por su cuenta.
 */
function dondeConectarse() {
    const datos = JSON.parse(document.querySelector('meta[name="tiempo-real"]')?.content || '{}');

    if (! datos.clave) {
        throw new Error('El tiempo real no está configurado.');
    }

    const seguro = datos.esquema === 'https';
    const puerto = Number(datos.puerto) || (seguro ? 443 : 80);

    return {
        key: datos.clave,
        // Sin host, es el mismo servidor que entregó la página.
        wsHost: datos.host || window.location.hostname,
        wsPort: puerto,
        wssPort: puerto,
        forceTLS: seguro,
    };
}

/**
 * La conexión, creada la primera vez que se pide.
 */
export function conectarEcho() {
    if (conexion !== null) {
        return conexion;
    }

    const destino = dondeConectarse();

    window.Pusher = Pusher;

    conexion = new Echo({
        broadcaster: 'reverb',
        ...destino,
        enabledTransports: ['ws', 'wss'],
    });

    return conexion;
}

/**
 * Escucha los avisos de una partida entre personas, por su canal privado. Los usan la sala de espera y la mesa.
 *
 * - alAvisar recibe lo que manda el servidor, que es solo el número del último evento;
 * - alConectar corre al conectar y cada vez que se reconecta: ahí conviene ponerse al día, por si un aviso
 *   pasó mientras no había conexión;
 * - alCaer corre cuando la conexión se pierde o no se puede abrir;
 * - alRevancha, si se pasa, corre cuando cambia algo de la revancha de esa partida. Tampoco trae datos.
 *
 * Si no hay tiempo real (Reverb apagado o sin configurar) no falla: avisa con alCaer y quien llama sigue
 * preguntando por su cuenta.
 */
export function escucharPartida(partida, { alAvisar, alConectar, alCaer, alRevancha = null }) {
    try {
        const echo = conectarEcho();
        const enlace = echo.connector.pusher.connection;
        const canal = echo.private(`partida.${partida}`).listen('.partida.actualizada', alAvisar);

        if (alRevancha) {
            canal.listen('.revancha.actualizada', alRevancha);
        }

        enlace.bind('connected', alConectar);

        for (const caida of ['disconnected', 'unavailable', 'failed']) {
            enlace.bind(caida, alCaer);
        }
    } catch {
        alCaer();
    }
}
