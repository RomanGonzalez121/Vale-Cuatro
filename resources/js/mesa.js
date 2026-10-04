/*
 | Mesa de juego.
 |
 | En M0 esto es una maqueta: las manos vienen fijas desde el servidor y el rival
 | sigue un guion mínimo, solo para poder ver el movimiento. Las reglas de acá
 | abajo (fuerza de las cartas, tanto, pardas) existen para que la muestra sea
 | coherente; el motor real es de M2 y vive en el servidor. Lo que queda para
 | siempre es la parte visual: repartir, mover una carta, cantar y anotar.
 */

import { LLEGADA, fuerza, movimientoReducido, nombreDe, plantilla, tanto } from './cartas';

const CANTOS_DE_TRUCO = { 1: 'Truco', 2: 'Retruco', 3: 'Vale cuatro' };

export default (manos, puntosIniciales = { vos: 7, rival: 5 }) => ({
    manos,
    indice: 0,
    puntos: { ...puntosIniciales },
    baza: 0,
    jugadas: [{}, {}, {}],
    ganadas: [],
    manoVos: [],
    manoRival: [],
    turno: 'espera',
    envidoCantado: false,
    truco: { valor: 1, quiero: null },
    pendiente: false,
    voz: { texto: '', tono: 'copa', quien: 'vos', visible: false },
    aviso: 'Repartiendo.',
    cerrada: false,
    fin: null,
    reducido: movimientoReducido.matches,
    relojes: [],
    relojDeVoz: null,

    init() {
        this.repartir();
    },

    // Estado que lee la barra de acciones

    get hayEnvido() {
        return ! this.envidoCantado && this.baza === 0 && ! this.jugadas[0].vos && ! this.cerrada;
    },

    get puedeEnvido() {
        return this.hayEnvido && this.turno === 'vos' && ! this.pendiente;
    },

    get cantoDeTruco() {
        return CANTOS_DE_TRUCO[this.truco.valor] ?? null;
    },

    // Solo sube el truco quien tiene el quiero.
    get hayTruco() {
        return this.cantoDeTruco !== null && this.truco.quiero !== 'rival' && ! this.cerrada;
    },

    get puedeTruco() {
        return this.hayTruco && this.turno === 'vos' && ! this.pendiente;
    },

    get estadoDelTruco() {
        return this.truco.valor > 1 ? `${CANTOS_DE_TRUCO[this.truco.valor - 1]} querido, vale ${this.truco.valor}` : '';
    },

    get tamanoDeVoz() {
        // 0.56 em es el ancho medio de una letra de Piazzolla Black Italic; 0.6 em, el relleno de la ficha.
        return `min(11rem, 24dvh, calc(88cqw / ${Math.max(this.voz.texto.length, 4) * 0.56 + 0.6}))`;
    },

    resultadoDeBaza(numero) {
        return { vos: 'tuya', rival: 'del bot', parda: 'parda' }[this.ganadas[numero]] ?? '';
    },

    // Piezas visuales

    despues(milisegundos, accion) {
        this.relojes.push(setTimeout(accion, milisegundos));
    },

    frenarRelojes() {
        this.relojes.forEach(clearTimeout);
        this.relojes = [];
    },

    hueco(numero, quien) {
        return this.$refs.bazas.children[numero].querySelector(`[data-hueco="${quien}"]`);
    },

    /**
     * Reparto: el único momento coreografiado. Seis cartas salen del mazo del
     * rival, una para cada uno, con 70 ms entre carta y carta.
     */
    llegar(elemento, orden) {
        const origen = this.$refs.origen.getBoundingClientRect();
        const destino = elemento.getBoundingClientRect();
        const cuadros = this.reducido
            ? [{ opacity: 0 }, { opacity: 1 }]
            : [
                { opacity: 0, transform: `translate(${origen.left - destino.left}px, ${origen.top - destino.top}px) rotate(18deg)` },
                { opacity: 1, offset: 0.35 },
                { opacity: 1, transform: 'none' },
            ];

        elemento.animate(cuadros, {
            duration: this.reducido ? 150 : 280,
            delay: this.reducido ? 0 : orden * 70,
            easing: LLEGADA,
            fill: 'backwards',
        });
    },

    /**
     * Mueve una carta a su lugar en la baza y la anima desde donde estaba,
     * para que se vea de dónde salió.
     */
    apoyar(carta, destino, desde) {
        destino.replaceChildren(carta);

        const hasta = carta.getBoundingClientRect();
        const cuadros = this.reducido
            ? [{ opacity: 0 }, { opacity: 1 }]
            : [
                {
                    transformOrigin: 'top left',
                    transform: `translate(${desde.left - hasta.left}px, ${desde.top - hasta.top}px) scale(${desde.width / hasta.width})`,
                },
                { transformOrigin: 'top left', transform: 'none' },
            ];

        carta.animate(cuadros, { duration: this.reducido ? 150 : 260, easing: LLEGADA });
    },

    cantar(texto, tono, quien) {
        clearTimeout(this.relojDeVoz);
        this.voz = { texto, tono, quien, visible: true };
        this.relojDeVoz = setTimeout(() => (this.voz.visible = false), 1250);
    },

    sumar(quien, cantidad) {
        for (let i = 0; i < cantidad; i++) {
            setTimeout(() => {
                this.puntos[quien] = Math.min(30, this.puntos[quien] + 1);

                if (this.puntos[quien] === 30) {
                    this.frenarRelojes();
                    this.fin = quien;
                }
            }, i * 150);
        }
    },

    // La mano

    repartir() {
        const mano = this.manos[this.indice % this.manos.length];

        this.frenarRelojes();
        this.baza = 0;
        this.jugadas = [{}, {}, {}];
        this.ganadas = [];
        this.manoVos = [...mano.vos];
        this.manoRival = [...mano.rival];
        this.envidoCantado = false;
        this.truco = { valor: 1, quiero: null };
        this.pendiente = false;
        this.cerrada = false;
        this.turno = 'espera';
        this.aviso = 'Repartiendo.';

        [0, 1, 2].forEach((numero) => {
            this.hueco(numero, 'vos').replaceChildren();
            this.hueco(numero, 'rival').replaceChildren();
        });
        this.$refs.rival.replaceChildren();

        mano.vos.forEach((carta, i) => {
            const boton = document.createElement('button');

            boton.type = 'button';
            boton.className = 'naipe-jugable';
            boton.setAttribute('aria-label', `Jugar el ${nombreDe(carta)}`);
            boton.append(plantilla(carta));
            boton.addEventListener('click', () => this.jugar(carta, boton));
            this.$refs.mano.children[i].replaceChildren(boton);
            this.llegar(boton, i * 2);

            const dorso = document.createElement('div');

            dorso.append(plantilla('dorso'));
            this.$refs.rival.append(dorso);
            this.llegar(dorso, i * 2 + 1);
        });

        this.despues(this.reducido ? 200 : 650, () => {
            this.turno = 'vos';
            this.aviso = 'Sos mano. Jugá una carta o cantá.';
        });
    },

    jugar(carta, boton) {
        if (this.turno !== 'vos' || this.pendiente || this.cerrada) {
            return;
        }

        const desde = boton.getBoundingClientRect();
        const naipe = boton.firstElementChild;

        this.manoVos = this.manoVos.filter((otra) => otra !== carta);
        this.jugadas[this.baza].vos = carta;
        this.turno = 'espera';
        this.aviso = `Jugaste el ${nombreDe(carta)}.`;
        this.apoyar(naipe, this.hueco(this.baza, 'vos'), desde);
        boton.remove();

        if (this.jugadas[this.baza].rival) {
            this.despues(600, () => this.resolverBaza());
        } else if (this.manos[this.indice % this.manos.length].rivalCantaTruco && this.baza === 0 && this.truco.valor === 1) {
            this.despues(700, () => {
                this.cantar('Truco', 'copa', 'rival');
                this.pendiente = true;
                this.aviso = 'El bot cantó truco. ¿Qué hacés?';
            });
        } else {
            this.despues(750, () => this.juegaElRival());
        }
    },

    juegaElRival() {
        const tuya = this.jugadas[this.baza].vos;
        const deMenorAMayor = [...this.manoRival].sort((a, b) => fuerza(a) - fuerza(b));
        const carta = (tuya && deMenorAMayor.find((otra) => fuerza(otra) > fuerza(tuya))) || deMenorAMayor[0];
        const dorso = this.$refs.rival.lastElementChild;
        const desde = dorso.getBoundingClientRect();

        dorso.remove();
        this.manoRival = this.manoRival.filter((otra) => otra !== carta);
        this.jugadas[this.baza].rival = carta;
        this.aviso = `El bot jugó el ${nombreDe(carta)}.`;
        this.apoyar(plantilla(carta), this.hueco(this.baza, 'rival'), desde);

        if (tuya) {
            this.despues(600, () => this.resolverBaza());
        } else {
            this.turno = 'vos';
        }
    },

    resolverBaza() {
        const { vos, rival } = this.jugadas[this.baza];
        const diferencia = fuerza(vos) - fuerza(rival);
        const resultado = diferencia === 0 ? 'parda' : diferencia > 0 ? 'vos' : 'rival';

        this.ganadas.push(resultado);
        this.aviso = {
            vos: `Ganaste la baza con el ${nombreDe(vos)}.`,
            rival: `El bot ganó la baza con el ${nombreDe(rival)}.`,
            parda: 'Parda. Sale el mano.',
        }[resultado];

        const ganador = this.ganadorDeLaMano();

        if (ganador) {
            this.despues(700, () => this.cerrarMano(ganador, this.truco.valor));

            return;
        }

        this.baza++;

        if (resultado === 'rival') {
            this.despues(900, () => this.juegaElRival());
        } else {
            this.turno = 'vos';
        }
    },

    /**
     * Las pardas del reglamento, con vos como mano.
     */
    ganadorDeLaMano() {
        const [primera, segunda, tercera] = this.ganadas;

        if (this.ganadas.length < 2) {
            return null;
        }

        if (primera === 'parda') {
            if (segunda !== 'parda') {
                return segunda;
            }

            return tercera ? (tercera === 'parda' ? 'vos' : tercera) : null;
        }

        if (segunda === 'parda' || primera === segunda) {
            return primera;
        }

        return tercera ? (tercera === 'parda' ? primera : tercera) : null;
    },

    cerrarMano(ganador, puntos, motivo = null) {
        this.frenarRelojes();
        this.cerrada = true;
        this.pendiente = false;
        this.turno = 'espera';
        this.aviso = motivo ?? (ganador === 'vos' ? `Ganaste la mano. Sumás ${puntos}.` : `El bot ganó la mano. Suma ${puntos}.`);
        this.sumar(ganador, puntos);

        this.despues(2100, () => {
            if (! this.fin) {
                this.indice++;
                this.repartir();
            }
        });
    },

    // Los cantos

    cantarEnvido() {
        if (! this.puedeEnvido) {
            return;
        }

        const mano = this.manos[this.indice % this.manos.length];
        const tuyo = tanto(mano.vos);
        const suyo = tanto(mano.rival);

        this.envidoCantado = true;
        this.turno = 'espera';
        this.cantar('Envido', 'oro', 'vos');
        this.aviso = 'Cantaste envido.';

        this.despues(1300, () => {
            if (suyo < 23) {
                this.cantar('No quiero', 'copa', 'rival');
                this.aviso = 'El bot no quiso el envido. Sumás 1.';
                this.sumar('vos', 1);
            } else {
                this.cantar('Quiero', 'basto', 'rival');
                this.aviso = tuyo >= suyo ? `Envido: ${tuyo} a ${suyo}. Sumás 2.` : `Envido: ${tuyo} a ${suyo}. El bot suma 2.`;
                this.sumar(tuyo >= suyo ? 'vos' : 'rival', 2);
            }

            this.despues(700, () => (this.turno = 'vos'));
        });
    },

    cantarTruco() {
        if (! this.puedeTruco) {
            return;
        }

        const canto = this.cantoDeTruco;

        this.turno = 'espera';
        this.cantar(canto, 'copa', 'vos');
        this.aviso = `Cantaste ${canto.toLowerCase()}.`;

        this.despues(1300, () => {
            if (Math.max(...this.manoRival.map(fuerza)) < 8) {
                this.cantar('No quiero', 'copa', 'rival');
                this.cerrarMano('vos', this.truco.valor, `El bot no quiso. Sumás ${this.truco.valor}.`);

                return;
            }

            this.cantar('Quiero', 'basto', 'rival');
            this.truco = { valor: this.truco.valor + 1, quiero: 'rival' };
            this.aviso = `El bot quiso. ${this.estadoDelTruco}.`;
            this.turno = 'vos';
        });
    },

    responder(respuesta) {
        if (! this.pendiente) {
            return;
        }

        this.pendiente = false;

        if (respuesta === 'no-quiero') {
            this.cantar('No quiero', 'copa', 'vos');
            this.cerrarMano('rival', this.truco.valor, `No quisiste. El bot suma ${this.truco.valor}.`);

            return;
        }

        if (respuesta === 'quiero') {
            this.cantar('Quiero', 'basto', 'vos');
            this.truco = { valor: 2, quiero: 'vos' };
            this.aviso = 'Quisiste. Truco querido, vale 2.';
            this.despues(1300, () => this.juegaElRival());

            return;
        }

        this.cantar('Retruco', 'copa', 'vos');
        this.aviso = 'Cantaste retruco.';
        this.despues(1300, () => {
            this.cantar('Quiero', 'basto', 'rival');
            this.truco = { valor: 3, quiero: 'rival' };
            this.aviso = 'El bot quiso. Retruco querido, vale 3.';
            this.despues(1300, () => this.juegaElRival());
        });
    },

    irseAlMazo() {
        if (this.cerrada || (this.turno !== 'vos' && ! this.pendiente)) {
            return;
        }

        // Mazo en la primera baza sin envido ni flor cantados: 2 para el rival.
        const puntos = this.baza === 0 && ! this.envidoCantado && this.truco.valor === 1 && ! this.pendiente ? 2 : this.truco.valor;

        this.cerrarMano('rival', puntos, `Te fuiste al mazo. El bot suma ${puntos}.`);
    },
});
