/*
 | Mesa de a cuatro.
 |
 | Es la misma mesa del mano a mano (mesa.js) con otra disposición: en cruz. Vos abajo, tu compañero
 | enfrente y un rival a cada lado. De mesa.js sale todo lo que no depende de cuántos son: hablar con el
 | servidor, esperar a los bots, la barra de cantos, el ritmo, el sonido, el tanteador y el final.
 | Acá va lo que cambia: dónde está cada asiento, cómo se nombra, la baza en cruz y las señas.
 |
 | El tanteo es de las parejas. "vos" y "rival", que en el mano a mano son dos personas, acá son
 | "nosotros" (los asientos 0 y 2) y "ellos" (el 1 y el 3). Cada asiento tiene además su lugar en la mesa.
 |
 | En el centro se ve una sola baza, la que se está jugando: cada carta cae delante de quien la tiró.
 | Cuando se cierra y la mano sigue, las cuatro cartas vuelven al mazo; de las anteriores queda escrito
 | de quién fue cada una.
 |
 | Las señas no son jugadas: no esperan el turno ni mueven la mesa. Las del compañero llegan con cada
 | vista (las de los rivales no viajan nunca) y las propias se mandan aparte, también mientras juegan los bots.
 */

import { asentarEnLaBaza, cartasDelTanto, nombreDe, numeroDe, paloDe, plantilla } from './cartas';
import mesa, { CALLADO, CANTOS, NIVEL_DE_TRUCO, TRUCOS } from './mesa';

// El lugar de cada asiento. Se juega hacia la derecha: después de vos tira el de tu derecha.
const LUGARES = ['vos', 'derecha', 'companero', 'izquierda'];
const OTROS = [1, 2, 3];

// Cómo se nombra a cada uno en medio de una frase y al empezarla. A los rivales se los nombra por el
// lado: es lo que se dice en la mesa y entra en un renglón del celular.
const NOMBRES = {
    1: ['el de la derecha', 'El de la derecha'],
    2: ['tu compañero', 'Tu compañero'],
    3: ['el de la izquierda', 'El de la izquierda'],
};

const sinTantos = () => ({
    vos: { ...CALLADO },
    derecha: { ...CALLADO },
    companero: { ...CALLADO },
    izquierda: { ...CALLADO },
    gana: null,
    resuelto: false,
});

export default (inicial, pedidos) => {
    const base = mesa(inicial, pedidos);

    const cuatro = {
        tantos: sinTantos(),
        // Quién está cantando, para marcarlo junto a su nombre mientras la palabra está sobre la mesa.
        habla: null,
        // El panel de las señas está desplegado, y hay una seña propia en viaje.
        senando: false,
        enviandoSena: false,
        // Las señas que ya hizo en esta mano desde esta pantalla. La vista también las trae, pero un paso
        // que el servidor armó justo antes de la seña llegaría sin ella y la volvería a ofrecer.
        senasHechas: [],
        // Las cartas que cada uno muestra al cerrar la mano, porque ganó el envido o sumó por una flor.
        muestran: [],

        // Lo que lee la pantalla

        lugarDe(asiento) {
            return LUGARES[asiento];
        },

        /**
         * A qué pareja le toca algo que el motor dice por equipo: 'vos' es la nuestra y 'rival' la de ellos.
         */
        pareja(equipo) {
            return equipo % 2 === 0 ? 'vos' : 'rival';
        },

        nombre(asiento, alEmpezar = false) {
            return NOMBRES[asiento][alEmpezar ? 1 : 0];
        },

        esDeOtro(asiento) {
            return asiento != null && asiento !== this.yo;
        },

        /**
         * A quién se espera: el que tiene que tirar o, si hay un canto sin contestar, la pareja que lo contesta.
         */
        loEsperan(asiento) {
            if (! this.enJuego || this.fin || this.seFue(asiento)) {
                return false;
            }

            const pendiente = this.vista.pendiente;

            return pendiente ? asiento % 2 === pendiente.responde : this.vista.turno === asiento;
        },

        /**
         * Le toca tirar una carta: su lugar de la cruz se marca más. Con un canto sin contestar no tira nadie.
         */
        leTocaTirar(asiento) {
            return this.enJuego && ! this.fin && ! this.vista.pendiente && this.vista.turno === asiento;
        },

        /**
         * El reloj junto al nombre de un bot: es su turno y todavía no jugó.
         */
        piensa(asiento) {
            return this.pensando && this.loEsperan(asiento);
        },

        get avisoDeEspera() {
            const quien = OTROS.find((asiento) => this.loEsperan(asiento));

            // Con un canto sin contestar pueden ser los dos rivales: contesta el primero.
            return quien === undefined || this.vista.pendiente ? 'Juega la máquina.' : `Juega ${this.nombre(quien)}.`;
        },

        seFue(asiento) {
            return this.vista.enMazo.includes(asiento);
        },

        deQuien(resultado) {
            return { vos: 'nuestra', rival: 'de ellos', parda: 'parda' }[resultado];
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
                return `Les cantaron ${this.cantoPendiente().toLowerCase()}. Contestás vos.`;
            }

            return this.puede('jugar') ? 'Jugá una carta o cantá.' : 'Podés cantar.';
        },

        // Las señas

        get senasDelCompanero() {
            return this.vista.senas?.companero ?? [];
        },

        /**
         * Las señas que podés hacer con tus cartas, y si ya hiciste cada una en esta mano.
         */
        get senasPropias() {
            const hechas = [...(this.vista.senas?.hechas ?? []), ...this.senasHechas];

            return (this.vista.senas?.tuyas ?? []).map((sena) => ({ sena, hecha: hechas.includes(sena) }));
        },

        get puedeSenar() {
            return this.enJuego && ! this.fin && ! this.seFue(this.yo) && ! this.seFue(2);
        },

        /**
         * Cómo se dibuja una seña para quien no las sabe todas: la carta, si la seña la dice entera; el
         * número solo, si no dice el palo; un lugar vacío para "no tengo nada"; y el ícono del envido.
         */
        dibujo(sena) {
            const datos = this.pedidos.senas[sena];

            if (datos.carta) {
                return this.dibujoDeCarta(datos.carta);
            }

            if (datos.numero) {
                return { tipo: 'numero', numero: datos.numero, palo: null };
            }

            return { tipo: sena === 'buen_envido' ? 'envido' : 'nada', numero: null, palo: null };
        },

        dibujoDeCarta(carta) {
            return { tipo: 'carta', numero: numeroDe(carta), palo: paloDe(carta) };
        },

        gestoDe(sena) {
            return this.pedidos.senas[sena].gesto;
        },

        hacerDe(sena) {
            return this.pedidos.senas[sena].hacer;
        },

        /**
         * Lo que quiere decir una seña, dicho por quien la recibe ("Tiene un tres") o por quien la hace ("Tengo un tres").
         */
        significa(sena, propia = false) {
            const tiene = this.pedidos.senas[sena].tiene;

            if (sena === 'nada') {
                return propia ? 'No tengo nada' : 'No tiene nada';
            }

            return `${propia ? 'Tengo' : 'Tiene'} ${tiene}`;
        },

        /**
         * Lo que te dijo tu compañero en esta mano, en una frase: "Tu compañero te dice: un tres y buen envido."
         */
        get loQueDiceElCompanero() {
            const dichas = this.senasDelCompanero.map((sena) => (sena === 'nada' ? 'que no tiene nada' : this.pedidos.senas[sena].tiene));

            if (! dichas.length) {
                return '';
            }

            const lista = dichas.length > 1 ? `${dichas.slice(0, -1).join(', ')} y ${dichas.at(-1)}` : dichas[0];

            return `Tu compañero te dice: ${lista}.`;
        },

        abrirSenas() {
            this.cerrarAjustes(false);
            this.senando = true;
            this.$nextTick(() => this.$refs.senas?.querySelector('button:not([disabled])')?.focus({ preventScroll: true }));
        },

        cerrarSenas(devolverElFoco = true) {
            if (! this.senando) {
                return;
            }

            this.senando = false;

            if (devolverElFoco) {
                this.$nextTick(() => this.$refs.botonDeSenas?.focus({ preventScroll: true }));
            }
        },

        /**
         * Le hace una seña al compañero. No pasa por el turno ni traba la mesa: se puede mientras juegan
         * los bots. El servidor dice si vale (solo de lo que tenés, y una vez por mano). De su respuesta no
         * se toma la vista: la mesa puede estar contando otra jugada, y pisarla la dejaría a mitad de camino.
         */
        async senar(sena) {
            if (this.enviandoSena || ! this.puedeSenar) {
                return;
            }

            this.enviandoSena = true;

            try {
                const respuesta = await fetch(this.pedidos.sena, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.pedidos.token },
                    body: JSON.stringify({ sena, partida: this.vista.partida }),
                    credentials: 'same-origin',
                });

                if (respuesta.ok) {
                    this.senasHechas = [...this.senasHechas, sena];
                    this.aviso = `Le dijiste a tu compañero: ${this.significa(sena, true).toLowerCase()}.`;
                } else if (respuesta.status === 422) {
                    this.aviso = (await respuesta.json()).motivo;
                }
            } catch {
                this.aviso = 'No se pudo hacer la seña. Probá de nuevo.';
            }

            this.enviandoSena = false;
        },

        // Contar lo que pasó

        async contar(hecho, paso) {
            const asiento = hecho.asiento ?? null;
            const vos = asiento === this.yo;
            const nombre = asiento === null || vos ? '' : this.nombre(asiento, true);

            switch (hecho.tipo) {
                case 'reparto':
                    this.repartir(paso, true);
                    // Son doce cartas, de a una para cada uno: tarda más que el reparto de a dos.
                    await this.esperar(this.reducido ? 200 : 1000);

                    if (this.loQueDiceElCompanero) {
                        this.aviso = this.loQueDiceElCompanero;
                        await this.leer(900);
                    }
                    break;

                case 'carta':
                    this.aviso = vos ? `Jugaste el ${nombreDe(hecho.carta)}.` : `${nombre} jugó el ${nombreDe(hecho.carta)}.`;
                    this.callarDicho(this.lugarDe(asiento));

                    if (vos && this.adelantada === hecho.carta) {
                        // Esta carta ya se movió al tocarla: no se vuelve a animar.
                        this.adelantada = null;
                    } else {
                        this.jugarCarta(this.lugarDe(asiento), hecho.carta);
                        await this.leer(520);
                    }
                    break;

                case 'baza':
                    this.resolverBaza(hecho, paso);
                    await this.leer(900);

                    // Si la mano sigue, las cartas de la baza vuelven al mazo y el centro queda libre para la que viene.
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
                    const pareja = this.pareja(hecho.equipo);

                    this.aviso = `${pareja === 'vos' ? 'Sumamos' : 'Ellos suman'} ${hecho.puntos}: ${this.conceptoDe(hecho.concepto, paso).toLowerCase()}.`;
                    await this.sumar(pareja, hecho.tanteo[hecho.equipo]);
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
                    this.fin = this.pareja(hecho.ganador);
                    this.sonar('final');
                    this.contarElFinal();
                    break;
            }
        },

        /**
         * Un canto o una respuesta: la palabra va al centro, como siempre, y se marca quién la dijo.
         */
        decir(texto, tono, asiento) {
            this.habla = asiento;
            this.cantar(texto, tono, this.pareja(asiento));
        },

        comoSeFue(asiento) {
            return asiento === this.yo ? 'Te fuiste al mazo. Tu compañero sigue solo.' : `${this.nombre(asiento, true)} se fue al mazo.`;
        },

        /**
         * Quien se va al mazo deja sus cartas: vuelven al mazo boca abajo y nadie las ve.
         */
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

        /**
         * Las cartas boca abajo de uno de los otros tres. Cada una va dentro de su lugar, que es el que
         * lleva la inclinación del abanico: lo que se mueve (al repartir, al guardar) es la carta.
         */
        dorsosDe(asiento) {
            return [...this.dorsos(this.lugarDe(asiento)).querySelectorAll('[data-dorso]')];
        },

        /**
         * Saca de la mesa una carta que ya terminó de irse. Si es un dorso, con su lugar.
         */
        sacar(carta) {
            (carta.matches('[data-dorso]') ? carta.parentElement : carta).remove();
        },

        /**
         * Los tantos se cantan en ronda desde el mano, cada uno desde su lugar. Dice su número solo quien
         * supera al mejor cantado; los demás, "son buenas", y ese tanto nunca llegó al navegador. Después
         * queda a pleno el que ganó y los otros a media tinta.
         */
        async cantarTantos(hecho) {
            const gana = this.lugarDe(hecho.ganador);
            // En la contraflor el tanto son las tres cartas; en el envido, las que lo arman.
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
                    // En dos renglones: así la ficha entra en su lugar de la cruz.
                    this.tantos[lugar] = { numero: '', frase: 'Son\nbuenas', visible: true };
                    this.aviso = vos ? 'Decís: son buenas.' : `${nombre} dice: son buenas.`;
                } else {
                    this.tantos[lugar] = { numero: dicho.tanto, frase: orden === 0 ? '' : 'son mejores', visible: true };
                    this.aviso = `${vos ? 'Cantás' : `${nombre} canta`} ${dicho.tanto}${orden === 0 ? '' : ', son mejores'}.`;
                    // Mientras se canta tu tanto se levantan las cartas que lo arman.
                    this.levantarTanto(vos ? mias : []);
                }
            }

            await this.leer(650);
            this.tantos.resuelto = true;
            this.levantarTanto(gana === 'vos' ? mias : []);
        },

        // La mano

        /**
         * Pone sobre la mesa lo que dice una vista: tu mano, los dorsos de los otros tres y la baza en juego.
         * Es lo que se usa al cargar la página y al repartir; con "animar", las cartas llegan desde el mazo.
         */
        repartir(vista, animar) {
            // Las señas son de la mano: si es otra, las de la anterior ya no cuentan.
            this.senasHechas = vista.numeroDeMano === this.vista.numeroDeMano ? this.senasHechas : [];
            this.vista = vista;
            this.menu = null;
            this.cierre = null;
            this.muestran = [];
            this.tantos = sinTantos();
            this.ganadas = vista.bazas.filter((baza) => baza.cerrada).map((baza) => (baza.ganador === null ? 'parda' : this.pareja(baza.ganador)));
            this.repartidas = [
                ...vista.misCartas,
                ...vista.bazas.flatMap((baza) => baza.jugadas.filter(([asiento]) => asiento === this.yo).map(([, carta]) => carta)),
            ];

            // En el centro va la baza en juego. Con la mano cerrada es la última, que quedó a la vista.
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

            // Se reparte de a una para cada uno, en ronda, y la primera es para el mano.
            const turnoDe = (asiento) => (asiento - vista.mano + 4) % 4;

            vista.misCartas.forEach((carta, i) => {
                const boton = document.createElement('button');

                boton.type = 'button';
                boton.className = 'naipe-jugable';
                boton.dataset.carta = carta;
                boton.setAttribute('aria-label', `${nombreDe(carta)}, jugar esta carta`);
                boton.append(plantilla(carta));
                boton.addEventListener('click', (evento) => {
                    // El toque no sube hasta la mesa: si jugó la carta, no tiene que apurar además lo que hagan los bots.
                    evento.stopPropagation();
                    this.jugar(carta, boton);
                });
                this.arrastrable(boton, carta);
                this.$refs.mano.children[i].replaceChildren(boton);

                if (animar) {
                    this.llegar(boton, i * 4 + turnoDe(this.yo));
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
                        this.llegar(dorso, i * 4 + turnoDe(asiento));
                    }
                }
            });

            this.aviso = animar ? 'Repartiendo.' : this.aviso;
        },

        /**
         * La carta sale de la mano (o de los dorsos de quien la tiró) y cae delante suyo, en el centro.
         */
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
            const resultado = hecho.ganador === null ? 'parda' : this.pareja(hecho.ganador);
            const jugadas = vista.bazas[numero].jugadas;

            this.ganadas = [...this.ganadas.slice(0, numero), resultado];

            if (resultado === 'parda') {
                this.aviso = 'Parda. Sale el mano.';

                return;
            }

            const carta = jugadas.find(([asiento]) => asiento === hecho.ganador)[1];

            this.aviso = hecho.ganador === this.yo
                ? `Ganaste la baza con el ${nombreDe(carta)}.`
                : `${this.nombre(hecho.ganador, true)} ganó la baza con el ${nombreDe(carta)}.`;

            // La carta que gana queda arriba y las demás se apagan: la baza se lee de un vistazo.
            jugadas.forEach(([asiento]) => (this.hueco(this.lugarDe(asiento)).dataset[asiento === hecho.ganador ? 'gana' : 'pierde'] = ''));
        },

        /**
         * Las cartas de una baza cerrada vuelven al mazo: el centro queda libre para la baza que sigue.
         */
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
         * El cierre de la mano: qué pareja la ganó, por qué y cuánto sumó cada cosa. Queda a la vista hasta
         * que se reparte. Lo que no se jugó vuelve al mazo boca abajo; las cartas que alguien tiene que
         * mostrar (ganó el envido o sumó por una flor) se dibujan en el cierre, con su nombre.
         */
        cerrarMano(vista, animar = true) {
            const { ganador, motivo, anotado, mostradas } = vista.cierre;
            const titulo = motivo === 'partida' ? 'Se terminó la partida' : (this.pareja(ganador) === 'vos' ? 'Ganamos la mano' : 'La mano es de ellos');
            const razon = this.razonDelCierre(vista);
            const lineas = anotado.map((linea) => ({
                puntos: linea.puntos,
                texto: `${this.conceptoDe(linea.concepto, vista)}, para ${this.pareja(linea.equipo) === 'vos' ? 'nosotros' : 'ellos'}`,
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
            const nuestra = this.pareja(ganador) === 'vos';

            if (motivo === 'bazas') {
                return `Bazas: ${this.ganadas.map((baza) => this.deQuien(baza)).join(', ')}.`;
            }

            if (motivo === 'mazo') {
                // La mano se pierde así solo cuando se van los dos de la pareja.
                return nuestra ? 'Los dos rivales se fueron al mazo.' : 'Se fueron los dos al mazo.';
            }

            if (motivo === 'no_quiero') {
                const canto = NIVEL_DE_TRUCO[vista.truco.nivel].toLowerCase();

                return nuestra ? `Ellos no quisieron el ${canto}.` : `No quisimos el ${canto}.`;
            }

            return `${nuestra ? 'Llegamos' : 'Ellos llegaron'} a ${vista.puntosParaGanar}.`;
        },

        /**
         * Junta lo que quedó en la mesa antes de pedir el reparto de la mano siguiente.
         */
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
    return Object.defineProperties(base, Object.getOwnPropertyDescriptors(cuatro));
};
