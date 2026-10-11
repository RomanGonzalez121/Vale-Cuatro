/*
 | La página de "sin conexión".
 |
 | La muestra el service worker cuando se pide una página y la red no contesta, así que la dirección que
 | figura arriba es la de la página que se quería abrir. Por eso "Probar de nuevo" no va a ningún lado
 | fijo: primero pregunta si el sitio contesta y, si contesta, vuelve a pedir esa misma dirección.
 | También prueba sola cuando el equipo avisa que volvió la conexión.
 |
 | El movimiento: al abrirse, las tres cartas vuelven al mazo (el reparto al revés); y si se prueba y
 | sigue sin andar, la carta de arriba se levanta y vuelve a caer, como quien va a repartir y no puede.
 | Las dos cosas se suman a la posición que cada carta ya tiene en la pila.
 */

import { LLEGADA, movimientoReducido } from './cartas';

export default ({ pulso, inicio }) => ({
    probando: false,
    aviso: '',

    init() {
        this.juntar();
        window.addEventListener('online', () => this.probar());
    },

    /**
     * Las cartas llegan al mazo desde donde estaría una mano abierta, de a una. Con movimiento reducido aparecen.
     */
    juntar() {
        const reducido = movimientoReducido.matches;

        [...this.$refs.mazo.children].forEach((carta, i) => {
            const cuadros = reducido
                ? [{ opacity: 0 }, { opacity: 1 }]
                : [{ opacity: 0, transform: `translate(${(i - 1) * 62}%, 46%) rotate(${(i - 1) * 13}deg)` }, { opacity: 1, transform: 'none' }];

            carta.animate(cuadros, {
                duration: reducido ? 150 : 300,
                delay: reducido ? 0 : 120 + i * 80,
                easing: LLEGADA,
                fill: 'backwards',
                // Se suma a su lugar en la pila en vez de reemplazarlo.
                composite: reducido ? 'replace' : 'add',
            });
        });
    },

    /**
     * Sigue sin conexión: la carta de arriba se levanta y vuelve a su lugar. La respuesta también va escrita.
     */
    noSePudo() {
        if (movimientoReducido.matches) {
            return;
        }

        this.$refs.mazo.lastElementChild.animate(
            [{ transform: 'none' }, { transform: 'translateY(-9%) rotate(-5deg)' }, { transform: 'none' }],
            { duration: 260, easing: LLEGADA, composite: 'add' },
        );
    },

    async probar() {
        if (this.probando) {
            return;
        }

        this.probando = true;
        this.aviso = 'Probando.';

        let anda = false;

        try {
            // Una pregunta mínima, que nunca se guarda: si contesta, hay sitio.
            anda = (await fetch(pulso, { cache: 'no-store' })).ok;
        } catch {
            anda = false;
        }

        if (anda) {
            this.aviso = 'Volvió la conexión.';
            // Si se entró a esta página a propósito no hay otra que volver a pedir: va al inicio.
            if (window.location.pathname === '/sin-conexion') {
                window.location.assign(inicio);
            } else {
                window.location.reload();
            }

            return;
        }

        this.probando = false;
        this.aviso = 'Todavía sin conexión.';
        this.noSePudo();
    },
});
