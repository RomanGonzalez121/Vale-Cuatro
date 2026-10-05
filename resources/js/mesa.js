/*
 | Mesa de juego.
 |
 | En M0 esto es una maqueta: las manos vienen fijas desde el servidor y el rival
 | sigue un guion mínimo, solo para poder ver el movimiento. Las reglas de acá
 | abajo (fuerza de las cartas, tanto, pardas) existen para que la muestra sea
 | coherente; el motor real es de M2 y vive en el servidor. Lo que queda para
 | siempre es la parte visual: repartir, mover una carta, cantar, cantar los
 | tantos, cerrar la mano y anotar. Esas piezas reciben los datos ya resueltos,
 | así en M3 solo cambia de dónde vienen.
 */

import { LLEGADA, cartasDelTanto, fuerza, movimientoReducido, nombreDe, plantilla, tanto } from './cartas';

const CANTOS_DE_TRUCO = { 1: 'Truco', 2: 'Retruco', 3: 'Vale cuatro' };
const DE_QUIEN = { vos: 'tuya', rival: 'del bot', parda: 'parda' };
const CALLADO = { numero: '', frase: '', visible: false };

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
    envidoGanadoPor: null,
    truco: { valor: 1, quiero: null },
    pendiente: false,
    voz: { texto: '', tono: 'copa', quien: 'vos', visible: false },
    tantos: { vos: { ...CALLADO }, rival: { ...CALLADO }, gana: null, resuelto: false },
    desglose: [],
    cierre: null,
    aviso: 'Repartiendo.',
    cerrada: false,
    juntando: false,
    fin: null,
    reducido: movimientoReducido.matches,
    relojes: [],
    relojDeVoz: null,
    relojDePasos: null,
    pasos: [],

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

    // Lo que vale la mano se anota con el nombre del último canto querido.
    get conceptoDeLaMano() {
        return this.truco.valor > 1 ? CANTOS_DE_TRUCO[this.truco.valor - 1] : 'Mano';
    },

    get tamanoDeVoz() {
        // 0.56 em es el ancho medio de una letra de Piazzolla Black Italic; 0.6 em, el relleno de la ficha.
        return `min(11rem, 24dvh, calc(88cqw / ${Math.max(this.voz.texto.length, 4) * 0.56 + 0.6}))`;
    },

    resultadoDeBaza(numero) {
        return DE_QUIEN[this.ganadas[numero]] ?? '';
    },

    // Piezas visuales

    despues(milisegundos, accion) {
        this.relojes.push(setTimeout(accion, milisegundos));
    },

    frenarRelojes() {
        this.relojes.forEach(clearTimeout);
        this.relojes = [];
        clearTimeout(this.relojDePasos);
        this.pasos = [];
    },

    /**
     * Corre una serie de pasos, cada uno con su espera: [milisegundos, acción].
     */
    secuencia(pasos) {
        this.pasos = [...pasos];
        this.proximoPaso();
    },

    proximoPaso() {
        if (! this.pasos.length) {
            return;
        }

        this.relojDePasos = setTimeout(() => {
            this.pasos.shift()[1]();
            this.proximoPaso();
        }, this.pasos[0][0]);
    },

    /**
     * Tocar la mesa apura lo que se está mostrando: se cumplen de una los pasos
     * que faltan y el último, que es el que limpia, llega enseguida.
     */
    apurar() {
        if (this.pasos.length < 2) {
            return;
        }

        clearTimeout(this.relojDePasos);

        while (this.pasos.length > 1) {
            this.pasos.shift()[1]();
        }

        this.pasos[0][0] = 700;
        this.proximoPaso();
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
     * El camino inverso del reparto: la carta vuelve al mazo. Es una salida, así
     * que dura menos que la llegada. Devuelve la animación para saber cuándo terminó.
     */
    guardar(elemento, orden) {
        const origen = this.$refs.origen.getBoundingClientRect();
        const desde = elemento.getBoundingClientRect();
        const cuadros = this.reducido
            ? [{ opacity: 1 }, { opacity: 0 }]
            : [
                { opacity: 1, transform: 'none' },
                { opacity: 1, offset: 0.6 },
                { opacity: 0, transform: `translate(${origen.left - desde.left}px, ${origen.top - desde.top}px) rotate(14deg) scale(0.8)` },
            ];

        return elemento.animate(cuadros, {
            duration: this.reducido ? 120 : 200,
            delay: this.reducido ? 0 : orden * 30,
            easing: LLEGADA,
            fill: 'forwards',
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

    /**
     * Da vuelta una carta que estaba boca abajo, con el mismo giro del resto del sitio.
     */
    darVuelta(lugar, carta) {
        const giro = document.createElement('div');
        const dorso = document.createElement('div');
        const cara = document.createElement('div');

        giro.className = 'giro';
        giro.dataset.vuelta = 'false';
        cara.className = 'giro-cara';
        dorso.append(...lugar.childNodes);
        cara.append(plantilla(carta));
        giro.append(dorso, cara);
        lugar.replaceChildren(giro);

        // Dos cuadros de espera: el navegador tiene que pintar el dorso antes de girarlo.
        requestAnimationFrame(() => requestAnimationFrame(() => (giro.dataset.vuelta = 'true')));
    },

    cantar(texto, tono, quien) {
        clearTimeout(this.relojDeVoz);
        this.voz = { texto, tono, quien, visible: true };
        this.relojDeVoz = setTimeout(() => (this.voz.visible = false), 1250);
    },

    /**
     * Los tantos se cantan como en la mesa. Primero el mano, que dice su número;
     * el otro contesta con uno mayor ("31 son mejores") o con "Son buenas", sin
     * mostrar el suyo. Después el que pierde queda a media tinta y recién ahí
     * caen los puntos. Si empatan, gana el mano.
     */
    cantarTantos({ mano, tantos, concepto, puntos, alTerminar }) {
        const otro = mano === 'vos' ? 'rival' : 'vos';
        const gana = tantos[otro] > tantos[mano] ? otro : mano;

        clearTimeout(this.relojDeVoz);
        this.voz.visible = false;
        this.tantos = { vos: { ...CALLADO }, rival: { ...CALLADO }, gana, resuelto: false };
        this.envidoGanadoPor = gana;

        this.secuencia([
            [160, () => {
                this.tantos[mano] = { numero: tantos[mano], frase: '', visible: true };
                this.aviso = mano === 'vos' ? `Cantás ${tantos.vos}.` : `El bot canta ${tantos.rival}.`;
                this.levantarTanto(mano === 'vos');
            }],
            [800, () => {
                this.tantos[otro] = gana === otro
                    ? { numero: tantos[otro], frase: 'son mejores', visible: true }
                    : { numero: '', frase: 'Son buenas', visible: true };
                this.levantarTanto(gana === 'vos');
            }],
            [650, () => {
                // Corto, para que entre en un renglón del celular.
                const respuesta = gana === otro ? `${tantos[otro]} son mejores.` : 'Son buenas.';

                this.tantos.resuelto = true;
                this.aviso = `${respuesta} ${gana === 'vos' ? `Ganás el envido: sumás ${puntos}.` : `El bot suma ${puntos}.`}`;
                this.anotar(concepto, gana, puntos);
            }],
            [1500, () => {
                this.tantos.vos.visible = false;
                this.tantos.rival.visible = false;
                this.levantarTanto(false);
                alTerminar?.();
            }],
        ]);
    },

    /**
     * Levanta de tu mano las cartas que arman tu tanto y apaga la que no cuenta.
     */
    levantarTanto(levantar) {
        const delTanto = cartasDelTanto(this.manos[this.indice % this.manos.length].vos);

        this.$refs.mano.toggleAttribute('data-tanto', levantar);
        this.$refs.mano.querySelectorAll('button').forEach((boton) => {
            boton.classList.toggle('del-tanto', levantar && delTanto.includes(boton.dataset.carta));
        });
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

    /**
     * Suma puntos y deja anotado por qué, para el desglose del cierre de la mano.
     */
    anotar(concepto, quien, cantidad) {
        this.desglose.push({ concepto, puntos: cantidad, texto: `${concepto}, para ${quien === 'vos' ? 'vos' : 'el bot'}` });
        this.sumar(quien, cantidad);
    },

    // La mano

    repartir(enfocar = false) {
        const mano = this.manos[this.indice % this.manos.length];

        this.frenarRelojes();
        this.baza = 0;
        this.jugadas = [{}, {}, {}];
        this.ganadas = [];
        this.manoVos = [...mano.vos];
        this.manoRival = [...mano.rival];
        this.envidoCantado = false;
        this.envidoGanadoPor = null;
        this.truco = { valor: 1, quiero: null };
        this.tantos = { vos: { ...CALLADO }, rival: { ...CALLADO }, gana: null, resuelto: false };
        this.desglose = [];
        this.cierre = null;
        this.pendiente = false;
        this.cerrada = false;
        this.juntando = false;
        this.turno = 'espera';
        this.aviso = 'Repartiendo.';

        [0, 1, 2].forEach((numero) => {
            ['vos', 'rival'].forEach((quien) => {
                const hueco = this.hueco(numero, quien);

                hueco.replaceChildren();
                delete hueco.dataset.gana;
                delete hueco.dataset.pierde;
            });
        });
        this.$refs.rival.replaceChildren();
        this.$refs.mano.removeAttribute('data-tanto');

        mano.vos.forEach((carta, i) => {
            const boton = document.createElement('button');

            boton.type = 'button';
            boton.className = 'naipe-jugable';
            boton.dataset.carta = carta;
            boton.setAttribute('aria-label', `${nombreDe(carta)}, jugar esta carta`);
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
            this.aviso = 'Jugá una carta o cantá.';

            // Quien llegó con el teclado al botón de repartir sigue en sus cartas.
            if (enfocar) {
                this.$refs.mano.querySelector('button')?.focus({ preventScroll: true });
            }
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

        // La carta que gana queda arriba y la que pierde se apaga: la baza se lee de un vistazo.
        if (resultado !== 'parda') {
            this.hueco(this.baza, resultado).dataset.gana = '';
            this.hueco(this.baza, resultado === 'vos' ? 'rival' : 'vos').dataset.pierde = '';
        }

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

    /**
     * El cierre de la mano: quién la ganó, por qué y cuánto sumó cada cosa.
     * Queda a la vista hasta que el jugador reparte. Lo que no se jugó vuelve
     * al mazo boca abajo, salvo las cartas con las que el bot ganó el envido,
     * que tiene que mostrar.
     */
    cerrarMano(ganador, puntos, { motivo = null, concepto = null } = {}) {
        const titulo = ganador === 'vos' ? 'Ganaste la mano' : 'La mano es del bot';
        const razon = motivo ?? `Bazas: ${this.ganadas.map((baza) => DE_QUIEN[baza]).join(', ')}.`;

        this.frenarRelojes();
        this.cerrada = true;
        this.pendiente = false;
        this.turno = 'espera';
        this.tantos.vos.visible = false;
        this.tantos.rival.visible = false;
        this.anotar(concepto ?? this.conceptoDeLaMano, ganador, puntos);

        const mostradas = this.recogerSobrantes();

        this.cierre = { titulo, motivo: razon, lineas: [...this.desglose] };
        this.aviso = [
            `${titulo}. ${razon}`,
            ...this.desglose.map((linea) => `${linea.puntos} por ${linea.texto}.`),
            mostradas.length ? `El bot muestra ${mostradas.map((carta) => `el ${nombreDe(carta)}`).join(' y ')}.` : '',
            'Apretá Repartir para seguir.',
        ].filter(Boolean).join(' ');

        this.$nextTick(() => this.$refs.repartir.focus({ preventScroll: true }));
    },

    /**
     * Devuelve las cartas del bot que quedaron a la vista.
     */
    recogerSobrantes() {
        const dorsos = [...this.$refs.rival.children];
        const delTanto = cartasDelTanto(this.manos[this.indice % this.manos.length].rival);
        const mostradas = this.envidoGanadoPor === 'rival' ? this.manoRival.filter((carta) => delTanto.includes(carta)) : [];

        this.$refs.mano.removeAttribute('data-tanto');
        this.$refs.mano.querySelectorAll('button').forEach((boton, i) => {
            boton.disabled = true;
            this.guardar(boton, i).finished.then(() => boton.remove());
        });

        mostradas.forEach((carta, i) => this.darVuelta(dorsos[i], carta));
        dorsos.slice(mostradas.length).forEach((dorso, i) => this.guardar(dorso, i));

        return mostradas;
    },

    /**
     * Junta lo que quedó en la mesa y reparte la mano siguiente.
     */
    siguienteMano() {
        if (! this.cerrada || this.fin || this.juntando) {
            return;
        }

        const conTeclado = document.activeElement === this.$refs.repartir;
        const cartas = [...this.$refs.bazas.querySelectorAll('[data-hueco] > *'), ...this.$refs.rival.querySelectorAll('.giro')];

        this.juntando = true;
        this.cierre = null;
        this.aviso = 'Repartiendo.';
        cartas.forEach((carta, i) => this.guardar(carta, i));

        this.despues(this.reducido ? 130 : 210 + cartas.length * 30, () => {
            this.indice++;
            this.repartir(conTeclado);
        });
    },

    // Los cantos

    cantarEnvido() {
        if (! this.puedeEnvido) {
            return;
        }

        const mano = this.manos[this.indice % this.manos.length];
        const tantos = { vos: tanto(mano.vos), rival: tanto(mano.rival) };

        this.envidoCantado = true;
        this.turno = 'espera';
        this.cantar('Envido', 'oro', 'vos');
        this.aviso = 'Cantaste envido.';

        this.despues(1300, () => {
            if (tantos.rival < 23) {
                this.cantar('No quiero', 'copa', 'rival');
                this.aviso = 'El bot no quiso el envido. Sumás 1.';
                // Primero se lee el canto y después cae el punto.
                this.despues(450, () => this.anotar('Envido no querido', 'vos', 1));
                this.despues(1000, () => (this.turno = 'vos'));

                return;
            }

            this.cantar('Quiero', 'basto', 'rival');
            this.aviso = 'El bot quiso el envido.';
            this.despues(950, () => this.cantarTantos({
                mano: 'vos',
                tantos,
                concepto: 'Envido',
                puntos: 2,
                alTerminar: () => (this.turno = 'vos'),
            }));
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
            // El bot decide con lo que le queda en la mano más la carta que ya tiró en esta baza.
            // Sin ese cuidado, con la mano vacía siempre decía "no quiero".
            const cartasDelBot = [...this.manoRival, this.jugadas[this.baza].rival].filter(Boolean);

            if (Math.max(...cartasDelBot.map(fuerza)) < 8) {
                this.cantar('No quiero', 'copa', 'rival');
                this.cerrarMano('vos', this.truco.valor, {
                    motivo: `El bot no quiso el ${canto.toLowerCase()}.`,
                    concepto: `${canto} no querido`,
                });

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
            this.cerrarMano('rival', this.truco.valor, { motivo: 'No quisiste el truco.', concepto: 'Truco no querido' });

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
        const sinEnvido = this.baza === 0 && ! this.envidoCantado && this.truco.valor === 1 && ! this.pendiente;

        this.cerrarMano('rival', sinEnvido ? 2 : this.truco.valor, {
            motivo: 'Te fuiste al mazo.',
            concepto: sinEnvido ? 'Mano y envido sin jugar' : this.conceptoDeLaMano,
        });
    },
});
