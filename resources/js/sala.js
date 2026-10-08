/*
 | La sala de espera de una partida entre personas.
 |
 | Quien la abrió manda el link y espera. Cuando se sienta alguien, el servidor manda un aviso por el
 | WebSocket (un canal privado de la partida, sin cartas ni datos: solo "hay novedades") y la página
 | pregunta qué pasó. Esa pregunta también se hace sola cada tanto, por si el aviso no llega: con el
 | WebSocket andando cada 15 segundos y, si se cayó o nunca conectó, cada 4. Al volver a estar a la vista
 | o al reconectarse se pregunta de inmediato.
 |
 | La pantalla es la mesa servida y quieta: tanteador, mazo y los dos lugares vacíos. Cuando alguien se
 | sienta, su apodo cae en el tanteador y el mazo reparte tres cartas a cada uno, boca abajo: es el único
 | momento coreografiado, y después pasa a la mesa. Todo lo que decide el servidor llega por esa
 | pregunta: la página no sabe quién es el rival ni si la sala sigue abierta hasta que se lo dicen, y
 | las cartas de verdad recién llegan en la mesa.
 */

import { LLEGADA, llegarDelMazo, movimientoReducido } from './cartas';
import { escucharPartida } from './echo';

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
            escucharPartida(partida, {
                alAvisar: () => this.consultar(),
                // Al conectar (y al reconectar) se pone al día, por si el aviso pasó mientras no estaba.
                alConectar: () => {
                    this.enVivo = true;
                    this.consultar();
                },
                alCaer: () => (this.enVivo = false),
            });
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
                        this.sentarse(datos.rival, datos.mano);

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

        sentarse(apodo, soyMano) {
            if (this.llego) {
                return;
            }

            this.rival = apodo;
            this.llego = true;

            // Los dorsos existen recién cuando Alpine los dibuja: ahí se reparten.
            this.$nextTick(() => this.repartir(soyMano));

            setTimeout(() => window.location.assign(mesa), movimientoReducido.matches ? PAUSA_REDUCIDA : PAUSA_DEL_REPARTO);
        },

        /**
         * La llegada del rival: su apodo cae en el tanteador y después el mazo reparte, una carta para
         * cada uno, con 70 ms entre carta y carta y empezando por el mano, igual que en la mesa. Con
         * movimiento reducido todo aparece con un fundido corto.
         */
        repartir(soyMano) {
            const reducido = movimientoReducido.matches;

            // En el celular la mesa puede haber quedado debajo del borde: se la trae a la vista para que el reparto se vea.
            // Va sin deslizar: las cartas miden de dónde salen y adónde llegan con la página ya quieta.
            this.$refs.mesa.scrollIntoView({ block: 'nearest', behavior: 'instant' });

            const cartas = (lugar) => [...this.$refs[lugar].querySelectorAll('.carta')];
            // El reparto arranca cuando el apodo ya está llegando.
            const llegar = (carta, orden) => llegarDelMazo(carta, this.$refs.mazo, orden, { reducido, demora: 180 });

            this.$refs.tanteoRival.querySelector('span').animate(
                reducido ? [{ opacity: 0 }, { opacity: 1 }] : [{ opacity: 0, transform: 'translateY(-0.6em)' }, { opacity: 1, transform: 'none' }],
                { duration: reducido ? 150 : 260, easing: LLEGADA },
            );

            cartas('lugarPropio').forEach((carta, i) => llegar(carta, i * 2 + (soyMano ? 0 : 1)));
            cartas('lugarRival').forEach((carta, i) => llegar(carta, i * 2 + (soyMano ? 1 : 0)));
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
