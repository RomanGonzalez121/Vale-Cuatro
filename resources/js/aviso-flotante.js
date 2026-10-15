/*
 | El aviso de abajo ("Dorso elegido."): un cartelito que sube, queda unos segundos y baja por donde vino.
 | Lo trae el servidor después de un formulario, o lo pide la página sin recargarse, con un evento:
 |
 |     window.dispatchEvent(new CustomEvent('aviso', { detail: { tipo: 'hecho', texto: 'Carta elegida.' } }));
 |
 | Los tipos son los de los colores de palo: "hecho" confirma (Basto), "aviso" informa (Espada) y "error"
 | dice que algo no salió (Copa). Si llega otro mientras se ve uno, lo reemplaza y vuelve a contar.
 */
const A_LA_VISTA = 4500;
const DESPUES_DEL_MOUSE = 2000;

export default (inicial) => ({
    visible: false,
    tipo: inicial?.tipo ?? 'hecho',
    texto: inicial?.texto ?? '',
    reloj: null,

    init() {
        if (inicial) {
            this.$nextTick(() => this.mostrar(inicial));
        }
    },

    mostrar({ tipo = 'hecho', texto }) {
        this.tipo = tipo;
        this.texto = texto;
        this.visible = true;
        this.esperar(A_LA_VISTA);
    },

    esperar(milisegundos = DESPUES_DEL_MOUSE) {
        clearTimeout(this.reloj);
        this.reloj = setTimeout(() => (this.visible = false), milisegundos);
    },

    pausar() {
        clearTimeout(this.reloj);
    },
});
