/*
 | La sala de espera de una partida entre personas.
 |
 | Quien la abrió manda el link y espera. La página pregunta cada pocos segundos si ya se sentó alguien
 | (y apenas vuelve a estar a la vista, por si la pestaña estaba dormida). Cuando se sienta, se reparten
 | los tres dorsos en el lugar del rival, que es el único movimiento de la pantalla, y pasa a la mesa.
 |
 | Todo lo que decide el servidor llega por esa consulta: la página no sabe quién es el rival ni si la sala
 | sigue abierta hasta que se lo dicen.
 */

import { movimientoReducido } from './cartas';

const CADA = 4000;

/** Lo que se ve el reparto antes de pasar a la mesa. Con movimiento reducido alcanza con leer el aviso. */
const PAUSA_DEL_REPARTO = 1600;
const PAUSA_REDUCIDA = 700;

export default function sala({ estado, mesa, enlace }) {
    return {
        // Cómo salió lo último que se intentó con el link: 'copiado', 'elegido' (se dejó seleccionado para copiar a mano) o nada.
        copiado: null,
        puedeCompartir: typeof navigator.share === 'function',
        // Quien se sentó, cuando ya llegó.
        rival: null,
        llego: false,
        relojDelAviso: null,
        espera: null,

        init() {
            this.consultar();

            // Si la pestaña estaba dormida, se entera apenas vuelve.
            document.addEventListener('visibilitychange', () => {
                if (! document.hidden && ! this.llego) {
                    this.consultar();
                }
            });
        },

        async consultar() {
            clearTimeout(this.espera);

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

            this.espera = setTimeout(() => this.consultar(), CADA);
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
