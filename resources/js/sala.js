/*
 | La sala de espera de una partida entre personas.
 |
 | Quien la abrió manda el link y espera. Cuando se sienta alguien, el servidor manda un aviso por el
 | WebSocket (un canal privado de la partida, sin cartas ni datos: solo "hay novedades") y la página
 | pregunta qué pasó. Esa pregunta también se hace sola cada tanto, por si el aviso no llega: con el
 | WebSocket andando cada 15 segundos y, si se cayó o nunca conectó, cada 4. Al volver a estar a la vista
 | o al reconectarse se pregunta de inmediato.
 |
 | Cuando se sienta, se reparten los tres dorsos en el lugar del rival, que es el único movimiento de la
 | pantalla, y pasa a la mesa. Todo lo que decide el servidor llega por esa pregunta: la página no sabe
 | quién es el rival ni si la sala sigue abierta hasta que se lo dicen.
 */

import { movimientoReducido } from './cartas';
import { conectarEcho } from './echo';

/** Cada cuánto pregunta si no le avisaron: con el WebSocket andando es solo un respaldo. */
const CADA_EN_VIVO = 15000;
const CADA_SIN_AVISOS = 4000;

/** Lo que se ve el reparto antes de pasar a la mesa. Con movimiento reducido alcanza con leer el aviso. */
const PAUSA_DEL_REPARTO = 1600;
const PAUSA_REDUCIDA = 700;

export default function sala({ estado, mesa, enlace, partida }) {
    return {
        // Cómo salió lo último que se intentó con el link: 'copiado', 'elegido' (se dejó seleccionado para copiar a mano) o nada.
        copiado: null,
        puedeCompartir: typeof navigator.share === 'function',
        // Quien se sentó, cuando ya llegó.
        rival: null,
        llego: false,
        enVivo: false,
        relojDelAviso: null,
        espera: null,

        init() {
            this.escuchar();
            this.consultar();

            // Si la pestaña estaba dormida, se entera apenas vuelve.
            document.addEventListener('visibilitychange', () => {
                if (! document.hidden && ! this.llego) {
                    this.consultar();
                }
            });
        },

        /**
         * Se suscribe al canal de la partida. Si no hay tiempo real (Reverb apagado, o el navegador no puede conectar),
         * no pasa nada: queda la pregunta cada pocos segundos.
         */
        escuchar() {
            try {
                const echo = conectarEcho();
                const conexion = echo.connector.pusher.connection;

                echo.private(`partida.${partida}`).listen('.partida.actualizada', () => this.consultar());

                // Al conectar (y al reconectar) se pone al día, por si el aviso pasó mientras no estaba.
                conexion.bind('connected', () => {
                    this.enVivo = true;
                    this.consultar();
                });

                for (const caida of ['disconnected', 'unavailable', 'failed']) {
                    conexion.bind(caida, () => (this.enVivo = false));
                }
            } catch {
                this.enVivo = false;
            }
        },

        async consultar() {
            clearTimeout(this.espera);

            if (this.llego) {
                return;
            }

            try {
                const respuesta = await fetch(estado, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

                if (respuesta.ok) {
                    const datos = await respuesta.json();

                    if (datos.empezo) {
                        this.sentarse(datos.rival);

                        return;
                    }

                    // La sala se cerró (venció, o se cerró desde otra pestaña): el servidor sabe adónde ir.
                    if (datos.cerrada) {
                        window.location.assign(mesa);

                        return;
                    }
                }
            } catch {
                // Sin conexión por un rato: se vuelve a intentar.
            }

            this.espera = setTimeout(() => this.consultar(), this.enVivo ? CADA_EN_VIVO : CADA_SIN_AVISOS);
        },

        sentarse(apodo) {
            if (this.llego) {
                return;
            }

            this.rival = apodo;
            this.llego = true;

            setTimeout(() => window.location.assign(mesa), movimientoReducido.matches ? PAUSA_REDUCIDA : PAUSA_DEL_REPARTO);
        },

        async copiar() {
            try {
                await navigator.clipboard.writeText(enlace);
                this.avisar('copiado');
            } catch {
                // Sin permiso para copiar: se deja el link seleccionado para copiarlo a mano.
                this.$refs.enlace.select();
                this.avisar('elegido');
            }
        },

        async compartir() {
            try {
                await navigator.share({ title: 'Truco en Vale Cuatro', text: 'Te invito a jugar al truco.', url: enlace });
            } catch {
                // Cerró el menú de compartir: no es un error.
            }
        },

        avisar(que) {
            this.copiado = que;
            clearTimeout(this.relojDelAviso);
            this.relojDelAviso = setTimeout(() => (this.copiado = null), 3000);
        },
    };
}
