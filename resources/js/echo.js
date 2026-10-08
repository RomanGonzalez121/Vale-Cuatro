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
 * La conexión, creada la primera vez que se pide.
 */
export function conectarEcho() {
    if (conexion !== null) {
        return conexion;
    }

    window.Pusher = Pusher;

    conexion = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
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
 * - alCaer corre cuando la conexión se pierde o no se puede abrir.
 *
 * Si no hay tiempo real (Reverb apagado o sin configurar) no falla: avisa con alCaer y quien llama sigue
 * preguntando por su cuenta.
 */
export function escucharPartida(partida, { alAvisar, alConectar, alCaer }) {
    try {
        const echo = conectarEcho();
        const enlace = echo.connector.pusher.connection;

        echo.private(`partida.${partida}`).listen('.partida.actualizada', alAvisar);
        enlace.bind('connected', alConectar);

        for (const caida of ['disconnected', 'unavailable', 'failed']) {
            enlace.bind(caida, alCaer);
        }
    } catch {
        alCaer();
    }
}
