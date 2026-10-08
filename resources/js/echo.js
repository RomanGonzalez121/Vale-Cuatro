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
