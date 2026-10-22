/*
 | Mesa del gallito: uno contra dos.
 |
 | Es la misma mesa del mano a mano (mesa.js) con otra disposición: en T. Vos abajo y una máquina a cada
 | costado. De mesa.js sale todo lo que no depende de cuántos son: hablar con el servidor, esperar a las
 | máquinas, la barra de cantos, el ritmo, el sonido, el tanteador y el final. Acá va lo que cambia.
 |
 | En cada mano uno es el gallo (el mano) y juega solo contra los otros dos. Los lados cambian de una mano a
 | la otra, así que lo que el motor dice por equipo (0 el gallo, 1 la pareja) se lee con el gallo de esa mano.
 | Cada uno suma aparte: hay tres tanteadores, "vos", "izquierda" y "derecha", y cuando gana la pareja anotan
 | los dos. A las máquinas se las nombra por su lado; en la mesa, además, por lo que son en la mano.
 |
 | En el centro se ve una sola baza, la que se está jugando: cada carta cae delante de quien la tiró.
 */

import { asentarEnLaBaza, cartasDelTanto, nombreDe, numeroDe, paloDe, plantilla } from './cartas';
import mesa, { CALLADO, CANTOS, NIVEL_DE_TRUCO, TRUCOS } from './mesa';

// El lugar de cada asiento. Se juega hacia la derecha: después de vos tira la de tu derecha.
const LUGARES = ['vos', 'derecha', 'izquierda'];
const OTROS = [1, 2];

// Cómo se nombra a cada máquina en medio de una frase y al empezarla: por su lado, que no cambia.
const NOMBRES = {
    1: ['la de la derecha', 'La de la derecha'],
    2: ['la de la izquierda', 'La de la izquierda'],
};

const sinTantos = () => ({
    vos: { ...CALLADO },
    derecha: { ...CALLADO },
    izquierda: { ...CALLADO },
    gana: null,
    resuelto: false,
});

const puntosDe = (vista) => ({ vos: vista.tanteo[0], derecha: vista.tanteo[1], izquierda: vista.tanteo[2] });

export default (inicial, pedidos) => {
    const base = mesa(inicial, pedidos);
    const pintarComoSiempre = base.pintar;

    const gallito = {
        puntos: puntosDe(inicial),
        cuenta: { vos: 0, izquierda: 0, derecha: 0 },
        tantos: sinTantos(),
        // Quién está cantando, para marcarlo junto a su nombre mientras la palabra está sobre la mesa.
        habla: null,
        // Las cartas que cada uno muestra al cerrar la mano, porque ganó el envido o sumó por una flor.
        muestran: [],

        // Lo que lee la pantalla

        puntosDe,

        lugarDe(asiento) {
            return LUGARES[asiento];
        },

        nombre(asiento, alEmpezar = false) {
            return NOMBRES[asiento][alEmpezar ? 1 : 0];
        },

        esDeOtro(asiento) {
            return asiento != null && asiento !== this.yo;
        },

        /**
         * El gallo de la mano en juego, que es el mano. Con la mano cerrada, el de la que se cerró.
         */
        get gallo() {
            return this.vista.cierre?.gallo ?? this.vista.mano;
        },

        get soyElGallo() {
            return this.gallo === this.yo;
        },

        /**
         * Tu compañera en esta mano: la otra máquina que no es el gallo. Ninguna si el gallo sos vos.
         */
        get companera() {
            return this.soyElGallo ? null : OTROS.find((asiento) => asiento !== this.gallo);
        },

        /**
         * El equipo de un asiento en esta mano, como lo dice el motor: 0 el gallo, 1 la pareja.
         */
        equipoDe(asiento) {
            return asiento === this.gallo ? 0 : 1;
        },

        /**
         * Los asientos de un equipo en esta mano.
         */
        asientosDe(equipo) {
            return [0, 1, 2].filter((asiento) => this.equipoDe(asiento) === equipo);
        },

        esDeLosMios(equipo) {
            return this.asientosDe(equipo).includes(this.yo);
        },

        /**
         * Lo que es cada máquina para vos en esta mano: el gallo, tu compañera o, si el gallo sos vos, la pareja.
         */
        rolDe(asiento) {
            if (asiento === this.gallo) {
                return 'Gallo';
            }

            return this.soyElGallo ? 'Pareja' : 'Con vos';
        },

        /**
         * Tu lugar en la mano, dicho una vez debajo de tus cartas.
         */
        get tuLugar() {
            // Corto: comparte el renglón con el estado del truco en un celular de 360 px.
            return this.soyElGallo ? 'Sos el gallo' : `Vas con la ${this.lugarDe(this.companera)}`;
        },

        /**
         * A quién se espera: el que tiene que tirar o, si hay un canto sin contestar, el equipo que lo contesta.
         */
        loEsperan(asiento) {
            if (! this.enJuego || this.fin || this.seFue(asiento)) {
                return false;
            }

            const pendiente = this.vista.pendiente;

            return pendiente ? this.equipoDe(asiento) === pendiente.responde : this.vista.turno === asiento;
        },

        leTocaTirar(asiento) {
            return this.enJuego && ! this.fin && ! this.vista.pendiente && this.vista.turno === asiento;
        },

        piensa(asiento) {
            return this.pensando && this.loEsperan(asiento);
        },

        get avisoDeEspera() {
            const quien = OTROS.find((asiento) => this.loEsperan(asiento));

            return quien === undefined || this.vista.pendiente ? 'Juega la máquina.' : `Juega ${this.nombre(quien)}.`;
        },

        seFue(asiento) {
            return this.vista.enMazo.includes(asiento);
        },

        /**
         * Una carta que alguien muestra al cerrar la mano, dibujada en chico: el número y el palo.
         */
        dibujoDeCarta(carta) {
            return { tipo: 'carta', numero: numeroDe(carta), palo: paloDe(carta) };
        },

        hayTantosALaVista() {
            return LUGARES.some((lugar) => this.tantos[lugar].visible);
        },

        callarTantos() {
            LUGARES.forEach((lugar) => (this.tantos[lugar].visible = false));
        },

        indicacion() {
            if (this.fin || ! this.enJuego || ! this.vista.acciones.length) {
                return '';
            }

            if (this.puede('quiero')) {
                return this.soyElGallo
                    ? `Te cantaron ${this.cantoPendiente().toLowerCase()}.`
                    : `Les cantaron ${this.cantoPendiente().toLowerCase()}. Contestás vos.`;
            }

            return this.puede('jugar') ? 'Jugá una carta o cantá.' : 'Podés cantar.';
        },

        /**
         * Quién ganó, dicho como el resultado de una baza: tuya, de tu lado o del otro.
         */
        deQuien(resultado) {
            return { vos: this.soyElGallo ? 'tuya' : 'nuestra', rival: 'de ellos', parda: 'parda' }[resultado];
        },

        /**
         * El lado de una baza o de unos puntos visto desde vos: 'vos' si es de tu equipo en esta mano.
         */
        lado(equipo) {
            return this.esDeLosMios(equipo) ? 'vos' : 'rival';
        },

        /**
         * Cómo se nombra a un equipo de esta mano: vos, vos y tu compañera, el gallo o la pareja.
         */
        nombreDelEquipo(equipo, alEmpezar = false) {
            const asientos = this.asientosDe(equipo);
            // Si estás en el equipo, primero vos.
            const orden = asientos.includes(this.yo) ? [this.yo, ...asientos.filter((asiento) => asiento !== this.yo)] : asientos;

            return orden
                .map((asiento, i) => (asiento === this.yo ? (alEmpezar && i === 0 ? 'Vos' : 'vos') : this.nombre(asiento, alEmpezar && i === 0)))
                .join(' y ');
        },

        /**
         * Quién ganó lo que gana un equipo, dicho con su verbo: "Ganaste", "Vos y la de la izquierda ganaron", "La de la derecha ganó".
         */
        quienGano(equipo) {
            const asientos = this.asientosDe(equipo);

            if (asientos.length === 1 && asientos[0] === this.yo) {
                return 'Ganaste';
            }

            return `${this.nombreDelEquipo(equipo, true)} ${asientos.length > 1 ? 'ganaron' : 'ganó'}`;
        },

        /**
         * El título del final: lo ganaste vos o la máquina de un lado.
         */
        get ganadorDelFinal() {
            return this.fin === 'vos' ? 'Ganaste el gallito' : `Ganó la de la ${this.fin}`;
        },

        // Contar lo que pasó

        async contar(hecho, paso) {
            const asiento = hecho.asiento ?? null;
            const vos = asiento === this.yo;
            const nombre = asiento === null || vos ? '' : this.nombre(asiento, true);

            switch (hecho.tipo) {
                case 'reparto':
                    this.repartir(paso, true);
                    await this.esperar(this.reducido ? 200 : 900);
                    this.aviso = `${this.tuLugar}.`;
                    break;

                case 'carta':
                    this.aviso = vos ? `Jugaste el ${nombreDe(hecho.carta)}.` : `${nombre} jugó el ${nombreDe(hecho.carta)}.`;
                    this.callarDicho(this.lugarDe(asiento));

                    if (vos && this.adelantada === hecho.carta) {
                        this.adelantada = null;
                    } else {
                        this.jugarCarta(this.lugarDe(asiento), hecho.carta);
                        await this.leer(520);
                    }
                    break;

                case 'baza':
                    this.resolverBaza(hecho, paso);
                    await this.leer(900);

                    if (! paso.hechos.some((otro) => otro.tipo === 'mano_cerrada')) {
                        await this.levantarBaza();
                    }
                    break;

                case 'canto':
                    this.decir(CANTOS[hecho.canto], TRUCOS.includes(hecho.canto) ? 'copa' : 'oro', asiento);
                    this.aviso = `${vos ? 'Cantaste' : `${nombre} cantó`} ${CANTOS[hecho.canto].toLowerCase()}.`;
                    this.anotarDicho(this.lugarDe(asiento), CANTOS[hecho.canto], TRUCOS.includes(hecho.canto) ? 'copa' : '');
                    await this.leer(1250);
                    break;

                case 'respuesta':
                    this.decir(hecho.quiere ? 'Quiero' : 'No quiero', hecho.quiere ? 'basto' : 'copa', asiento);
                    this.aviso = `${vos ? (hecho.quiere ? 'Quisiste' : 'No quisiste') : `${nombre} ${hecho.quiere ? 'quiso' : 'no quiso'}`}.`;
                    this.anotarDicho(this.lugarDe(asiento), hecho.quiere ? 'Quiero' : 'No quiero', hecho.quiere ? 'basto' : 'copa');
                    await this.leer(1100);
                    break;

                case 'tantos':
                    await this.cantarTantos(hecho);
                    break;

                case 'puntos': {
                    // Cuando gana la pareja anotan los dos: sus fósforos caen a la vez.
                    const suman = hecho.asientos ?? this.asientosDe(hecho.equipo);

                    const quienes = suman.length === 1 && suman[0] === this.yo ? 'Sumás' : `${this.nombreDelEquipo(hecho.equipo, true)} ${suman.length > 1 ? 'suman' : 'suma'}`;

                    this.aviso = `${quienes} ${hecho.puntos}: ${this.conceptoDe(hecho.concepto, paso).toLowerCase()}.`;
                    await Promise.all(suman.map((uno) => this.sumar(this.lugarDe(uno), hecho.tanteo[uno])));
                    await this.leer(350);
                    break;
                }

                case 'mazo':
                    this.aviso = this.comoSeFue(asiento);
                    this.dejarLasCartas(asiento);
                    await this.leer(600);
                    break;

                case 'mano_cerrada':
                    this.vista = paso;
                    this.cerrarMano(paso);
                    await this.leer(300);
                    break;

                case 'partida_terminada':
                    this.fin = this.lugarDe(hecho.ganador);
                    this.sonar('final');
                    this.contarElFinal();
                    break;
            }
        },

        /**
         * Deja la mesa como dice la vista y, si la partida terminó, dice quién la ganó por su lugar.
         */
        pintar(vista, animar = true, aviso = null) {
            pintarComoSiempre.call(this, vista, animar, aviso);

            if (vista.ganador !== null && vista.ganador !== undefined) {
                this.fin = this.lugarDe(vista.ganador);
            }
        },

        /**
         * Un canto o una respuesta: la palabra va al centro, como siempre, y se marca quién la dijo.
         */
        decir(texto, tono, asiento) {
            this.habla = asiento;
            this.cantar(texto, tono, asiento === this.yo ? 'vos' : 'rival');
        },

        comoSeFue(asiento) {
            if (asiento === this.yo) {
                return this.soyElGallo ? 'Te fuiste al mazo.' : 'Te fuiste al mazo. Tu compañera sigue sola.';
            }

            return `${this.nombre(asiento, true)} se fue al mazo.`;
        },

        dejarLasCartas(asiento) {
            const cartas = asiento === this.yo ? [...this.$refs.mano.querySelectorAll('button')] : this.dorsosDe(asiento);

            cartas.forEach((carta, i) => {
                carta.disabled = true;
                this.guardar(carta, i).finished.then(() => this.sacar(carta));
            });
        },

        // Piezas visuales

        hueco(lugar) {
            return this.$refs.cruz.querySelector(`[data-hueco="${lugar}"]`);
        },

        dorsos(lugar) {
            return this.$root.querySelector(`[data-dorsos="${lugar}"]`);
        },

        dorsosDe(asiento) {
            return [...this.dorsos(this.lugarDe(asiento)).querySelectorAll('[data-dorso]')];
        },

        sacar(carta) {
            (carta.matches('[data-dorso]') ? carta.parentElement : carta).remove();
        },

        /**
         * Los tantos se cantan en ronda desde el gallo, cada uno desde su lugar. Dice su número solo quien
         * supera al mejor cantado; los demás, "son buenas". Después queda a pleno el que ganó.
         */
        async cantarTantos(hecho) {
            const gana = this.lugarDe(hecho.ganador);
            const mias = hecho.canto === 'contraflor' ? this.repartidas : cartasDelTanto(this.repartidas);

            clearTimeout(this.relojDeVoz);
            this.voz.visible = false;
            this.tantos = { ...sinTantos(), gana };

            await this.leer(160);

            for (const [orden, dicho] of hecho.tantos.entries()) {
                const lugar = this.lugarDe(dicho.asiento);
                const vos = dicho.asiento === this.yo;
                const nombre = vos ? '' : this.nombre(dicho.asiento, true);

                if (orden > 0) {
                    await this.leer(800);
                }

                this.sonar('canto');

                if (dicho.tanto === null) {
                    this.tantos[lugar] = { numero: '', frase: 'Son\nbuenas', visible: true };
                    this.aviso = vos ? 'Decís: son buenas.' : `${nombre} dice: son buenas.`;
                } else {
                    this.tantos[lugar] = { numero: dicho.tanto, frase: orden === 0 ? '' : 'son mejores', visible: true };
                    this.aviso = `${vos ? 'Cantás' : `${nombre} canta`} ${dicho.tanto}${orden === 0 ? '' : ', son mejores'}.`;
                    this.levantarTanto(vos ? mias : []);
                }
            }

            await this.leer(650);
            this.tantos.resuelto = true;
            this.levantarTanto(gana === 'vos' ? mias : []);
        },

        // La mano

        /**
         * Pone sobre la mesa lo que dice una vista: tu mano, los dorsos de las otras dos y la baza en juego.
         */
        repartir(vista, animar) {
            this.vista = vista;
            this.menu = null;
            this.cierre = null;
            this.muestran = [];
            this.tantos = sinTantos();
            this.ganadas = vista.bazas.filter((baza) => baza.cerrada).map((baza) => (baza.ganador === null ? 'parda' : this.lado(this.equipoDe(baza.ganador))));
            this.repartidas = [
                ...vista.misCartas,
                ...vista.bazas.flatMap((baza) => baza.jugadas.filter(([asiento]) => asiento === this.yo).map(([, carta]) => carta)),
            ];

            const baza = vista.bazas.at(-1);

            LUGARES.forEach((lugar, asiento) => {
                const hueco = this.hueco(lugar);
                const jugada = baza?.jugadas.find(([otro]) => otro === asiento);

                hueco.replaceChildren(...(jugada ? [asentarEnLaBaza(plantilla(jugada[1]))] : []));
                delete hueco.dataset.gana;
                delete hueco.dataset.pierde;

                if (jugada && baza.cerrada && baza.ganador !== null) {
                    hueco.dataset[baza.ganador === asiento ? 'gana' : 'pierde'] = '';
                }
            });

            this.$refs.mano.removeAttribute('data-tanto');
            [...this.$refs.mano.children].forEach((lugar) => {
                lugar.replaceChildren();
                lugar.hidden = false;
            });

            // Se reparte de a una para cada uno, en ronda, y la primera es para el gallo.
            const turnoDe = (asiento) => (asiento - vista.mano + 3) % 3;

            vista.misCartas.forEach((carta, i) => {
                const boton = document.createElement('button');

                boton.type = 'button';
                boton.className = 'naipe-jugable';
                boton.dataset.carta = carta;
                boton.setAttribute('aria-label', `${nombreDe(carta)}, jugar esta carta`);
                boton.append(plantilla(carta));
                boton.addEventListener('click', (evento) => {
                    evento.stopPropagation();
                    this.jugar(carta, boton);
                });
                this.arrastrable(boton, carta);
                this.$refs.mano.children[i].replaceChildren(boton);

                if (animar) {
                    this.llegar(boton, i * 3 + turnoDe(this.yo));
                }
            });

            OTROS.forEach((asiento) => {
                const lugar = this.dorsos(this.lugarDe(asiento));
                const cuantas = vista.enMazo.includes(asiento) ? 0 : vista.cartasEnMano[asiento];

                lugar.replaceChildren();

                for (let i = 0; i < cuantas; i++) {
                    const sitio = document.createElement('div');
                    const dorso = document.createElement('div');

                    dorso.dataset.dorso = '';
                    dorso.append(plantilla('dorso'));
                    sitio.append(dorso);
                    lugar.append(sitio);

                    if (animar) {
                        this.llegar(dorso, i * 3 + turnoDe(asiento));
                    }
                }
            });

            this.aviso = animar ? 'Repartiendo.' : this.aviso;
        },

        jugarCarta(lugar, carta, baza = null, boton = null) {
            if (lugar !== 'vos') {
                const sitio = this.dorsos(lugar).lastElementChild;
                const desde = (sitio ?? this.dorsos(lugar)).getBoundingClientRect();

                sitio?.remove();
                this.apoyar(plantilla(carta), this.hueco(lugar), desde);

                return;
            }

            const origen = boton ?? this.$refs.mano.querySelector(`button[data-carta="${carta}"]`);
            const desde = origen.getBoundingClientRect();

            this.apoyar(origen.firstElementChild, this.hueco('vos'), desde);
            origen.remove();
            this.reacomodarMano();
        },

        resolverBaza(hecho, vista) {
            const numero = hecho.numero - 1;
            const resultado = hecho.ganador === null ? 'parda' : this.lado(this.equipoDe(hecho.ganador));
            const jugadas = vista.bazas[numero].jugadas;

            this.ganadas = [...this.ganadas.slice(0, numero), resultado];

            if (resultado === 'parda') {
                this.aviso = 'Parda. Sale el gallo.';

                return;
            }

            const carta = jugadas.find(([asiento]) => asiento === hecho.ganador)[1];

            this.aviso = hecho.ganador === this.yo
                ? `Ganaste la baza con el ${nombreDe(carta)}.`
                : `${this.nombre(hecho.ganador, true)} ganó la baza con el ${nombreDe(carta)}.`;

            jugadas.forEach(([asiento]) => (this.hueco(this.lugarDe(asiento)).dataset[asiento === hecho.ganador ? 'gana' : 'pierde'] = ''));
        },

        levantarBaza() {
            const cartas = [...this.$refs.cruz.querySelectorAll('[data-hueco] > *')];

            cartas.forEach((carta, i) => this.guardar(carta, i).finished.then(() => carta.remove()));

            return this.esperar(this.reducido ? 130 : 210 + cartas.length * 30).then(() => {
                LUGARES.forEach((lugar) => {
                    delete this.hueco(lugar).dataset.gana;
                    delete this.hueco(lugar).dataset.pierde;
                });
            });
        },

        /**
         * El cierre de la mano: quién la ganó, por qué y cuánto sumó cada uno. Queda a la vista hasta que se reparte.
         */
        cerrarMano(vista, animar = true) {
            const { ganador, motivo, anotado, mostradas } = vista.cierre;
            const titulo = motivo === 'partida' ? 'Se terminó el gallito' : `${this.quienGano(ganador)} la mano`;
            const razon = this.razonDelCierre(vista);
            const lineas = anotado.map((linea) => ({
                puntos: linea.puntos,
                texto: `${this.conceptoDe(linea.concepto, vista)}, ${this.asientosDe(linea.equipo).length > 1 ? 'para cada uno' : `para ${this.asientosDe(linea.equipo)[0] === this.yo ? 'vos' : this.nombre(this.asientosDe(linea.equipo)[0])}`}`,
            }));

            this.muestran = OTROS.filter((asiento) => (mostradas[asiento] ?? []).length).map((asiento) => ({
                asiento,
                nombre: this.nombre(asiento, true),
                cartas: mostradas[asiento],
            }));

            this.callarTantos();
            this.$refs.mano.removeAttribute('data-tanto');

            const sueltas = [...this.$refs.mano.querySelectorAll('button'), ...OTROS.flatMap((asiento) => this.dorsosDe(asiento))];

            sueltas.forEach((carta, i) => {
                carta.disabled = true;

                if (animar) {
                    this.guardar(carta, i).finished.then(() => this.sacar(carta));
                } else {
                    this.sacar(carta);
                }
            });

            this.cierre = { titulo, motivo: razon, lineas };
            this.cierreDesde = Date.now();
            this.aviso = [
                `${titulo}. ${razon}`,
                ...lineas.map((linea) => `${linea.puntos} por ${linea.texto}.`),
                ...this.muestran.map((quien) => `${quien.nombre} muestra ${quien.cartas.map((carta) => `el ${nombreDe(carta)}`).join(' y ')}.`),
                vista.fase === 'por_repartir' ? 'Apretá Repartir para seguir.' : '',
            ].filter(Boolean).join(' ');
        },

        razonDelCierre(vista) {
            const { ganador, motivo } = vista.cierre;
            const elGallo = ganador === 0;

            if (motivo === 'bazas') {
                return `Bazas: ${this.ganadas.map((baza) => this.deQuien(baza)).join(', ')}.`;
            }

            if (motivo === 'mazo') {
                return elGallo ? 'Se fueron los dos de la pareja.' : 'El gallo se fue al mazo.';
            }

            if (motivo === 'no_quiero') {
                const canto = NIVEL_DE_TRUCO[vista.truco.nivel].toLowerCase();

                return elGallo ? `La pareja no quiso el ${canto}.` : `El gallo no quiso el ${canto}.`;
            }

            return `Alguien llegó a ${vista.puntosParaGanar}.`;
        },

        juntar() {
            const cartas = [...this.$refs.cruz.querySelectorAll('[data-hueco] > *')];

            this.cierre = null;
            this.muestran = [];
            this.callarDicho();
            this.aviso = 'Repartiendo.';
            cartas.forEach((carta, i) => this.guardar(carta, i));

            return this.esperar(this.reducido ? 130 : 210 + cartas.length * 30);
        },
    };

    // Las propiedades se copian con sus descriptores: un reparto común (...) leería los getters una vez y los dejaría fijos.
    return Object.defineProperties(base, Object.getOwnPropertyDescriptors(gallito));
};
