/*
 | Repetición de una partida, jugada por jugada.
 |
 | Acá no se reconstruye nada: el servidor ya volvió a pasar los eventos de la partida por el motor y manda
 | la lista de "cuadros", que es la mesa tal como se veía después de cada cosa que pasó. Avanzar, retroceder
 | o saltar de mano es cambiar de cuadro, por eso ir hacia atrás funciona igual que ir hacia adelante.
 |
 | Qué se anima: la carta que llega cuando se avanza un cuadro con el botón o con la reproducción. Con el
 | teclado, al volver atrás o al saltar de mano no se anima nada: son gestos que se repiten muchas veces
 | seguidas y una animación los haría sentir lentos.
 */

import { cantoEnRenglones, reparto } from './cartas';

const ENTRE_CUADROS = 1150;

// La letra de un canto en la repetición: lo más grande que llega a ser (en rem), el ancho medio de una letra
// de Piazzolla Black Italic y el relleno de la ficha (en em), y cuánto del ancho y del alto del lugar de las
// bazas puede ocupar.
const REMS_DE_LA_LETRA_MAS_GRANDE = 7;
const ANCHO_DE_LETRA = 0.56;
const RELLENO_DEL_CANTO = 0.6;
const ANCHO_QUE_OCUPA = 0.86;
const ALTO_QUE_OCUPA = 0.92;

export default ({ resumen, rival, cuadros, manos }) => ({
    // Lo que la lista del historial dice de esta partida: quién ganó, el tanteo, contra quién, cuándo.
    resumen,
    // Cómo se nombra al rival en medio de una frase: "el bot" o el apodo de la otra persona.
    rival,
    cuadros,
    // En qué cuadro empieza cada mano.
    manos,
    indice: 0,
    reloj: null,
    voz: { texto: '', tono: 'copa', quien: 'vos', letra: 0 },

    init() {
        this.cantar();
        this.$watch('indice', () => this.cantar());

        // Con la pestaña tapada no tiene sentido seguir pasando cuadros que nadie ve.
        document.addEventListener('visibilitychange', () => document.hidden && this.pausar());
    },

    // Lo que lee la pantalla

    /** La mesa tal como estaba después del cuadro actual. */
    get estado() {
        return this.cuadros[this.indice];
    },

    get reproduciendo() {
        return this.reloj !== null;
    },

    get alPrincipio() {
        return this.indice === 0;
    },

    get alFinal() {
        return this.indice === this.cuadros.length - 1;
    },

    /** Qué mano se está viendo, contando desde cero. */
    get mano() {
        return this.manos.findLastIndex((inicio) => inicio <= this.indice);
    },

    /** Dónde termina la mano que se está viendo: el cuadro anterior al reparto siguiente. */
    get finDeLaMano() {
        return (this.manos[this.mano + 1] ?? this.cuadros.length) - 1;
    },

    get jugada() {
        return this.indice - this.manos[this.mano] + 1;
    },

    get jugadasDeLaMano() {
        return this.finDeLaMano - this.manos[this.mano] + 1;
    },

    /** Contra quién, cuándo y cuánto duró, en un renglón. */
    get detalle() {
        const { rival, dia, hora, manos, minutos } = this.resumen;

        return `Contra ${rival}. ${dia}, ${hora}. ${manos} ${manos === 1 ? 'mano' : 'manos'} en ${minutos} ${minutos === 1 ? 'minuto' : 'minutos'}.`;
    },

    /** Qué cartas del rival se ven: las del bot, todas; las de otra persona, solo las que se vieron en la mesa. */
    get nota() {
        return this.resumen.bot ? 'Acá se ven las cartas de los dos.' : `De ${this.rival} se ven solo las cartas que jugó o mostró en la mesa.`;
    },

    rotuloDeBaza(numero) {
        return { vos: ', tuya', rival: this.resumen.bot ? ', del bot' : ', del rival', parda: ', parda' }[this.estado.bazas[numero].ganador] ?? '';
    },

    /** La carta de quien perdió la baza se apaga, igual que en la mesa. */
    perdio(numero, quien) {
        const ganador = this.estado.bazas[numero].ganador;

        return ganador !== null && ganador !== 'parda' && ganador !== quien;
    },

    // Moverse por la partida

    /**
     * Va a un cuadro. Solo el paso de un cuadro al siguiente, pedido con un botón o por la reproducción, llega
     * con su animación; todo lo demás cambia de golpe.
     */
    ir(indice, conMovimiento = false) {
        const destino = Math.min(Math.max(indice, 0), this.cuadros.length - 1);

        reparto.quieto = ! (conMovimiento && destino === this.indice + 1);
        this.indice = destino;
        // Alpine dibuja el cuadro nuevo antes de este aviso: ahí las cartas ya están puestas.
        this.$nextTick(() => (reparto.quieto = false));
    },

    siguiente(conMovimiento = true) {
        this.ir(this.indice + 1, conMovimiento);
    },

    anterior() {
        this.pausar();
        this.ir(this.indice - 1);
    },

    /** Al principio de la mano siguiente. */
    manoSiguiente() {
        this.pausar();
        this.ir(this.manos[this.mano + 1] ?? this.cuadros.length - 1);
    },

    /** Al principio de esta mano y, si ya se está ahí, al de la anterior: como el "atrás" de cualquier reproductor. */
    manoAnterior() {
        this.pausar();
        this.ir(this.indice > this.manos[this.mano] ? this.manos[this.mano] : this.manos[Math.max(this.mano - 1, 0)]);
    },

    reproducir() {
        if (this.alFinal) {
            this.ir(0);
        }

        this.reloj = setInterval(() => {
            this.siguiente();

            if (this.alFinal) {
                this.pausar();
            }
        }, ENTRE_CUADROS);
    },

    pausar() {
        clearInterval(this.reloj);
        this.reloj = null;
    },

    /**
     * Las flechas pasan de jugada; con Mayús, de mano. No se usan dentro de un campo ni con otras teclas
     * apretadas, que son atajos del navegador.
     */
    tecla(evento) {
        if (! ['ArrowLeft', 'ArrowRight'].includes(evento.key) || evento.ctrlKey || evento.metaKey || evento.altKey) {
            return;
        }

        evento.preventDefault();
        this.pausar();

        if (evento.shiftKey) {
            evento.key === 'ArrowRight' ? this.manoSiguiente() : this.manoAnterior();
        } else {
            this.ir(this.indice + (evento.key === 'ArrowRight' ? 1 : -1));
        }
    },

    // El canto

    /**
     * Si en este cuadro alguien cantó, la palabra va grande sobre las bazas, con la misma pieza que en la mesa.
     * Se guarda aparte del cuadro para que, al pasar al siguiente, se vaya con su fundido y no cambie de texto.
     */
    cantar() {
        const canto = this.estado.canto;

        if (! canto) {
            return;
        }

        const texto = cantoEnRenglones(canto.texto, (renglones) => this.letraDe(renglones));

        this.voz = { ...canto, texto, letra: this.letraDe(texto.split('\n')) };
    },

    /**
     * Cuántos píxeles mide la letra de un canto con esos renglones: lo que entre en el lugar de las bazas,
     * sin pasar del máximo.
     */
    letraDe(renglones) {
        const lugar = this.$refs.bazas.getBoundingClientRect();
        const masLargo = Math.max(...renglones.map((renglon) => renglon.length), 4);

        return Math.min(
            REMS_DE_LA_LETRA_MAS_GRANDE * parseFloat(getComputedStyle(document.documentElement).fontSize),
            (lugar.height * ALTO_QUE_OCUPA) / (renglones.length * 0.9),
            (lugar.width * ANCHO_QUE_OCUPA) / (masLargo * ANCHO_DE_LETRA + RELLENO_DEL_CANTO),
        );
    },

    destroy() {
        this.pausar();
    },
});
