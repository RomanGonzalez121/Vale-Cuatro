/*
 | Repetición de una partida, jugada por jugada.
 |
 | Recibe la lista de pasos en orden (reparto, canto, carta, puntos) y arma la
 | mesa reproduciendo los pasos desde el principio hasta el actual. Por eso
 | "anterior" funciona igual que "siguiente": no hay nada que deshacer, solo se
 | vuelve a contar hasta un paso antes. Es la misma idea con la que M3 guarda
 | la partida como una secuencia de eventos.
 */

import { fuerza, nombreDe } from './cartas';

const ENTRE_PASOS = 1150;

export default (pasos) => ({
    pasos,
    indice: 0,
    reloj: null,
    voz: { texto: '', tono: 'copa', quien: 'vos' },

    get reproduciendo() {
        return this.reloj !== null;
    },

    get alFinal() {
        return this.indice === this.pasos.length - 1;
    },

    /** La mesa tal como estaba después del paso actual. */
    get estado() {
        const mesa = { numero: 0, tanteo: [0, 0], mano: [null, null, null], rival: 0, bazas: [{}, {}, {}], canto: null, texto: '' };

        this.pasos.slice(0, this.indice + 1).forEach((paso) => {
            mesa.canto = null;

            if (paso.tipo === 'reparto') {
                Object.assign(mesa, { numero: paso.mano, tanteo: [...paso.tanteo], mano: [...paso.vos], rival: 3, bazas: [{}, {}, {}] });
                mesa.texto = `Mano ${paso.mano}. Se reparte.`;
            }

            if (paso.tipo === 'canto') {
                mesa.canto = paso;
                mesa.texto = `${paso.quien === 'vos' ? 'Vos' : 'El bot'}: ${paso.texto.toLowerCase()}.`;
            }

            if (paso.tipo === 'carta') {
                mesa.bazas[paso.baza][paso.quien] = paso.carta;
                mesa.texto = `${paso.quien === 'vos' ? 'Jugaste' : 'El bot jugó'} el ${nombreDe(paso.carta)}.`;

                if (paso.quien === 'vos') {
                    mesa.mano = mesa.mano.map((carta) => (carta === paso.carta ? null : carta));
                } else {
                    mesa.rival--;
                }
            }

            if (paso.tipo === 'puntos') {
                mesa.tanteo[paso.quien === 'vos' ? 0 : 1] += paso.cantidad;
                mesa.texto = paso.texto;
            }
        });

        return mesa;
    },

    /** 'vos', 'rival' o 'parda' cuando la baza ya tiene las dos cartas. */
    ganadorDeBaza(numero) {
        const { vos, rival } = this.estado.bazas[numero];

        if (! vos || ! rival) {
            return null;
        }

        const diferencia = fuerza(vos) - fuerza(rival);

        return diferencia === 0 ? 'parda' : diferencia > 0 ? 'vos' : 'rival';
    },

    rotuloDeBaza(numero) {
        return { vos: ', tuya', rival: ', del bot', parda: ', parda' }[this.ganadorDeBaza(numero)] ?? '';
    },

    perdio(numero, quien) {
        const ganador = this.ganadorDeBaza(numero);

        return ganador !== null && ganador !== 'parda' && ganador !== quien;
    },

    get tamanoDeVoz() {
        const letras = Math.max(this.voz.texto.length, 4);

        return `min(7rem, calc(86cqw / ${letras * 0.56 + 0.6}))`;
    },

    siguiente() {
        this.indice = Math.min(this.indice + 1, this.pasos.length - 1);
    },

    anterior() {
        this.pausar();
        this.indice = Math.max(this.indice - 1, 0);
    },

    reproducir() {
        if (this.alFinal) {
            this.indice = 0;
        }

        this.reloj = setInterval(() => {
            this.siguiente();

            if (this.alFinal) {
                this.pausar();
            }
        }, ENTRE_PASOS);
    },

    pausar() {
        clearInterval(this.reloj);
        this.reloj = null;
    },

    destroy() {
        this.pausar();
    },
});
