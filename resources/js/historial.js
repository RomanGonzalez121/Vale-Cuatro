/*
 | La lista del historial y el cartel de la repetición.
 |
 | "Ver de nuevo" es un link de verdad a la página de esa repetición. Acá se lo mejora: en vez de cambiar
 | de página, la repetición se abre en un cartel sobre la lista, y la dirección del navegador pasa a ser
 | la de esa partida. Así el cartel se comporta como una página: "atrás" lo cierra, "adelante" lo vuelve a
 | abrir, y recargar o entrar directo a esa dirección muestra la repetición como página propia.
 |
 | El cartel es el <dialog> del navegador: él se ocupa del foco, de la tecla Esc y de que lo de atrás no
 | se pueda tocar. Si algo falla (sin conexión, o el servidor no contesta), el link hace lo de siempre.
 */

import { LLEGADA, movimientoReducido } from './cartas';

export default () => ({
    // Lo que arma el servidor para la repetición abierta, o null con el cartel cerrado.
    datos: null,
    // La repetición que se está pidiendo: mientras tanto ese link avisa que está ocupado.
    pidiendo: null,
    tituloDeLaLista: document.title,

    init() {
        // "Atrás" y "adelante" del navegador: se muestra lo que diga la entrada a la que se llegó.
        window.addEventListener('popstate', (evento) => this.segunLaDireccion(evento.state));
    },

    /**
     * El clic en "Ver de nuevo". Con Ctrl, Mayús o el botón del medio el link se abre como cualquier link.
     */
    async abrir(evento) {
        if (evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey || evento.button !== 0) {
            return;
        }

        evento.preventDefault();

        const enlace = evento.currentTarget;

        if (this.pidiendo) {
            return;
        }

        if (await this.mostrar(enlace.dataset.cuadros, true)) {
            history.pushState({ repeticion: enlace.dataset.cuadros }, '', enlace.href);
        } else {
            // No se pudo armar el cartel: queda la página propia, que no depende de nada de esto.
            window.location.assign(enlace.href);
        }
    },

    /**
     * Pide los datos de una repetición y abre el cartel con ella. Devuelve si pudo.
     */
    async mostrar(direccion, conMovimiento) {
        this.pidiendo = direccion;

        try {
            const respuesta = await fetch(direccion, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

            if (! respuesta.ok) {
                return false;
            }

            const datos = await respuesta.json();

            // La repetición se arma de nuevo con cada partida: primero se saca la anterior.
            this.datos = null;
            await this.$nextTick();
            this.datos = datos;
            await this.$nextTick();

            if (! this.$refs.cartel.open) {
                this.$refs.cartel.showModal();

                if (conMovimiento) {
                    this.entrar();
                }
            }

            document.title = `Repetición | ${this.tituloDeLaLista.split(' | ').at(-1)}`;

            return true;
        } catch {
            return false;
        } finally {
            this.pidiendo = null;
        }
    },

    /**
     * "Cerrar", la tecla Esc o un clic en el fondo. Cerrar es volver atrás en el navegador: el cartel se
     * saca cuando llega ese aviso, así el botón y la flecha del navegador hacen exactamente lo mismo.
     */
    cerrar() {
        history.back();
    },

    segunLaDireccion(estado) {
        if (estado?.repeticion) {
            // Si no se puede volver a armar el cartel, se carga la página de esa repetición.
            this.mostrar(estado.repeticion, false).then((pudo) => pudo || window.location.reload());

            return;
        }

        this.$refs.cartel.close();
        this.datos = null;
        document.title = this.tituloDeLaLista;
    },

    /**
     * El cartel entra apenas más chico y el fondo se oscurece con él. Con el teclado o con "atrás" no se
     * anima nada; con movimiento reducido, solo el fundido.
     */
    entrar() {
        const cartel = this.$refs.cartel;
        const reducido = movimientoReducido.matches;
        const opciones = { duration: reducido ? 150 : 220, easing: LLEGADA };

        cartel.animate(reducido ? [{ opacity: 0 }, { opacity: 1 }] : [{ opacity: 0, transform: 'scale(0.96)' }, { opacity: 1, transform: 'none' }], opciones);
        cartel.animate([{ opacity: 0 }, { opacity: 1 }], { ...opciones, pseudoElement: '::backdrop' });
    },
});
