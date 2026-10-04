/*
 | Las mesitas de "Cómo se juega": cada regla se puede probar con cartas del mazo.
 | Todo el movimiento de acá responde a un toque del visitante.
 */

import { cartasAlAzar, escalon, fuerza, nombreDe, paloDe, repartirEn, tanto, valorDeEnvido } from './cartas';

/** Dos cartas al azar: hay que decir cuál gana. Enseña el orden de las cartas. */
export const duelo = () => ({
    cartas: [],
    elegida: null,
    aciertos: 0,
    intentos: 0,

    init() {
        this.repartir();
    },

    repartir() {
        this.cartas = cartasAlAzar(2);
        this.elegida = null;
        this.$nextTick(() => {
            this.$refs.lugares.querySelectorAll('[data-lugar]').forEach((lugar, i) => repartirEn(lugar, this.cartas[i], i));
        });
    },

    /** 0 o 1 según la carta que gana, o 'parda' si empatan. */
    get ganadora() {
        const diferencia = fuerza(this.cartas[0]) - fuerza(this.cartas[1]);

        return diferencia === 0 ? 'parda' : diferencia > 0 ? 0 : 1;
    },

    get acerto() {
        return this.elegida === this.ganadora;
    },

    nombre(lugar) {
        return this.cartas[lugar] ? nombreDe(this.cartas[lugar]) : '';
    },

    elegir(opcion) {
        if (this.elegida !== null) {
            return;
        }

        this.elegida = opcion;
        this.intentos++;
        this.aciertos += this.acerto ? 1 : 0;
    },

    estado(lugar) {
        if (this.elegida === null || this.ganadora === 'parda') {
            return '';
        }

        return this.ganadora === lugar ? 'gana' : 'pierde';
    },

    get veredicto() {
        if (this.elegida === null) {
            return 'Tocá la carta que gana la baza, o decí que empatan.';
        }

        const [a, b] = this.cartas;
        const inicio = this.acerto ? 'Bien.' : 'No.';

        if (this.ganadora === 'parda') {
            return `${inicio} Empatan: las dos están en el escalón ${escalon(a)}. La baza es parda.`;
        }

        const gana = this.cartas[this.ganadora];
        const pierde = this.cartas[1 - this.ganadora];

        return `${inicio} Gana el ${nombreDe(gana)}, del escalón ${escalon(gana)}. El ${nombreDe(pierde)} está en el ${escalon(pierde)}.`;
    },
});

/** Tres cartas al azar y cuánto tienen de envido, con la cuenta a la vista. */
export const tantoDeEnvido = () => ({
    cartas: [],

    init() {
        this.repartir();
    },

    repartir() {
        this.cartas = cartasAlAzar(3);
        this.$nextTick(() => {
            this.$refs.lugares.querySelectorAll('[data-lugar]').forEach((lugar, i) => repartirEn(lugar, this.cartas[i], i));
        });
    },

    get tanto() {
        return this.cartas.length ? tanto(this.cartas) : 0;
    },

    get hayFlor() {
        return this.cartas.length === 3 && new Set(this.cartas.map(paloDe)).size === 1;
    },

    get cuenta() {
        if (! this.cartas.length) {
            return '';
        }

        const pares = this.cartas.flatMap((a, i) => this.cartas.slice(i + 1).map((b) => [a, b])).filter(([a, b]) => paloDe(a) === paloDe(b));

        if (! pares.length) {
            return 'No hay dos cartas del mismo palo: vale la más alta. Las figuras valen 0.';
        }

        const [a, b] = pares.sort((x, y) => valorDeEnvido(y[0]) + valorDeEnvido(y[1]) - valorDeEnvido(x[0]) - valorDeEnvido(x[1]))[0];
        const cuenta = `${valorDeEnvido(a)} más ${valorDeEnvido(b)} más 20`;

        return this.hayFlor
            ? `Tenés flor, las tres son de ${paloDe(a)}. Si la callás, de envido son ${cuenta} con las dos más altas.`
            : `El ${nombreDe(a)} y el ${nombreDe(b)} son del mismo palo: ${cuenta}.`;
    },
});
