/*
 | Mesa de juego.
 |
 | Acá no hay reglas. El motor vive en el servidor: esta pantalla le manda lo
 | que hace el jugador y recibe "pasos", que son la vista de su asiento después
 | de cada jugada. Cada paso trae los hechos que ocurrieron, y este archivo los
 | cuenta de a uno con las piezas visuales: repartir, mover una carta, cantar,
 | cantar los tantos, anotar y cerrar la mano.
 |
 | El bot juega aparte, desde una cola del servidor. Mientras le toca, la mesa
 | pregunta cada tanto qué pasó después del último paso que mostró.
 |
 | Con otra persona enfrente es igual, pero no se pregunta sin parar: el servidor
 | avisa por el WebSocket que hay novedades (sin cartas ni datos, solo el número
 | del último evento) y recién ahí la mesa pregunta qué pasó. Esa pregunta se
 | hace sola cada tanto por si el aviso no llega, y también cuando se cumple el
 | plazo del turno, que resuelve el servidor.
 |
 | La pantalla habla de "vos" y del "rival": qué asiento es cada uno lo dice la
 | vista. Las cartas del rival no llegan nunca: de su mano solo se sabe cuántas
 | le quedan.
 */

import { LLEGADA, cartasDelTanto, llegarDelMazo, movimientoReducido, nombreDe, plantilla } from './cartas';
import { escucharPartida } from './echo';

const LADOS = ['vos', 'rival'];

const CANTOS = {
    envido: 'Envido',
    real_envido: 'Real envido',
    falta_envido: 'Falta envido',
    flor: 'Flor',
    contraflor: 'Contraflor',
    contraflor_al_resto: 'Contraflor al resto',
    truco: 'Truco',
    retruco: 'Retruco',
    vale_cuatro: 'Vale cuatro',
};

const ENVIDOS = ['envido', 'real_envido', 'falta_envido'];
const FLORES = ['flor', 'contraflor', 'contraflor_al_resto'];
const TRUCOS = ['truco', 'retruco', 'vale_cuatro'];
const NIVEL_DE_TRUCO = ['', 'Truco', 'Retruco', 'Vale cuatro'];

const CONCEPTOS = {
    envido: 'Envido',
    envido_no_querido: 'Envido no querido',
    envido_no_jugado: 'Envido sin jugar',
    flor: 'Flor',
    contraflor: 'Contraflor',
    contraflor_no_querida: 'Contraflor no querida',
    mano: 'Mano',
};

const CALLADO = { numero: '', frase: '', visible: false };

// Cada cuánto se le pregunta al servidor qué jugó el bot, y cuánto se lo espera antes de pedirle que juegue ya.
const CONSULTA = 600;
const RED_DE_SEGURIDAD = 5000;

// Con otra persona, cada cuánto se pregunta si no llegó ningún aviso: con el WebSocket andando es solo un respaldo.
const RESPALDO_EN_VIVO = 15000;
const RESPALDO_SIN_AVISOS = 3000;
// Cumplido el plazo, cada cuánto se vuelve a preguntar, cuánto se espera al servidor antes de pedirle
// que lo resuelva ya, y cada cuánto se le insiste como mucho.
const TRAS_EL_PLAZO = 1500;
const GRACIA_DEL_PLAZO = 2000;
const ENTRE_RECLAMOS = 5000;
// Cuando al turno propio le quedan estos segundos, la mesa lo dice.
const SEGUNDOS_DE_APURO = 10;
// Lo mínimo que el cierre de una mano queda a la vista cuando el reparto siguiente no lo pidió esta mesa.
const LECTURA_DEL_CIERRE = 2500;

export default (inicial, pedidos) => ({
    vista: inicial,
    pedidos,
    // El asiento propio y el de enfrente. Contra el bot siempre son el 0 y el 1.
    yo: inicial.asiento,
    ellos: 1 - inicial.asiento,
    puntos: { vos: inicial.tanteo[inicial.asiento], rival: inicial.tanteo[1 - inicial.asiento] },
    // Mientras se cuenta lo que pasó o se espera al servidor, no se puede hacer nada.
    ocupada: true,
    // Le toca al bot y todavía no jugó: la mesa lo marca junto a su nombre.
    pensando: false,
    // Entre personas: si el WebSocket está conectado, el último evento del que avisó y el reloj de la próxima pregunta.
    enVivo: false,
    avisado: 0,
    preguntando: false,
    relojDeVigia: null,
    ultimoReclamo: 0,
    // Quien abrió la sala ya avisó que tiene la mesa a la vista.
    presentado: false,
    // De qué lado arde el fósforo de la cuenta regresiva ('vos', 'rival' o ninguno) y sus animaciones.
    plazo: null,
    llamas: [],
    relojDeApuro: null,
    // Cuando la barra está desplegada (los niveles del envido, por ejemplo), acá van sus botones.
    menu: null,
    ganadas: [],
    repartidas: [],
    adelantada: null,
    voz: { texto: '', tono: 'copa', quien: 'vos', visible: false },
    tantos: { vos: { ...CALLADO }, rival: { ...CALLADO }, gana: null, resuelto: false },
    cierre: null,
    cierreDesde: 0,
    aviso: 'Repartiendo.',
    fin: null,
    saliendo: false,
    prisa: false,
    reducido: movimientoReducido.matches,
    relojDeVoz: null,

    init() {
        this.sellar(this.vista);

        if (this.pedidos.entrePersonas) {
            this.escuchar();
        }

        this.pintar(this.vista);
    },

    // Lo que lee la pantalla

    get enJuego() {
        return this.vista.fase === 'jugando';
    },

    get cerrada() {
        return ! this.enJuego;
    },

    get esMano() {
        return this.vista.mano === this.yo;
    },

    /**
     * De qué lado de la mesa está un asiento.
     */
    quien(asiento) {
        return asiento === this.yo ? 'vos' : 'rival';
    },

    /**
     * Cómo se nombra al rival en medio de una frase y al empezarla: "el bot", o el apodo de la otra persona.
     */
    get rival() {
        return this.pedidos.rival ?? 'el bot';
    },

    get rivalAlEmpezar() {
        return this.pedidos.rival ?? 'El bot';
    },

    /**
     * El reloj junto al nombre del rival: el bot está pensando, o le toca a la otra persona.
     */
    get piensaElRival() {
        return this.pensando || (this.pedidos.entrePersonas && this.juegaElOtro && ! this.ocupada);
    },

    /**
     * De quién fue una baza. El apodo no entra en ese renglón a 360 px: con otra persona es "del rival".
     */
    deQuien(resultado) {
        return { vos: 'tuya', rival: this.pedidos.entrePersonas ? 'del rival' : 'del bot', parda: 'parda' }[resultado];
    },

    get baza() {
        return Math.max(0, this.vista.bazas.length - 1);
    },

    get estadoDelTruco() {
        const querido = this.vista.truco.querido;

        return querido > 0 ? `${NIVEL_DE_TRUCO[querido]} querido, vale ${querido + 1}` : '';
    },

    get tamanoDeVoz() {
        // 0.56 em es el ancho medio de una letra de Piazzolla Black Italic; 0.6 em, el relleno de la ficha.
        return `min(11rem, 24dvh, calc(88cqw / ${Math.max(this.voz.texto.length, 4) * 0.56 + 0.6}))`;
    },

    resultadoDeBaza(numero) {
        return this.deQuien(this.ganadas[numero]) ?? '';
    },

    // La barra de cantos: muestra, en una sola fila, lo que el motor declara válido ahora.

    puede(tipo) {
        return this.vista.acciones.some((accion) => accion.tipo === tipo);
    },

    /**
     * Todos los botones que corresponden a este momento, en orden. Si los niveles
     * del envido son más de uno, van juntos detrás de un solo botón que se despliega.
     */
    botones() {
        const envidos = ENVIDOS.filter((canto) => this.puede(canto));
        const flores = FLORES.filter((canto) => this.puede(canto));
        const truco = TRUCOS.filter((canto) => this.puede(canto));
        const pendiente = this.vista.pendiente?.canto;

        if (this.puede('quiero')) {
            // Contestando: querer, subir lo mismo que te cantaron, no querer y, si el envido está primero, el envido.
            const subirEnvido = envidos.length > 1 ? ['grupo-subir'] : envidos;
            const envidoPrimero = envidos.length > 1 ? ['grupo-envido'] : envidos;

            return [
                'quiero',
                ...(pendiente === 'truco' ? truco : []),
                ...(pendiente === 'envido' ? subirEnvido : []),
                ...(pendiente === 'contraflor' ? flores : []),
                'no_quiero',
                ...(pendiente === 'truco' ? envidoPrimero : []),
                ...(pendiente !== 'contraflor' ? flores : []),
            ];
        }

        return [
            ...flores,
            ...(envidos.length > 1 ? ['grupo-envido'] : envidos),
            ...truco,
            ...(this.puede('mazo') ? ['mazo'] : []),
        ];
    },

    get barra() {
        if (this.fin) {
            return [];
        }

        if (this.vista.fase === 'por_repartir') {
            return ['repartir'];
        }

        if (this.menu) {
            return [...this.menu, 'volver'];
        }

        const botones = this.botones();

        // Más de cuatro no entran en una fila del celular: los que sobran van detrás de "Más".
        return botones.length > 4 ? [...botones.slice(0, 3), 'mas'] : botones;
    },

    /**
     * Si un botón de la barra se ve y en qué lugar va.
     */
    estiloDe(clave) {
        const lugar = this.barra.indexOf(clave);

        return { display: lugar === -1 ? 'none' : null, order: Math.max(lugar, 0) };
    },

    tocar(clave) {
        if (this.ocupada) {
            return;
        }

        const envidos = ENVIDOS.filter((canto) => this.puede(canto));

        if (clave === 'volver') {
            this.abrirMenu(null);
        } else if (clave === 'grupo-envido' || clave === 'grupo-subir') {
            this.abrirMenu(envidos);
        } else if (clave === 'mas') {
            this.abrirMenu(this.botones().slice(3).flatMap((boton) => (boton.startsWith('grupo-') ? envidos : [boton])));
        } else if (clave === 'repartir') {
            this.pedirReparto();
        } else {
            this.enviar({ tipo: clave });
        }
    },

    /**
     * Cambia lo que muestra la barra y deja el foco en su primer botón, para quien juega con teclado.
     */
    abrirMenu(botones) {
        this.menu = botones;
        this.$nextTick(() => this.$refs.barra.querySelector(`[data-boton="${this.barra[0]}"]`)?.focus({ preventScroll: true }));
    },

    // Hablar con el servidor

    async enviar(accion) {
        if (this.ocupada || this.fin) {
            return;
        }

        this.ocupada = true;
        this.menu = null;
        this.apagar();

        await this.pedir(this.pedidos.accion, accion);
    },

    /**
     * Toda jugada va con el último evento que mostró la mesa. Si en el servidor ya pasó algo más (se venció el
     * turno, se repartió), la rechaza: lo que se tocó mirando una mano no puede entrar en otra.
     */
    conDesde(cuerpo) {
        return { ...cuerpo, desde: this.vista.evento };
    },

    async pedirReparto() {
        if (this.ocupada || this.fin) {
            return;
        }

        const conTeclado = document.activeElement === this.$refs.repartir;

        this.ocupada = true;
        this.apagar();
        await this.juntar();
        await this.pedir(this.pedidos.repartir, {});

        // Quien llegó con el teclado al botón de repartir sigue en sus cartas.
        if (conTeclado) {
            this.$refs.mano.querySelector('button')?.focus({ preventScroll: true });
        }
    },

    async pedir(url, cuerpo) {
        let respuesta;

        try {
            respuesta = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.pedidos.token },
                body: JSON.stringify(this.conDesde(cuerpo)),
                credentials: 'same-origin',
            });
        } catch {
            this.deshacer('No se pudo conectar con la mesa. Probá de nuevo.');

            return;
        }

        if (respuesta.status === 422) {
            const { motivo } = await respuesta.json();

            // Entre personas, la jugada puede no entrar porque el servidor llegó antes: venció el turno o ya repartió.
            if (! (await this.alcanzarAlServidor())) {
                this.deshacer(motivo);
            }

            return;
        }

        // Sesión vencida o partida que ya no está en curso: la página se vuelve a cargar y el servidor decide.
        if (! respuesta.ok) {
            window.location.reload();

            return;
        }

        for (const paso of (await respuesta.json()).pasos) {
            await this.mostrar(this.sellar(paso));
        }

        await this.esperarAlBot();

        this.liberar();
    },

    /**
     * Terminó de contarse lo que pasó: la mesa se puede volver a usar. Entre personas, además,
     * arranca la cuenta regresiva de quien tenga el turno y se queda atenta a lo que haga el rival.
     */
    liberar(aviso = null) {
        this.prisa = false;
        this.ocupada = false;
        this.aviso = aviso ?? (this.indicacion() || this.aviso);
        this.enfocarLoQueSigue();
        this.vigilar();
    },

    /**
     * Le toca al otro cuando la mano está en juego y el jugador no tiene nada para hacer.
     */
    get juegaElOtro() {
        return ! this.fin && this.enJuego && ! this.vista.acciones.length;
    },

    /**
     * El bot juega en el servidor, aparte. Mientras le toque, la mesa pregunta qué pasó después del
     * último evento que mostró y cuenta los pasos que lleguen. Si pasan unos segundos sin novedades
     * le pide al servidor que juegue en el momento: es la red de seguridad por si la cola no anda.
     *
     * A otra persona no se la espera así: puede tardar todo su turno. De eso se ocupa vigilar().
     */
    async esperarAlBot() {
        let ultimaNovedad = Date.now();

        while (! this.pedidos.entrePersonas && this.juegaElOtro) {
            if (! this.pensando) {
                this.pensando = true;
                this.aviso = 'Juega el bot.';
            }

            await new Promise((listo) => setTimeout(listo, CONSULTA));

            const pasos = await this.consultar();

            if (pasos?.length) {
                this.pensando = false;

                // Entre dos jugadas seguidas del bot hay una pausa; antes de la primera ya se esperó.
                for (const [numero, paso] of pasos.entries()) {
                    await this.mostrar(paso, numero > 0);
                }

                ultimaNovedad = Date.now();
            } else if (Date.now() - ultimaNovedad > RED_DE_SEGURIDAD) {
                await this.despertarAlBot();
                ultimaNovedad = Date.now();
            }
        }

        this.pensando = false;
    },

    /**
     * Los pasos posteriores al último que se mostró. Devuelve null si no se pudo preguntar:
     * la mesa vuelve a probar en la consulta siguiente.
     */
    async consultar() {
        try {
            const respuesta = await fetch(`${this.pedidos.estado}?partida=${this.vista.partida}&desde=${this.vista.evento}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            // Sesión vencida o partida que ya no existe: la página se vuelve a cargar y el servidor decide.
            if ([401, 409, 419].includes(respuesta.status)) {
                window.location.reload();
                await new Promise(() => {});
            }

            return respuesta.ok ? (await respuesta.json()).pasos.map((paso) => this.sellar(paso)) : null;
        } catch {
            return null;
        }
    },

    /**
     * Cada paso dice cuántos segundos faltan para que el servidor resuelva la espera (el turno o el
     * reparto). Apenas llega se anota a qué hora de este navegador se cumple: lo que tarde en contarse
     * el paso en pantalla no le regala tiempo a nadie. Contra el bot no hay plazo.
     */
    sellar(paso) {
        paso.vence = paso.restan == null ? null : Date.now() + paso.restan * 1000;
        // Un plazo más largo que un turno es la espera a quien abrió la sala y todavía no llegó a la mesa.
        paso.llegando = paso.fase === 'jugando' && (paso.restan ?? 0) > this.pedidos.turno;

        return paso;
    },

    /**
     * Quien abrió la sala avisa que ya tiene la mesa a la vista: hasta entonces su turno corre con un plazo
     * de espera más largo, por si el rival se sentó mientras mandaba el link desde otra aplicación. Con la
     * pestaña tapada no avisa. El servidor contesta con lo que pasó desde lo último que la mesa mostró,
     * que incluye la llegada: así la mesa queda al día antes de que se pueda jugar.
     */
    async presentarse() {
        if (! this.pedidos.presente || this.presentado || document.hidden || this.fin) {
            return;
        }

        this.presentado = true;

        try {
            const respuesta = await fetch(this.pedidos.presente, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.pedidos.token },
                body: JSON.stringify({ desde: this.vista.evento }),
                credentials: 'same-origin',
            });

            if (! respuesta.ok) {
                return;
            }

            for (const paso of (await respuesta.json()).pasos) {
                await this.mostrar(this.sellar(paso));
            }
        } catch {
            // Sin conexión: se vuelve a probar cuando la pestaña vuelva a estar a la vista. Jugar también cuenta como llegar.
            this.presentado = false;
        }
    },

    // Con otra persona enfrente

    /**
     * Se suscribe al canal privado de la partida. Por ahí no llegan cartas ni jugadas: solo el número del
     * último evento. Si no hay tiempo real (Reverb apagado, o el navegador no puede conectar) no pasa nada:
     * queda la pregunta cada pocos segundos.
     */
    escuchar() {
        escucharPartida(this.vista.partida, {
            alAvisar: (aviso) => {
                this.avisado = Math.max(this.avisado, aviso?.evento ?? 0);
                this.ponerseAlDia();
            },
            // Al conectar (y al reconectar) se pone al día, por si un aviso pasó mientras no estaba.
            alConectar: () => {
                this.enVivo = true;
                this.ponerseAlDia();
            },
            alCaer: () => (this.enVivo = false),
        });

        // Si la pestaña estaba dormida, se entera apenas vuelve. Y si era la primera vez que se la ve, avisa que llegó.
        document.addEventListener('visibilitychange', async () => {
            if (document.hidden) {
                return;
            }

            if (! this.ocupada && this.pedidos.presente && ! this.presentado) {
                this.ocupada = true;
                this.apagar();
                await this.presentarse();
                this.liberar();

                return;
            }

            this.ponerseAlDia();
        });
    },

    /**
     * Deja programada la próxima pregunta al servidor y enciende la cuenta regresiva. Se pregunta cuando
     * llega un aviso, cuando se cumple el plazo de lo que se está esperando y, por si nada de eso pasa,
     * cada tanto. Si ya hay un aviso de algo que la mesa todavía no mostró, se pregunta ya.
     */
    vigilar(recienPreguntado = false) {
        clearTimeout(this.relojDeVigia);
        this.arder();

        if (! this.pedidos.entrePersonas || this.fin) {
            return;
        }

        const vence = this.vista.vence ?? null;
        const falta = vence === null ? null : vence - Date.now();
        let espera = this.enVivo ? RESPALDO_EN_VIVO : RESPALDO_SIN_AVISOS;

        if (falta !== null) {
            espera = falta > 0 ? Math.min(espera, falta + 400) : TRAS_EL_PLAZO;
        }

        if (! recienPreguntado && this.avisado > this.vista.evento) {
            espera = 0;
        }

        this.relojDeVigia = setTimeout(() => this.ponerseAlDia(), espera);
    },

    /**
     * Pregunta qué pasó después de lo último que mostró y lo cuenta. Mientras la mesa está ocupada con la
     * jugada propia no pregunta: ese pedido trae lo suyo, y al terminar la mesa vuelve a vigilar.
     */
    async ponerseAlDia() {
        if (this.ocupada || this.fin || this.preguntando) {
            return;
        }

        clearTimeout(this.relojDeVigia);
        this.preguntando = true;

        const desde = this.vista.evento;
        const avisado = this.avisado;
        let pasos = await this.consultar();

        // El plazo se cumplió y el servidor no lo resolvió (la cola está caída o atrasada): se le pide que lo haga ya.
        if (! pasos?.length && ! this.ocupada && this.plazoSinResolver()) {
            this.ultimoReclamo = Date.now();
            await this.reclamarElPlazo();
            pasos = await this.consultar();
        }

        this.preguntando = false;

        // Mientras se preguntaba, el jugador hizo algo y su pedido sigue en curso: al terminar, la mesa vuelve a vigilar.
        if (this.ocupada || this.fin) {
            return;
        }

        // Ese pedido ya terminó y la mesa mostró algo más nuevo que lo que se preguntó: esta respuesta quedó vieja.
        // No se cuenta, pero el reloj de la próxima pregunta tiene que quedar puesto.
        if (this.vista.evento !== desde) {
            this.vigilar();

            return;
        }

        if (! pasos?.length) {
            // Si en el medio llegó otro aviso, se pregunta de nuevo sin esperar.
            this.vigilar(this.avisado === avisado);

            return;
        }

        await this.contarLoQuePaso(pasos);
    },

    plazoSinResolver() {
        const vence = this.vista.vence ?? null;

        return vence !== null && Date.now() > vence + GRACIA_DEL_PLAZO && Date.now() - this.ultimoReclamo > ENTRE_RECLAMOS;
    },

    /**
     * La red de seguridad del plazo. El servidor decide: si todavía no se cumplió, o ya lo resolvió, no hace nada.
     */
    async reclamarElPlazo() {
        try {
            await fetch(this.pedidos.plazo, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.pedidos.token },
                credentials: 'same-origin',
            });
        } catch {
            // Sin conexión: la pregunta siguiente vuelve a probar.
        }
    },

    /**
     * Cuenta en pantalla los pasos que no salieron de un pedido propio: lo que jugó el rival, el reparto
     * que hizo el servidor o la jugada que hizo por quien se quedó sin tiempo.
     */
    async contarLoQuePaso(pasos) {
        this.ocupada = true;
        this.apagar();

        // Si lo único que pasó es que el rival llegó a la mesa, no hay nada que contar: la barra queda como estaba.
        if (pasos.some((paso) => paso.hechos.length)) {
            this.menu = null;
        }

        for (const [numero, paso] of pasos.entries()) {
            await this.mostrar(paso, numero > 0);
        }

        this.liberar();
    },

    /**
     * La jugada propia no entró. Entre personas puede ser porque el servidor llegó antes (se venció el
     * turno, o ya repartió): si hay pasos nuevos, la mesa vuelve a lo último cierto y los cuenta.
     * Devuelve si fue así; si no, queda deshacer la jugada como siempre.
     */
    async alcanzarAlServidor() {
        if (! this.pedidos.entrePersonas) {
            return false;
        }

        const pasos = await this.consultar();

        if (! pasos?.length) {
            return false;
        }

        // La carta que se había movido al tocarla no se jugó: vuelve a la mano.
        if (this.adelantada) {
            this.adelantada = null;
            this.repartir(this.vista, false);
        }

        await this.contarLoQuePaso(pasos);

        return true;
    },

    /**
     * La cuenta regresiva: un fósforo acostado en el borde del campo, del lado de quien tiene que jugar,
     * que se consume en lo que falta para el plazo. Con la mano cerrada es lo que falta para que se
     * reparta sola, y arde de tu lado, donde está "Repartir". Solo se mueve con transform.
     */
    arder() {
        this.apagar();

        const vence = this.vista.vence ?? null;
        const falta = vence === null ? 0 : vence - Date.now();

        if (! this.pedidos.entrePersonas || this.fin || falta <= 0) {
            return;
        }

        const lado = this.juegaElOtro ? 'rival' : 'vos';
        const total = (this.enJuego ? this.pedidos.turno : this.pedidos.reparto) * 1000;
        const queda = Math.min(falta / total, 1);
        const fosforo = this.$refs[lado === 'vos' ? 'plazoPropio' : 'plazoRival'];
        // Con movimiento reducido no se desliza: da un salto cada cinco segundos.
        const opciones = {
            duration: falta,
            easing: this.reducido ? `steps(${Math.max(1, Math.round(falta / 5000))}, end)` : 'linear',
            fill: 'forwards',
        };

        this.plazo = lado;
        this.llamas = [
            fosforo.querySelector('.plazo-palito').animate([{ transform: `scaleX(${queda})` }, { transform: 'scaleX(0)' }], opciones),
            // La cabeza no se achica: viaja con la punta del palito.
            fosforo.querySelector('.plazo-carril').animate(
                [{ transform: `translateX(${(1 - queda) * 100}%)` }, { transform: 'translateX(100%)' }],
                opciones,
            ),
        ];

        if (lado === 'vos' && this.enJuego && falta > SEGUNDOS_DE_APURO * 1000) {
            this.relojDeApuro = setTimeout(() => {
                if (! this.ocupada) {
                    this.aviso = `Te quedan ${SEGUNDOS_DE_APURO} segundos.`;
                }
            }, falta - SEGUNDOS_DE_APURO * 1000);
        }
    },

    apagar() {
        clearTimeout(this.relojDeApuro);
        this.llamas.forEach((llama) => llama.cancel());
        this.llamas = [];
        this.plazo = null;
    },

    async despertarAlBot() {
        try {
            await fetch(this.pedidos.bot, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.pedidos.token },
                credentials: 'same-origin',
            });
        } catch {
            // Sin conexión: la consulta siguiente vuelve a probar.
        }
    },

    /**
     * Con la mano cerrada, el foco va a "Repartir"; con la partida terminada, al cartel del final.
     * Se hace recién acá, cuando los botones ya se pueden usar.
     */
    enfocarLoQueSigue() {
        this.$nextTick(() => {
            if (this.fin) {
                this.$refs.fin.focus({ preventScroll: true });
            } else if (this.vista.fase === 'por_repartir') {
                this.$refs.repartir.focus({ preventScroll: true });
            }
        });
    },

    /**
     * La jugada no entró (el servidor la rechazó o no hubo conexión). Lo que muestra la pantalla puede
     * haber quedado viejo, así que se le pregunta al servidor cómo está la partida y se pinta eso.
     * Si tampoco contesta, queda lo último que se sabía.
     */
    async deshacer(motivo) {
        let vista = this.vista;

        try {
            const respuesta = await fetch(this.pedidos.estado, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

            if (respuesta.ok) {
                vista = this.sellar((await respuesta.json()).vista);
            }
        } catch {
            // Sin conexión: se sigue con la vista que había.
        }

        this.adelantada = null;
        this.pintar(vista, false, motivo);
    },

    /**
     * Qué puede hacer el jugador ahora, dicho en una línea.
     */
    indicacion() {
        if (this.fin || ! this.enJuego) {
            return '';
        }

        if (! this.vista.acciones.length) {
            // Mientras juega el bot lo dice esperarAlBot(); a otra persona se la espera sin trabar la mesa.
            if (! this.pedidos.entrePersonas) {
                return '';
            }

            return this.vista.llegando ? `Esperando que ${this.rival} llegue a la mesa.` : `Juega ${this.rival}.`;
        }

        if (this.puede('quiero')) {
            return `${this.rivalAlEmpezar} cantó ${this.cantoPendiente().toLowerCase()}. ¿Qué hacés?`;
        }

        return 'Jugá una carta o cantá.';
    },

    cantoPendiente() {
        const canto = this.vista.pendiente?.canto;

        if (canto === 'truco') {
            return NIVEL_DE_TRUCO[this.vista.truco.nivel];
        }

        return CANTOS[canto === 'envido' ? this.vista.envido.cadena.at(-1) : this.vista.flor.contra];
    },

    // Contar lo que pasó

    esperar(milisegundos) {
        return new Promise((listo) => setTimeout(listo, this.prisa ? Math.min(milisegundos, 80) : milisegundos));
    },

    /**
     * Tocar la mesa apura lo que se está mostrando.
     */
    apurar() {
        if (this.ocupada) {
            this.prisa = true;
        }
    },

    async mostrar(paso, conPausa = false) {
        // Entre dos jugadas seguidas del rival hay un momento, para que se lea la anterior.
        if (conPausa && paso.hechos[0]?.asiento === this.ellos) {
            await this.esperar(650);
        }

        // Si el reparto no lo pidió esta mesa (repartió el servidor, o lo apuró el rival), primero se junta lo que
        // quedó. Antes, el cierre queda a la vista lo que lleva leerlo: si el rival apura, no se lo saca de adelante.
        if (paso.hechos[0]?.tipo === 'reparto' && this.cierre) {
            await this.esperar(LECTURA_DEL_CIERRE - (Date.now() - this.cierreDesde));
            await this.juntar();
        }

        for (const hecho of paso.hechos) {
            await this.contar(hecho, paso);
        }

        if (this.tantos.vos.visible || this.tantos.rival.visible) {
            await this.esperar(1300);
            this.tantos.vos.visible = false;
            this.tantos.rival.visible = false;
            this.levantarTanto([]);
        }

        this.vista = paso;
    },

    async contar(hecho, paso) {
        const asiento = hecho.asiento ?? hecho.equipo ?? hecho.ganador ?? null;
        const quien = asiento === null ? null : this.quien(asiento);
        const rival = this.rivalAlEmpezar;

        switch (hecho.tipo) {
            case 'reparto':
                this.repartir(paso, true);
                await this.esperar(this.reducido ? 200 : 650);
                break;

            case 'carta':
                this.aviso = quien === 'vos' ? `Jugaste el ${nombreDe(hecho.carta)}.` : `${rival} jugó el ${nombreDe(hecho.carta)}.`;

                if (quien === 'vos' && this.adelantada === hecho.carta) {
                    // Esta carta ya se movió al tocarla: no se vuelve a animar.
                    this.adelantada = null;
                } else {
                    this.jugarCarta(quien, hecho.carta, this.bazaDe(paso, hecho));
                    await this.esperar(520);
                }
                break;

            case 'baza':
                this.resolverBaza(hecho, paso);
                await this.esperar(700);
                break;

            case 'canto':
                this.cantar(CANTOS[hecho.canto], TRUCOS.includes(hecho.canto) ? 'copa' : 'oro', quien);
                this.aviso = `${quien === 'vos' ? 'Cantaste' : `${rival} cantó`} ${CANTOS[hecho.canto].toLowerCase()}.`;
                await this.esperar(1250);
                break;

            case 'respuesta':
                this.cantar(hecho.quiere ? 'Quiero' : 'No quiero', hecho.quiere ? 'basto' : 'copa', quien);
                this.aviso = `${quien === 'vos' ? (hecho.quiere ? 'Quisiste' : 'No quisiste') : `${rival} ${hecho.quiere ? 'quiso' : 'no quiso'}`}.`;
                await this.esperar(1100);
                break;

            case 'tantos':
                await this.cantarTantos(hecho);
                break;

            case 'puntos':
                this.aviso = `${quien === 'vos' ? 'Sumás' : `${rival} suma`} ${hecho.puntos}: ${this.conceptoDe(hecho.concepto, paso).toLowerCase()}.`;
                await this.sumar(quien, hecho.tanteo[hecho.equipo]);
                await this.esperar(350);
                break;

            case 'mazo':
                this.aviso = this.comoSeFue(asiento, paso);
                await this.esperar(400);
                break;

            case 'mano_cerrada':
                this.vista = paso;
                this.cerrarMano(paso);
                await this.esperar(300);
                break;

            case 'partida_terminada':
                this.fin = quien;
                break;
        }
    },

    /**
     * Quién se fue al mazo, dicho en una frase. Entre personas el servidor manda al mazo a quien se
     * queda sin tiempo: ahí se cuenta eso, que no es lo mismo que irse.
     */
    comoSeFue(asiento, vista) {
        const vos = asiento === this.yo;

        if ((vista.vencio ?? null) === asiento) {
            return vos ? 'Se te venció el turno.' : `A ${this.rival} se le venció el turno.`;
        }

        return vos ? 'Te fuiste al mazo.' : `${this.rivalAlEmpezar} se fue al mazo.`;
    },

    // Piezas visuales

    hueco(numero, quien) {
        return this.$refs.bazas.children[numero].querySelector(`[data-hueco="${quien}"]`);
    },

    /**
     * Reparto: el único momento coreografiado. Las cartas salen del mazo, una
     * para cada uno, con 70 ms entre carta y carta.
     */
    llegar(elemento, orden) {
        llegarDelMazo(elemento, this.$refs.origen, orden, { reducido: this.reducido });
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
     * Los tantos se cantan como en la mesa, en el orden en que los dijo el
     * motor. El primero dice su número; el que sigue contesta con uno mayor
     * ("31 son mejores") o con "Son buenas", y ese tanto nunca llegó al
     * navegador. Después el que pierde queda a media tinta.
     */
    async cantarTantos(hecho) {
        const gana = this.quien(hecho.ganador);
        const rival = this.rivalAlEmpezar;
        // En la contraflor el tanto son las tres cartas; en el envido, las que lo arman.
        const mias = hecho.canto === 'contraflor' ? this.repartidas : cartasDelTanto(this.repartidas);

        clearTimeout(this.relojDeVoz);
        this.voz.visible = false;
        this.tantos = { vos: { ...CALLADO }, rival: { ...CALLADO }, gana, resuelto: false };

        await this.esperar(160);

        for (const [orden, dicho] of hecho.tantos.entries()) {
            const quien = this.quien(dicho.asiento);

            if (orden > 0) {
                await this.esperar(800);
            }

            if (dicho.tanto === null) {
                this.tantos[quien] = { numero: '', frase: 'Son buenas', visible: true };
                this.aviso = quien === 'vos' ? 'Decís: son buenas.' : `${rival} dice: son buenas.`;
            } else {
                this.tantos[quien] = { numero: dicho.tanto, frase: orden === 0 ? '' : 'son mejores', visible: true };
                this.aviso = `${quien === 'vos' ? 'Cantás' : `${rival} canta`} ${dicho.tanto}${orden === 0 ? '' : ', son mejores'}.`;
                // Mientras se canta tu tanto se levantan las cartas que lo arman.
                this.levantarTanto(quien === 'vos' ? mias : []);
            }
        }

        await this.esperar(650);
        this.tantos.resuelto = true;
        this.levantarTanto(gana === 'vos' ? mias : []);
    },

    /**
     * Levanta de tu mano las cartas que se indican y apaga las demás. Sin cartas, las suelta todas.
     */
    levantarTanto(cartas) {
        this.$refs.mano.toggleAttribute('data-tanto', cartas.length > 0);
        this.$refs.mano.querySelectorAll('button').forEach((boton) => {
            boton.classList.toggle('del-tanto', cartas.includes(boton.dataset.carta));
        });
    },

    /**
     * Anota los puntos de a uno, como fósforos que caen, hasta llegar al tanteo que dijo el motor.
     */
    sumar(quien, hasta) {
        return new Promise((listo) => {
            const caer = () => {
                if (this.puntos[quien] >= hasta) {
                    listo();

                    return;
                }

                this.puntos[quien]++;
                setTimeout(caer, this.prisa ? 40 : 150);
            };

            caer();
        });
    },

    conceptoDe(concepto, vista) {
        if (concepto !== 'truco') {
            return CONCEPTOS[concepto];
        }

        // Lo que vale la mano lleva el nombre del último canto querido; si nadie lo quiso, el del canto que se rechazó.
        const { nivel, querido } = vista.truco;

        return querido === nivel ? NIVEL_DE_TRUCO[querido] : `${NIVEL_DE_TRUCO[nivel]} no querido`;
    },

    // La mano

    /**
     * Pone sobre la mesa lo que dice una vista: tu mano, los dorsos del rival y lo ya jugado.
     * Es lo que se usa al cargar la página y al repartir; con "animar", las cartas llegan desde el mazo.
     */
    repartir(vista, animar) {
        this.vista = vista;
        this.menu = null;
        this.cierre = null;
        this.tantos = { vos: { ...CALLADO }, rival: { ...CALLADO }, gana: null, resuelto: false };
        this.ganadas = vista.bazas.filter((baza) => baza.cerrada).map((baza) => (baza.ganador === null ? 'parda' : this.quien(baza.ganador)));
        this.repartidas = [
            ...vista.misCartas,
            ...vista.bazas.flatMap((baza) => baza.jugadas.filter(([asiento]) => asiento === this.yo).map(([, carta]) => carta)),
        ];

        [0, 1, 2].forEach((numero) => {
            LADOS.forEach((quien) => {
                const asiento = quien === 'vos' ? this.yo : this.ellos;
                const hueco = this.hueco(numero, quien);
                const baza = vista.bazas[numero];
                const jugada = baza?.jugadas.find(([otro]) => otro === asiento);

                hueco.replaceChildren(...(jugada ? [plantilla(jugada[1])] : []));
                delete hueco.dataset.gana;
                delete hueco.dataset.pierde;

                if (baza?.cerrada && baza.ganador !== null) {
                    hueco.dataset[baza.ganador === asiento ? 'gana' : 'pierde'] = '';
                }
            });
        });

        this.$refs.rival.replaceChildren();
        this.$refs.mano.removeAttribute('data-tanto');
        [...this.$refs.mano.children].forEach((lugar) => lugar.replaceChildren());

        vista.misCartas.forEach((carta, i) => {
            const boton = document.createElement('button');

            boton.type = 'button';
            boton.className = 'naipe-jugable';
            boton.dataset.carta = carta;
            boton.setAttribute('aria-label', `${nombreDe(carta)}, jugar esta carta`);
            boton.append(plantilla(carta));
            boton.addEventListener('click', (evento) => {
                // El toque no sube hasta la mesa: ahí apuraría lo que conteste el rival.
                evento.stopPropagation();
                this.jugar(carta, boton);
            });
            this.$refs.mano.children[i].replaceChildren(boton);

            if (animar) {
                this.llegar(boton, i * 2);
            }
        });

        for (let i = 0; i < vista.cartasEnMano[this.ellos]; i++) {
            const dorso = document.createElement('div');

            dorso.append(plantilla('dorso'));
            this.$refs.rival.append(dorso);

            if (animar) {
                this.llegar(dorso, i * 2 + 1);
            }
        }

        this.aviso = animar ? 'Repartiendo.' : this.aviso;
    },

    /**
     * Deja la mesa tal como la describe una vista, sin contar nada: al cargar la página o al deshacer.
     */
    pintar(vista, animar = true, aviso = null) {
        const enJuego = vista.fase === 'jugando';

        this.puntos = { vos: vista.tanteo[this.yo], rival: vista.tanteo[this.ellos] };
        this.repartir(vista, animar && enJuego);

        if (enJuego) {
            setTimeout(async () => {
                // Si se cargó la página en el turno del bot, primero se espera lo que juegue.
                const jugabaElBot = ! this.pedidos.entrePersonas && this.juegaElOtro;

                await this.esperarAlBot();
                await this.presentarse();

                this.liberar(jugabaElBot ? null : aviso);
            }, animar && ! this.reducido ? 650 : 0);

            return;
        }

        // Entre dos manos: queda a la vista el cierre de la que terminó.
        this.cerrarMano(vista, false);
        this.fin = vista.ganador === null ? null : this.quien(vista.ganador);
        this.aviso = aviso ?? this.aviso;

        // Quien abrió la sala también avisa que llegó si cargó la página entre dos manos. Contra el bot no hace nada.
        this.presentarse().finally(() => {
            this.ocupada = false;
            this.aviso = this.enJuego ? (this.indicacion() || this.aviso) : this.aviso;
            this.vigilar();
        });
    },

    /**
     * Tocar una carta propia la mueve en el momento, sin esperar al servidor: la jugada
     * ya figura entre las válidas. Si igual volviera rechazada, la mesa se deshace sola.
     */
    jugar(carta, boton) {
        if (this.ocupada || ! this.vista.acciones.some((accion) => accion.tipo === 'jugar' && accion.carta === carta)) {
            return;
        }

        this.adelantada = carta;
        this.aviso = `Jugaste el ${nombreDe(carta)}.`;
        this.jugarCarta('vos', carta, this.baza, boton);
        this.enviar({ tipo: 'jugar', carta });
    },

    /**
     * La carta sale de la mano (o de los dorsos del rival) y cae en su lugar de la baza.
     */
    jugarCarta(quien, carta, baza, boton = null) {
        if (quien === 'rival') {
            const dorso = this.$refs.rival.lastElementChild;
            const desde = dorso.getBoundingClientRect();

            dorso.remove();
            this.apoyar(plantilla(carta), this.hueco(baza, 'rival'), desde);

            return;
        }

        const origen = boton ?? this.$refs.mano.querySelector(`button[data-carta="${carta}"]`);
        const desde = origen.getBoundingClientRect();

        this.apoyar(origen.firstElementChild, this.hueco(baza, 'vos'), desde);
        origen.remove();
    },

    /**
     * En qué baza se jugó una carta, según la vista que la trae.
     */
    bazaDe(vista, hecho) {
        return vista.bazas.findIndex((baza) => baza.jugadas.some(([asiento, carta]) => asiento === hecho.asiento && carta === hecho.carta));
    },

    resolverBaza(hecho, vista) {
        const numero = hecho.numero - 1;
        const resultado = hecho.ganador === null ? 'parda' : this.quien(hecho.ganador);
        const jugadas = vista.bazas[numero].jugadas;

        this.ganadas = [...this.ganadas.slice(0, numero), resultado];

        if (resultado === 'parda') {
            this.aviso = 'Parda. Sale el mano.';

            return;
        }

        const carta = jugadas.find(([asiento]) => asiento === hecho.ganador)[1];

        this.aviso = resultado === 'vos' ? `Ganaste la baza con el ${nombreDe(carta)}.` : `${this.rivalAlEmpezar} ganó la baza con el ${nombreDe(carta)}.`;

        // La carta que gana queda arriba y la que pierde se apaga: la baza se lee de un vistazo.
        this.hueco(numero, resultado).dataset.gana = '';
        this.hueco(numero, resultado === 'vos' ? 'rival' : 'vos').dataset.pierde = '';
    },

    /**
     * El cierre de la mano: quién la ganó, por qué y cuánto sumó cada cosa.
     * Queda a la vista hasta que se reparte. Lo que no se jugó vuelve al mazo
     * boca abajo, salvo las cartas que el rival tiene que mostrar porque ganó
     * el envido o sumó por una flor.
     */
    cerrarMano(vista, animar = true) {
        const { ganador, motivo, anotado, mostradas } = vista.cierre;
        const entrePersonas = this.pedidos.entrePersonas;
        const titulo = motivo === 'partida'
            ? 'Se terminó la partida'
            // Con otra persona el título no lleva su apodo: uno largo lo partiría en tres renglones.
            : (ganador === this.yo ? 'Ganaste la mano' : (entrePersonas ? 'Perdiste la mano' : 'La mano es del bot'));
        const razon = this.razonDelCierre(vista);
        const lineas = anotado.map((linea) => ({
            puntos: linea.puntos,
            texto: `${this.conceptoDe(linea.concepto, vista)}, para ${linea.equipo === this.yo ? 'vos' : this.rival}`,
        }));
        const delBot = (mostradas[this.ellos] ?? []).filter((carta) => ! vista.bazas.some((baza) => baza.jugadas.some(([, otra]) => otra === carta)));
        const dorsos = [...this.$refs.rival.children];

        this.tantos.vos.visible = false;
        this.tantos.rival.visible = false;
        this.$refs.mano.removeAttribute('data-tanto');
        this.$refs.mano.querySelectorAll('button').forEach((boton, i) => {
            boton.disabled = true;

            if (animar) {
                this.guardar(boton, i).finished.then(() => boton.remove());
            } else {
                boton.remove();
            }
        });

        // Al cargar la página con la mano ya cerrada no quedan dorsos: se dibujan los que hay que mostrar.
        while (dorsos.length < delBot.length) {
            const dorso = document.createElement('div');

            dorso.append(plantilla('dorso'));
            this.$refs.rival.append(dorso);
            dorsos.push(dorso);
        }

        delBot.forEach((carta, i) => this.darVuelta(dorsos[i], carta));
        dorsos.slice(delBot.length).forEach((dorso, i) => (animar ? this.guardar(dorso, i) : dorso.remove()));

        this.cierre = { titulo, motivo: razon, lineas };
        this.cierreDesde = Date.now();
        this.aviso = [
            `${titulo}. ${razon}`,
            ...lineas.map((linea) => `${linea.puntos} por ${linea.texto}.`),
            delBot.length ? `${this.rivalAlEmpezar} muestra ${delBot.map((carta) => `el ${nombreDe(carta)}`).join(' y ')}.` : '',
            vista.fase === 'por_repartir' ? (entrePersonas ? 'Se reparte solo en unos segundos. Repartir lo apura.' : 'Apretá Repartir para seguir.') : '',
        ].filter(Boolean).join(' ');
    },

    razonDelCierre(vista) {
        const { ganador, motivo } = vista.cierre;

        if (motivo === 'bazas') {
            return `Bazas: ${this.ganadas.map((baza) => this.deQuien(baza)).join(', ')}.`;
        }

        if (motivo === 'mazo') {
            // Se fue quien perdió la mano.
            return this.comoSeFue(ganador === this.yo ? this.ellos : this.yo, vista);
        }

        if (motivo === 'no_quiero') {
            const canto = NIVEL_DE_TRUCO[vista.truco.nivel].toLowerCase();

            return ganador === this.yo ? `${this.rivalAlEmpezar} no quiso el ${canto}.` : `No quisiste el ${canto}.`;
        }

        return `${ganador === this.yo ? 'Llegaste' : `${this.rivalAlEmpezar} llegó`} a ${vista.puntosParaGanar}.`;
    },

    /**
     * Junta lo que quedó en la mesa antes de pedir el reparto de la mano siguiente.
     */
    juntar() {
        const cartas = [...this.$refs.bazas.querySelectorAll('[data-hueco] > *'), ...this.$refs.rival.querySelectorAll('.giro')];

        this.cierre = null;
        this.aviso = 'Repartiendo.';
        cartas.forEach((carta, i) => this.guardar(carta, i));

        return this.esperar(this.reducido ? 130 : 210 + cartas.length * 30);
    },
});
