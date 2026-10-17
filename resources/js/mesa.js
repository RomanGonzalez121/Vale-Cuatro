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
 |
 | Al terminar la partida, lo que sigue también lo decide el servidor. En una
 | serie al mejor de tres, la partida siguiente ya está repartida y se va a ella.
 | Si no, queda la revancha: con otra persona se pide y el otro la contesta, y la
 | mesa se entera igual que de una jugada, por un aviso sin datos y preguntando.
 */

import { BLOQUEADOS, PRENDIDOS, SIN_AVISOS, apagarAvisos, avisarPresencia, estadoDeAvisos, prenderAvisos } from './avisos';
import { LLEGADA, asentarEnLaBaza, cantoEnRenglones, cartasDelTanto, llegarDelMazo, movimientoReducido, nombreDe, plantilla, resorte } from './cartas';

// La carta cae en la baza con un resorte de poco rebote: se pasa apenas y se apoya. Y la mano se junta igual.
const CAE = resorte(0.4, 0.15);
const SE_JUNTA = resorte(0.38, 0.12);
// El sello de la libreta golpea el papel: un resorte corto, con el rebote justo para sentir el golpe.
const SELLA = resorte(0.36, 0.22);
// Entre un sello y el siguiente, cuando la partida dio varios.
const ENTRE_SELLOS = 170;
import { hacerArrastrable } from './arrastre';
import { escucharPartida } from './echo';
import { apagarSonido, audioAbierto, guardarSonido, prenderSonido, sonar, sonidoGuardado } from './sonido';

const LADOS = ['vos', 'rival'];

export const CANTOS = {
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
export const TRUCOS = ['truco', 'retruco', 'vale_cuatro'];
export const NIVEL_DE_TRUCO = ['', 'Truco', 'Retruco', 'Vale cuatro'];

const CONCEPTOS = {
    envido: 'Envido',
    envido_no_querido: 'Envido no querido',
    envido_no_jugado: 'Envido sin jugar',
    flor: 'Flor',
    contraflor: 'Contraflor',
    contraflor_no_querida: 'Contraflor no querida',
    mano: 'Mano',
};

export const CALLADO = { numero: '', frase: '', visible: false };

// El ritmo de la mesa lo elige quien juega, con el botón de la barra. No cambia el juego: cambia cuánto se detiene
// la mesa. "pausas" multiplica las pausas para leer cada jugada (1 es lo escrito en cada lugar); "piensa" es lo
// mínimo que tarda en verse una jugada del bot, que en el servidor juega apenas le toca; "consulta", cada cuánto
// se le pregunta al servidor qué jugó.
const RITMOS = {
    tranquilo: { pausas: 1, piensa: 1000, consulta: 600 },
    agil: { pausas: 0.7, piensa: 0, consulta: 300 },
};
// La elección queda en el navegador, como el modo de día y de noche: vale también para quien juega sin cuenta.
const CLAVE_DEL_RITMO = 'vale-cuatro:ritmo';
const RITMO_DE_FABRICA = 'tranquilo';

function ritmoGuardado() {
    try {
        return localStorage.getItem(CLAVE_DEL_RITMO) ?? RITMO_DE_FABRICA;
    } catch {
        // Sin almacenamiento (modo privado estricto) vale el de fábrica.
        return RITMO_DE_FABRICA;
    }
}
// Cuánto se espera al bot antes de pedirle que juegue ya.
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
// Los fósforos del nivel en el final: uno cada tanto, después de contar los tanteadores, y un respiro al llenar la tira.
const ENTRE_FOSFOROS_DEL_NIVEL = 220;
const AL_LLENAR_LA_TIRA = 420;
const POR_NIVEL = 10;
const ULTIMO_NIVEL = 40;
const TIPO_DE_PREMIO = { carta: 'Carta', dorso: 'Dorso', mesa: 'Mesa', mazo: 'Mazo', anotar: 'Anotar', distintivo: 'Distintivo', titulo: 'Título' };
// Los premios del final: cada carta entra boca abajo y se da vuelta al asentarse (la vuelta es la de .giro, 300 ms).
// Entre una carta y la siguiente, la entrada entera más un respiro: se ven de a una.
const ENTRA_EL_PREMIO = 220;
const ENTRE_PREMIOS = 80;
// Las animaciones y relojes de los premios que se están mostrando, para apurarlos. Fuera del estado de Alpine: una
// animación envuelta en su proxy no se deja terminar.
const premiosEnCurso = { relojes: [], animaciones: [] };

// En la mesa ancha el mazo se pasa a quien reparte antes de repartir: un movimiento sobre la mesa, de 260 ms.
const PASE_DEL_MAZO = 260;

// Anotar puntos: cuánto hay entre un fósforo y el siguiente, y lo máximo que tarda en caer una suma entera.
const ENTRE_FOSFOROS = 150;
const SUMA_MAS_LARGA = 1000;
// La revancha: cuánto se espera, cumplida la hora de un pedido, antes de preguntarle al servidor en qué quedó.
const TRAS_EL_PEDIDO = 400;
// En estos estados ya no va a cambiar nada: se deja de preguntar.
const REVANCHA_CERRADA = ['aceptada', 'rechazada', 'no_disponible'];
// Sin ningún pedido a la vista, la pregunta de respaldo se hace solo durante este rato: una pestaña olvidada
// en el final de una partida no le pregunta al servidor para siempre. Un aviso en vivo sigue llegando igual.
const ATENCION_A_LA_REVANCHA = 180000;

// El final de la partida: los dos tanteadores se cuentan de corrido, a un fósforo cada tanto, después de que entra el cartel.
const ENTRE_FOSFOROS_DEL_FINAL = 32;
const ANTES_DE_CONTAR_EL_FINAL = 320;

// Las medidas de la letra de un canto en la mesa: lo más grande que llega a ser, y el ancho medio de una letra
// de Piazzolla Black Italic y el relleno de la ficha, las dos en em.
const REMS_DE_LA_LETRA_MAS_GRANDE = 11;
const ANCHO_DE_LETRA = 0.56;
const RELLENO_DEL_CANTO = 0.6;

export default (inicial, pedidos) => ({
    vista: inicial,
    pedidos,
    // El asiento propio y el de enfrente. Contra el bot siempre son el 0 y el 1.
    yo: inicial.asiento,
    ellos: 1 - inicial.asiento,
    puntos: { vos: inicial.tanteo[inicial.asiento], rival: inicial.tanteo[1 - inicial.asiento] },
    // Mientras se cuenta lo que pasó o se espera al servidor, no se puede hacer nada.
    ocupada: true,
    // Lo último que dijo otro en la mesa (un canto, "quiero"), en una fichita sobre sus cartas.
    dicho: { lugar: null, texto: '', tono: '' },
    // Lo que no tiene otro lugar en la mesa (una jugada que no entró): se ve arriba de tu mano.
    nota: '',
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
    // El ritmo que eligió quien juega: ágil o tranquilo.
    agil: ritmoGuardado() === 'agil',
    // Si la mesa suena. Arranca apagada, salvo que quien juega la haya dejado prendida.
    sonido: sonidoGuardado(),
    // Los avisos al equipo en este navegador (entre personas): 'no' (no hay), 'bloqueados', 'apagados' o 'prendidos'.
    avisos: SIN_AVISOS,
    cambiandoAvisos: false,
    // De qué lado arde el fósforo de la cuenta regresiva ('vos', 'rival' o ninguno) y sus animaciones.
    plazo: null,
    llamas: [],
    relojDeApuro: null,
    // Cuando la barra está desplegada (los niveles del envido, por ejemplo), acá van sus botones.
    menu: null,
    ganadas: [],
    repartidas: [],
    adelantada: null,
    demoraDelReparto: 0,
    // Lo que la partida le anotó al nivel (llega en el paso que la termina) y la tira que se cuenta en el final.
    experiencia: null,
    // Si ya se muestran los sellos nuevos de la libreta, después de contar la tira.
    sellando: false,
    tira: { nivel: 1, lleva: 0, subio: false, contada: false },
    // Lo que dio la partida, después de los sellos: si ya se ve, si va con palabras (las cartas no llegaron) y adónde lleva el link.
    premios: { aLaVista: false, enPalabras: false, enlace: null },
    tipoDePremio: TIPO_DE_PREMIO,
    voz: { texto: '', tono: 'copa', quien: 'vos', visible: false },
    tantos: { vos: { ...CALLADO }, rival: { ...CALLADO }, gana: null, resuelto: false },
    cierre: null,
    cierreDesde: 0,
    aviso: 'Repartiendo.',
    fin: null,
    // En un desafío (una mano con objetivo): si se cumplió, cuando la mano ya cerró. Lo dice el servidor.
    superado: null,
    // La ventanita con el objetivo del desafío está desplegada.
    conObjetivo: false,
    // El final de la partida: los puntos que van contando los dos tanteadores del cartel.
    cuenta: { vos: 0, rival: 0 },
    // La revancha entre dos personas: en qué está (lo dice el servidor), cuántos segundos le quedan a un
    // pedido a la vista y a qué hora de este navegador se cumple. "pidiendo" es que hay un pedido propio en viaje.
    revancha: { estado: null, restan: null, por: null, vence: null, pidiendo: false },
    // Cuántos pedidos propios y cuántas preguntas salieron: una respuesta que no es de lo último que salió es vieja.
    pedidosDeRevancha: 0,
    preguntasDeRevancha: 0,
    // Hasta cuándo se pregunta por las dudas, sin que llegue un aviso.
    atentaHasta: 0,
    relojDeRevancha: null,
    segunderoDeRevancha: null,
    saliendo: false,
    // La ventanita de los ajustes (modo, sonido y ritmo) está desplegada.
    ajustando: false,
    prisa: false,
    relojDeVoz: null,

    init() {
        this.sellar(this.vista);

        // Si venía prendido de antes, el audio se abre ya, y arranca a sonar con el primer toque.
        if (this.sonido) {
            prenderSonido();
        }

        if (this.pedidos.entrePersonas) {
            this.escuchar();
        }

        if (this.pedidos.avisos) {
            estadoDeAvisos().then((cual) => (this.avisos = cual));
            // A quien tiene la mesa a la vista no se le manda un aviso: el servidor se entera por acá.
            avisarPresencia(this.pedidos.avisos);
        }

        this.pintar(this.vista);

        if (this.esManoConObjetivo) {
            // El objetivo se muestra solo, una vez, cuando la mesa queda libre por primera vez: el reparto ya llegó y,
            // si el bot salía primero, ya jugó. No va a un tiempo fijo: en un equipo lento el reparto tarda más.
            // Se corre con la primera jugada.
            let mostrado = false;
            const mostrar = () => {
                if (! mostrado && this.enJuego && ! this.ocupada) {
                    mostrado = true;
                    this.abrirObjetivo(false);
                }
            };

            this.$watch('ocupada', (ocupada) => (ocupada ? this.cerrarObjetivo(false) : mostrar()));
            this.$nextTick(mostrar);
        }
    },

    // Lo que lee la pantalla

    /**
     * Cómo queda la serie con esta partida: lo que traía, más la que se acaba de ganar. Sin serie, null.
     */
    get marcadorDeLaSerie() {
        const serie = this.pedidos.serie;

        if (! serie) {
            return null;
        }

        return { vos: serie.vos + (this.fin === 'vos' ? 1 : 0), rival: serie.rival + (this.fin === 'rival' ? 1 : 0) };
    },

    /**
     * La partida terminó y la serie no: nadie llegó a las que hacen falta, y la siguiente ya está repartida.
     */
    get sigueLaSerie() {
        const marcador = this.marcadorDeLaSerie;

        return Boolean(this.fin && marcador) && Math.max(marcador.vos, marcador.rival) < this.pedidos.serie.necesarias;
    },

    get fraseDeLaSerie() {
        const marcador = this.marcadorDeLaSerie;

        if (! this.fin || ! marcador) {
            return '';
        }

        if (this.sigueLaSerie) {
            return `La serie va ${marcador.vos} a ${marcador.rival}.`;
        }

        // Quien la ganó va primero, como se dice hablando: "ganaste 2 a 1", "ganó 2 a 1".
        return marcador.vos > marcador.rival
            ? `Ganaste la serie ${marcador.vos} a ${marcador.rival}.`
            : `${this.rivalAlEmpezar} ganó la serie ${marcador.rival} a ${marcador.vos}.`;
    },

    /**
     * Si la partida es un desafío: una sola mano con un objetivo, que se cierra cuando la mano termina.
     * Ahí no hay final de partida ni reparto: lo que sigue es reintentar o volver a la lista.
     */
    get esManoConObjetivo() {
        return Boolean(this.pedidos.practica?.esMano);
    },

    /**
     * El desafío ya cerró: la mano terminó, se haya cumplido el objetivo o no.
     */
    get cerroElDesafio() {
        return this.esManoConObjetivo && ! this.enJuego && this.cierre !== null;
    },

    /**
     * En una partida de la escalera o de la gira, lo que deja dicho al terminar: se pasó o no. Las frases las
     * manda el servidor, que sabe si es un escalón o una parada.
     */
    get fraseDeLaEscalera() {
        const frases = this.pedidos.practica?.frases;

        if (! frases || this.pedidos.practica.esMano || ! this.fin || this.sigueLaSerie) {
            return '';
        }

        if (this.fin !== 'vos') {
            return frases.noSalio;
        }

        return this.pedidos.practica.esElUltimo ? frases.final : frases.paso;
    },

    abrirObjetivo(conFoco = true) {
        this.cerrarAjustes(false);
        this.conObjetivo = true;

        if (conFoco) {
            this.$nextTick(() => this.$refs.objetivo?.focus({ preventScroll: true }));
        }
    },

    cerrarObjetivo(devolverElFoco = true) {
        if (! this.conObjetivo) {
            return;
        }

        this.conObjetivo = false;

        if (devolverElFoco) {
            this.$nextTick(() => this.$refs.botonDeObjetivo?.focus({ preventScroll: true }));
        }
    },

    /**
     * En un torneo, lo que esta partida deja dicho al terminar: se pasa de ronda, se es campeón o se queda afuera.
     */
    get fraseDelTorneo() {
        const torneo = this.pedidos.torneo;

        if (! torneo || ! this.fin) {
            return '';
        }

        if (this.fin !== 'vos') {
            return 'Quedaste afuera del torneo.';
        }

        return torneo.esFinal ? 'Sos el campeón del torneo.' : `Pasás ${torneo.sigue}.`;
    },

    /**
     * Hay un pedido de revancha esperando respuesta, propio o del rival: mientras tanto no se ofrece otra cosa.
     */
    get revanchaALaVista() {
        return ['pedida', 'te_piden'].includes(this.revancha.estado);
    },

    get avisoDeRevancha() {
        const { estado, por } = this.revancha;

        switch (estado) {
            case 'pedida':
                return `Le pediste la revancha a ${this.rival}.`;
            case 'te_piden':
                return `${this.rivalAlEmpezar} te pide la revancha.`;
            case 'aceptada':
                return 'Hay revancha.';
            case 'rechazada':
                return por === 'vos' ? 'No quisiste la revancha.' : `${this.rivalAlEmpezar} no quiso la revancha.`;
            case 'agotada':
                // Sin "por", no la canceló nadie ni faltó respuesta: alguno de los dos ya estaba en otra partida.
                return { vos: 'Cancelaste el pedido.', rival: `${this.rivalAlEmpezar} no contestó.` }[por] ?? 'Ya no se puede jugar la revancha.';
            case 'no_disponible':
                return 'Ya no se puede jugar la revancha.';
            default:
                return '';
        }
    },

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
     * El tanto de envido propio, ya sumado por el servidor. Solo lo recibe quien declaró en su perfil
     * que está aprendiendo; para los demás es null y la mesa no muestra nada.
     */
    get tantoPropio() {
        return this.vista.ayudas?.tanto ?? null;
    },

    /**
     * Si la persona pidió menos movimiento. Se pregunta cada vez: puede cambiarlo con la mesa abierta.
     */
    get reducido() {
        return movimientoReducido.matches;
    },

    /**
     * De qué lado de la mesa está un asiento.
     */
    quien(asiento) {
        return asiento === this.yo ? 'vos' : 'rival';
    },

    /**
     * Si una jugada la hizo otro y no quien mira la mesa. Mano a mano el otro es uno solo; la mesa de
     * cuatro (mesa-cuatro.js) lo cambia, porque ahí los otros son tres.
     */
    esDeOtro(asiento) {
        return asiento === this.ellos;
    },

    /**
     * Lo que dice la mesa mientras espera la jugada del bot.
     */
    get avisoDeEspera() {
        return 'Juega la máquina.';
    },

    /**
     * Cómo se nombra al rival en medio de una frase y al empezarla: "la máquina", o el apodo de la otra persona.
     */
    get rival() {
        return this.pedidos.rival ?? 'la máquina';
    },

    get rivalAlEmpezar() {
        return this.pedidos.rival ?? 'La máquina';
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
        return { vos: 'tuya', rival: this.pedidos.entrePersonas ? 'del rival' : 'de la máquina', parda: 'parda' }[resultado];
    },

    get baza() {
        return Math.max(0, this.vista.bazas.length - 1);
    },

    /**
     * Lo que se cantó en esta mano, para la planilla del costado de la mesa ancha. Como la planilla de los
     * cantos del bridge, cada renglón es una vuelta: lo que dijo cada uno va en su columna, y en un mismo
     * renglón lo de la izquierda se dijo antes. Cada cosa lleva el color de su ficha en la barra de cantos.
     * Quién ganó los tantos va en un renglón entero. Es lo que la mesa ya fue contando, para releerlo.
     */
    get planilla() {
        const filas = [];

        for (const dicho of this.vista.cantos ?? []) {
            if (dicho.tipo === 'tantos') {
                const gano = this.quien(dicho.ganador) === 'vos' ? 'ganaste vos' : `ganó ${this.rival}`;

                filas.push({ resultado: `${CANTOS[dicho.canto]}: ${gano}` });
                continue;
            }

            const lado = this.quien(dicho.asiento);
            const cosa = dicho.tipo === 'respuesta'
                ? { texto: dicho.quiere ? 'Quiero' : 'No quiero', tono: dicho.quiere ? 'basto' : 'copa' }
                : { texto: CANTOS[dicho.canto], tono: TRUCOS.includes(dicho.canto) ? 'copa' : null };
            const ultima = filas[filas.length - 1];
            // Sigue en el mismo renglón si su lugar está libre y no queda a la izquierda de algo dicho antes.
            const entra = ultima && ! ultima.resultado && ! ultima[lado] && ! (lado === 'vos' && ultima.rival);

            if (entra) {
                ultima[lado] = cosa;
            } else {
                filas.push({ vos: null, rival: null, [lado]: cosa });
            }
        }

        return filas;
    },

    /**
     * De dónde salen las cartas al repartir: el mazo del costado en la mesa ancha, o el de al lado del
     * rival en las demás. Es el mismo mazo dibujado en dos lugares, y se ve uno solo.
     */
    get mazo() {
        const alCostado = this.$refs.mazoAlCostado;

        return alCostado && alCostado.offsetParent !== null ? alCostado : this.$refs.origen;
    },

    get estadoDelTruco() {
        const querido = this.vista.truco.querido;

        return querido > 0 ? `${NIVEL_DE_TRUCO[querido]} querido, vale ${querido + 1}` : '';
    },

    get tamanoDeVoz() {
        // 0.56 em es el ancho medio de una letra de Piazzolla Black Italic; 0.6 em, el relleno de la ficha.
        // Manda el renglón más largo, y con dos renglones cada uno tiene la mitad del alto.
        const renglones = this.voz.texto.split('\n');
        const largo = Math.max(...renglones.map((renglon) => renglon.length), 4);

        return `min(${REMS_DE_LA_LETRA_MAS_GRANDE}rem, calc(var(--alto, 100dvh) * ${0.24 / renglones.length}), calc(88cqw / ${largo * ANCHO_DE_LETRA + RELLENO_DEL_CANTO}))`;
    },

    /**
     * El canto, partido en dos renglones si en esta mesa así se lee bastante más grande (en el celular; en
     * una pantalla ancha entra entero). La decisión es la misma que en la repetición (cantoEnRenglones);
     * lo propio de la mesa es cómo mide la letra: las tres medidas que tamanoDeVoz le pasa a los estilos
     * (11rem, una parte del alto y cqw), sacadas acá en píxeles.
     */
    enRenglones(texto) {
        return cantoEnRenglones(texto, (renglones) => Math.min(
            REMS_DE_LA_LETRA_MAS_GRANDE * parseFloat(getComputedStyle(document.documentElement).fontSize),
            (window.innerHeight * 0.24) / renglones.length,
            (this.$root.clientWidth * 0.88) / (Math.max(...renglones.map((renglon) => renglon.length), 4) * ANCHO_DE_LETRA + RELLENO_DEL_CANTO),
        ));
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

        // Un desafío es una sola mano: cuando cierra no se reparte. Lo que sigue son los botones de su cierre,
        // que no pasan por acá (son un formulario y un link): la fila de cantos queda vacía.
        if (this.esManoConObjetivo && ! this.enJuego) {
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
        this.nota = '';
        this.callarDicho();
        this.apagar();

        await this.pedir(this.pedidos.accion, accion);
    },

    /**
     * Toda jugada va con el último evento que mostró la mesa. Si en el servidor ya pasó algo más (se venció el
     * turno, se repartió), la rechaza: lo que se tocó mirando una mano no puede entrar en otra.
     */
    /**
     * Lo que acompaña a todo pedido de la mesa: de qué partida habla y cuál fue el último evento que mostró.
     * Con eso el servidor rechaza lo que se decidió mirando una pantalla atrasada, y nunca aplica en una
     * partida lo que se tocó mirando otra (en una serie, la siguiente se reparte apenas termina la anterior).
     */
    conDesde(cuerpo = {}) {
        return { ...cuerpo, partida: this.vista.partida, desde: this.vista.evento };
    },

    async pedirReparto() {
        if (this.ocupada || this.fin) {
            return;
        }

        const conTeclado = document.activeElement === this.$refs.repartir;

        this.ocupada = true;
        this.nota = '';
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
                this.aviso = this.avisoDeEspera;
            }

            await new Promise((listo) => setTimeout(listo, this.ritmo.consulta));

            const pasos = await this.consultar();

            if (pasos?.length) {
                // En el servidor el bot juega apenas le toca. La pausa para que parezca que piensa la pone la
                // mesa: lo que falte para llegar al mínimo del ritmo elegido. Tocar la mesa la saltea.
                await this.esperar(this.ritmo.piensa - (Date.now() - ultimaNovedad));
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
                body: JSON.stringify(this.conDesde()),
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
                this.consultarRevancha();
            },
            alCaer: () => (this.enVivo = false),
            // Con la partida terminada, por el mismo canal llega que alguien pidió o contestó la revancha.
            alRevancha: () => this.consultarRevancha(),
        });

        // Quien pidió la revancha y se va de la pantalla la retira: el otro no se queda contestando a nadie.
        window.addEventListener('pagehide', () => this.retirarLaRevancha());

        // Si la pestaña estaba dormida, se entera apenas vuelve. Y si era la primera vez que se la ve, avisa que llegó.
        document.addEventListener('visibilitychange', async () => {
            if (document.hidden) {
                return;
            }

            if (this.fin) {
                this.atentaHasta = Date.now() + ATENCION_A_LA_REVANCHA;
                this.consultarRevancha();

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

    // La revancha

    /**
     * Con la partida terminada entre dos personas, la mesa queda atenta a la revancha: pregunta en qué está
     * y vuelve a preguntar cuando llega un aviso, cuando se cumple la hora de un pedido y, por las dudas, cada
     * tanto. Contra el bot no hay nada que atender (el botón del final la juega en el momento), y en una serie
     * que sigue tampoco: la partida siguiente ya está repartida.
     */
    atenderLaRevancha() {
        if (! this.pedidos.entrePersonas || ! this.fin || this.sigueLaSerie) {
            return;
        }

        this.atentaHasta = Date.now() + ATENCION_A_LA_REVANCHA;
        this.consultarRevancha();
    },

    async consultarRevancha() {
        // Con la pestaña tapada no se pregunta: al volver a estar a la vista se pregunta de nuevo.
        if (! this.pedidos.entrePersonas || ! this.fin || this.sigueLaSerie || document.hidden || REVANCHA_CERRADA.includes(this.revancha.estado)) {
            return;
        }

        clearTimeout(this.relojDeRevancha);

        const pedidos = this.pedidosDeRevancha;
        const pregunta = ++this.preguntasDeRevancha;

        try {
            const respuesta = await fetch(`${this.pedidos.revancha.estado}?partida=${this.vista.partida}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            // Sesión vencida: la página se vuelve a cargar y el servidor decide.
            if ([401, 419].includes(respuesta.status)) {
                window.location.reload();

                return;
            }

            const datos = respuesta.ok ? await respuesta.json() : null;

            // Mientras se preguntaba salió un pedido propio (pedir, contestar) u otra pregunta más nueva:
            // lo que vale es esa respuesta, no esta.
            if (pedidos !== this.pedidosDeRevancha || pregunta !== this.preguntasDeRevancha) {
                return;
            }

            if (datos) {
                this.ponerLaRevancha(datos);

                return;
            }

            // El servidor contestó que esa partida no es de quien pregunta: no hay nada más que preguntar.
            if (respuesta.status === 404) {
                this.ponerLaRevancha({ estado: 'no_disponible', restan: null, por: null });

                return;
            }
        } catch {
            // Sin conexión por un rato: se vuelve a preguntar.
        }

        this.volverAPreguntarPorLaRevancha();
    },

    /**
     * Deja la revancha como dice el servidor. Si ya hay partida nueva, va a ella; si no, deja puesto el
     * reloj de la próxima pregunta.
     */
    ponerLaRevancha({ estado, restan, por }) {
        this.revancha = { ...this.revancha, estado, restan, por, vence: restan === null ? null : Date.now() + restan * 1000 };

        if (estado === 'aceptada') {
            window.location.assign(this.pedidos.mesa);

            return;
        }

        this.volverAPreguntarPorLaRevancha();
    },

    volverAPreguntarPorLaRevancha() {
        clearTimeout(this.relojDeRevancha);
        clearInterval(this.segunderoDeRevancha);

        if (REVANCHA_CERRADA.includes(this.revancha.estado)) {
            return;
        }

        const { vence } = this.revancha;
        let espera = this.enVivo ? RESPALDO_EN_VIVO : RESPALDO_SIN_AVISOS;

        if (vence !== null) {
            // Con un pedido a la vista corre la cuenta, y se pregunta apenas se cumple su hora: el servidor dice que se venció.
            this.segunderoDeRevancha = setInterval(() => this.descontarLaRevancha(), 1000);
            espera = Math.min(espera, Math.max(0, vence - Date.now()) + TRAS_EL_PEDIDO);
        } else if (Date.now() > this.atentaHasta) {
            // Pasó el rato sin que nadie pidiera nada: ya no se pregunta por las dudas. Si el otro la pide,
            // llega el aviso en vivo; y al volver a mirar la pestaña se pregunta una vez más.
            return;
        }

        this.relojDeRevancha = setTimeout(() => this.consultarRevancha(), espera);
    },

    /**
     * Los segundos que le quedan al pedido a la vista, contados contra la hora que se anotó al recibirlo.
     */
    descontarLaRevancha() {
        const { vence } = this.revancha;

        if (vence !== null) {
            this.revancha.restan = Math.max(0, Math.ceil((vence - Date.now()) / 1000));
        }
    },

    /**
     * Pedir, querer, no querer o cancelar. El servidor contesta en qué quedó, y eso es lo que se muestra.
     */
    async revanchar(que) {
        if (this.revancha.pidiendo) {
            return;
        }

        this.revancha.pidiendo = true;
        this.pedidosDeRevancha++;
        clearTimeout(this.relojDeRevancha);

        try {
            const respuesta = await fetch(this.pedidos.revancha[que], {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.pedidos.token },
                body: JSON.stringify({ partida: this.vista.partida }),
                credentials: 'same-origin',
            });

            // Sesión vencida: la página se vuelve a cargar y el servidor decide.
            if ([401, 419].includes(respuesta.status)) {
                window.location.reload();

                return;
            }

            if (respuesta.ok) {
                this.revancha.pidiendo = false;
                this.ponerLaRevancha(await respuesta.json());

                return;
            }
        } catch {
            // Sin conexión: queda como estaba y se puede volver a tocar.
        }

        this.revancha.pidiendo = false;
        this.volverAPreguntarPorLaRevancha();
    },

    /**
     * Al irse de la página con un pedido propio sin contestar, se lo retira. Sale con "keepalive" para que
     * el navegador lo mande aunque la página ya se esté cerrando. Si no llegara, el pedido se vence solo.
     */
    retirarLaRevancha() {
        if (this.revancha.estado !== 'pedida') {
            return;
        }

        try {
            fetch(this.pedidos.revancha.cancelar, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.pedidos.token },
                body: JSON.stringify({ partida: this.vista.partida }),
                credentials: 'same-origin',
                keepalive: true,
            }).catch(() => {});
        } catch {
            // Un navegador sin "keepalive": el pedido se vence solo al minuto.
        }
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
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.pedidos.token },
                body: JSON.stringify({ partida: this.vista.partida }),
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
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.pedidos.token },
                body: JSON.stringify({ partida: this.vista.partida }),
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
            const respuesta = await fetch(`${this.pedidos.estado}?partida=${this.vista.partida}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

            // Sesión vencida, o la partida que se mostraba ya no se juega (la jugada sí había entrado y la cerró):
            // la página se vuelve a cargar y el servidor decide qué mostrar.
            if ([401, 409, 419].includes(respuesta.status)) {
                window.location.reload();
                await new Promise(() => {});
            }

            if (respuesta.ok) {
                vista = this.sellar((await respuesta.json()).vista);
            }
        } catch {
            // Sin conexión: se sigue con la vista que había.
        }

        this.adelantada = null;
        this.nota = motivo;
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

    /**
     * Te toca: la mano está en juego, la mesa terminó de contar lo que pasó y tenés algo para hacer.
     * No se escribe: tu mano sube un poco y la barra de cantos se enciende.
     */
    get teToca() {
        return ! this.fin && ! this.ocupada && ! this.cierre && this.enJuego && this.vista.acciones.length > 0;
    },

    /**
     * Lo que se ve arriba de tu mano. Casi siempre nada: lo que pasa se ve donde pasa, y el relato entero
     * va solo al lector de pantalla. Queda para lo que no tiene otro lugar.
     */
    get notaALaVista() {
        if (this.nota) {
            return this.nota;
        }

        return this.pedidos.entrePersonas && this.vista.llegando && this.juegaElOtro ? this.indicacion() : '';
    },

    /**
     * Lo que dijo otro, en una fichita sobre sus cartas: la última palabra, como se oye en la mesa.
     * Entra con un golpe chico y reemplaza a la de antes. Lo tuyo no lleva ficha: lo dijiste vos.
     */
    anotarDicho(lugar, texto, tono = '') {
        if (lugar === 'vos') {
            this.callarDicho();

            return;
        }

        this.dicho = { lugar, texto, tono };

        this.$nextTick(() => {
            const ficha = this.$root.querySelector(`[data-dicho="${lugar}"]`);

            ficha?.animate(
                this.reducido
                    ? [{ opacity: 0 }, { opacity: 1 }]
                    : [{ opacity: 0, transform: 'translateY(35%) scale(0.9)' }, { opacity: 1, transform: 'none' }],
                { duration: this.reducido ? 150 : 220, easing: LLEGADA },
            );
        });
    },

    callarDicho(lugar = null) {
        if (lugar === null || this.dicho.lugar === lugar) {
            this.dicho = { lugar: null, texto: '', tono: '' };
        }
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

    get ritmo() {
        return this.agil ? RITMOS.agil : RITMOS.tranquilo;
    },

    /**
     * El botón de la barra: pasa de ritmo tranquilo a ágil y al revés. Vale desde la jugada siguiente, se
     * recuerda en este navegador y la mesa lo dice con palabras, porque el ícono solo no alcanza.
     */
    cambiarRitmo() {
        this.agil = ! this.agil;
        this.aviso = this.agil ? 'Ritmo ágil.' : 'Ritmo tranquilo.';

        try {
            localStorage.setItem(CLAVE_DEL_RITMO, this.agil ? 'agil' : 'tranquilo');
        } catch {
            // Sin almacenamiento el ritmo igual cambia; solo no se recuerda.
        }
    },

    /**
     * El botón de sonido de la barra. Prendido, abre el audio y da una muestra (una carta que se apoya), que
     * además es la respuesta del botón; apagado, cierra todo. Se recuerda en este navegador y la mesa lo dice
     * con palabras.
     */
    cambiarSonido() {
        this.sonido = ! this.sonido;
        this.aviso = this.sonido ? 'Sonido prendido.' : 'Sonido apagado.';
        guardarSonido(this.sonido);

        if (this.sonido) {
            prenderSonido();
            // La muestra: una carta que se apoya y un fósforo que cae. El audio puede tardar un instante en arrancar.
            setTimeout(() => sonar('carta'), 60);
            setTimeout(() => sonar('punto'), 320);
        } else {
            apagarSonido();
        }
    },

    /**
     * Cómo están los avisos, dicho en el renglón de los ajustes.
     */
    get textoDeAvisos() {
        return {
            [PRENDIDOS]: 'Prendidos: te aviso cuando te toca',
            [BLOQUEADOS]: 'Bloqueados en este navegador',
        }[this.avisos] ?? 'Apagados';
    },

    /**
     * La llave de los avisos: los prende (ahí el navegador pide el permiso) o los apaga. La mesa lo dice
     * con palabras, igual que con el sonido y el ritmo.
     */
    async cambiarAvisos() {
        if (this.cambiandoAvisos || ! this.pedidos.avisos) {
            return;
        }

        this.cambiandoAvisos = true;
        this.avisos = this.avisos === PRENDIDOS ? await apagarAvisos(this.pedidos.avisos) : await prenderAvisos(this.pedidos.avisos);
        this.cambiandoAvisos = false;
        this.aviso = { [PRENDIDOS]: 'Avisos prendidos.', [BLOQUEADOS]: 'Los avisos quedaron bloqueados en este navegador.' }[this.avisos] ?? 'Avisos apagados.';
    },

    /**
     * Si hay audio abierto. Con el sonido apagado no hay nada: lo miran las pruebas de navegador.
     */
    get audioAbierto() {
        // Se lee "sonido" para que la pantalla vuelva a preguntar cuando cambia.
        return this.sonido !== null && audioAbierto();
    },

    /**
     * Hace sonar algo de la mesa, si el sonido está prendido. Acompaña a lo que se ve; nunca avisa solo.
     */
    sonar(nombre, enMilisegundos = 0, opciones = {}) {
        if (this.sonido) {
            sonar(nombre, enMilisegundos, opciones);
        }
    },

    /**
     * Los ajustes de la mesa: una ventanita con el modo, el sonido y el ritmo, que se despliega debajo de
     * su botón. No traba nada: la mesa sigue a la vista y se puede seguir jugando. Al abrirla el foco va a
     * su primer renglón; al cerrarla con el botón o con Esc vuelve al botón. Si se cierra por tocar afuera,
     * el foco se queda donde la persona tocó.
     */
    abrirAjustes() {
        this.ajustando = true;
        this.$nextTick(() => this.$refs.ajustes?.querySelector('button')?.focus({ preventScroll: true }));
    },

    cerrarAjustes(devolverElFoco = true) {
        if (! this.ajustando) {
            return;
        }

        this.ajustando = false;

        if (devolverElFoco) {
            this.$nextTick(() => this.$refs.botonDeAjustes?.focus({ preventScroll: true }));
        }
    },

    /**
     * Una pausa para leer lo que acaba de pasar. Su largo depende del ritmo de la mesa; las esperas que
     * acompañan a una animación (el reparto, juntar las cartas) no pasan por acá y duran lo que la animación.
     */
    leer(milisegundos) {
        return this.esperar(milisegundos * this.ritmo.pausas);
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
        if (conPausa && this.esDeOtro(paso.hechos[0]?.asiento)) {
            await this.leer(650);
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

        if (this.hayTantosALaVista()) {
            await this.leer(1300);
            this.callarTantos();
            this.levantarTanto([]);
        }

        this.vista = paso;
    },

    /**
     * Los tantos del envido que están a la vista, y sacarlos. Aparte, porque la mesa de cuatro tiene cuatro.
     */
    hayTantosALaVista() {
        return this.tantos.vos.visible || this.tantos.rival.visible;
    },

    callarTantos() {
        this.tantos.vos.visible = false;
        this.tantos.rival.visible = false;
    },

    async contar(hecho, paso) {
        const asiento = hecho.asiento ?? hecho.equipo ?? hecho.ganador ?? null;
        const quien = asiento === null ? null : this.quien(asiento);
        const rival = this.rivalAlEmpezar;

        switch (hecho.tipo) {
            case 'reparto':
                this.repartir(paso, true);
                await this.esperar(this.reducido ? 200 : 650 + this.demoraDelReparto);
                break;

            case 'carta':
                this.aviso = quien === 'vos' ? `Jugaste el ${nombreDe(hecho.carta)}.` : `${rival} jugó el ${nombreDe(hecho.carta)}.`;
                this.callarDicho(quien);

                if (quien === 'vos' && this.adelantada === hecho.carta) {
                    // Esta carta ya se movió al tocarla: no se vuelve a animar.
                    this.adelantada = null;
                } else {
                    this.jugarCarta(quien, hecho.carta, this.bazaDe(paso, hecho));
                    await this.leer(520);
                }
                break;

            case 'baza':
                this.resolverBaza(hecho, paso);
                await this.leer(700);
                break;

            case 'canto':
                this.cantar(CANTOS[hecho.canto], TRUCOS.includes(hecho.canto) ? 'copa' : 'oro', quien);
                this.aviso = `${quien === 'vos' ? 'Cantaste' : `${rival} cantó`} ${CANTOS[hecho.canto].toLowerCase()}.`;
                this.anotarDicho(quien, CANTOS[hecho.canto], TRUCOS.includes(hecho.canto) ? 'copa' : '');
                await this.leer(1250);
                break;

            case 'respuesta':
                this.cantar(hecho.quiere ? 'Quiero' : 'No quiero', hecho.quiere ? 'basto' : 'copa', quien);
                this.aviso = `${quien === 'vos' ? (hecho.quiere ? 'Quisiste' : 'No quisiste') : `${rival} ${hecho.quiere ? 'quiso' : 'no quiso'}`}.`;
                this.anotarDicho(quien, hecho.quiere ? 'Quiero' : 'No quiero', hecho.quiere ? 'basto' : 'copa');
                await this.leer(1100);
                break;

            case 'tantos':
                await this.cantarTantos(hecho);
                break;

            case 'puntos':
                this.aviso = `${quien === 'vos' ? 'Sumás' : `${rival} suma`} ${hecho.puntos}: ${this.conceptoDe(hecho.concepto, paso).toLowerCase()}.`;
                await this.sumar(quien, hecho.tanteo[hecho.equipo]);
                await this.leer(350);
                break;

            case 'mazo':
                this.aviso = this.comoSeFue(asiento, paso);
                await this.leer(400);
                break;

            case 'mano_cerrada':
                this.vista = paso;
                this.cerrarMano(paso);
                await this.leer(300);
                break;

            case 'partida_terminada':
                // En un desafío, llegar a los puntos no es el final de nada: lo que cuenta es el objetivo, y ya lo dijo el cierre.
                if (this.esManoConObjetivo) {
                    break;
                }

                this.fin = quien;
                this.experiencia = paso.experiencia ?? null;
                this.sonar('final');
                this.contarElFinal();
                this.atenderLaRevancha();
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
        llegarDelMazo(elemento, this.mazo, orden, { reducido: this.reducido, demora: this.demoraDelReparto });
        // Cada carta suena cuando sale, con los mismos 70 ms entre una y otra que tiene el reparto.
        this.sonar('reparto', orden * 70);
    },

    /**
     * El camino inverso del reparto: la carta vuelve al mazo. Es una salida, así
     * que dura menos que la llegada. Devuelve la animación para saber cuándo terminó.
     */
    guardar(elemento, orden) {
        const origen = this.mazo.getBoundingClientRect();
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
     * Mueve una carta a su lugar en la baza y la anima desde donde estaba, para que se vea de dónde salió.
     * Cae con un resorte de poco rebote y queda con su giro de pocos grados: ninguna baza queda con regla.
     */
    apoyar(carta, destino, desde) {
        destino.replaceChildren(asentarEnLaBaza(carta));

        const giro = carta.style.transform;
        // Se mide sin el giro, para que el vuelo salga justo de donde estaba la carta.
        carta.style.transform = '';
        const hasta = carta.getBoundingClientRect();
        carta.style.transform = giro;
        // Desde el centro de la carta, como su giro: así el vuelo termina justo donde queda apoyada.
        const escala = desde.width / hasta.width;
        const x = desde.left - hasta.left - ((1 - escala) * hasta.width) / 2;
        const y = desde.top - hasta.top - ((1 - escala) * hasta.height) / 2;
        const cuadros = this.reducido
            ? [{ opacity: 0 }, { opacity: 1 }]
            : [{ transform: `translate(${x}px, ${y}px) scale(${escala})` }, { transform: giro }];

        carta.animate(cuadros, this.reducido ? { duration: 150, easing: LLEGADA } : CAE);
        // Suena cuando la carta está llegando al paño, no cuando sale de la mano.
        this.sonar('carta', this.reducido ? 0 : 150);
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
        // Cada canto tiene su golpe: el truco pega dos veces, "quiero" suena más claro y "no quiero" más apagado.
        this.sonar(texto === 'Quiero' ? 'quiero' : texto === 'No quiero' ? 'noQuiero' : tono === 'copa' ? 'truco' : 'canto');
        this.voz = { texto: this.enRenglones(texto), tono, quien, visible: true };
        this.relojDeVoz = setTimeout(() => (this.voz.visible = false), 1250 * this.ritmo.pausas);
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

        await this.leer(160);

        for (const [orden, dicho] of hecho.tantos.entries()) {
            const quien = this.quien(dicho.asiento);

            if (orden > 0) {
                await this.leer(800);
            }

            this.sonar('canto');

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

        await this.leer(650);
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
        // Un punto o dos caen con su tiempo; una suma grande (un falta envido) se aprieta para entrar en un segundo.
        const entreFosforos = Math.min(ENTRE_FOSFOROS, SUMA_MAS_LARGA / Math.max(hasta - this.puntos[quien], 1));

        // Cuántos fósforos cayeron en esta suma: cada uno suena un semitono más arriba que el anterior.
        let caidos = 0;

        return new Promise((listo) => {
            const caer = () => {
                if (this.puntos[quien] >= hasta) {
                    listo();

                    return;
                }

                this.puntos[quien]++;
                this.sonar('punto', 0, { tono: 2 ** (Math.min(caidos++, 12) / 12) });

                // El que cruza y completa un grupo de cinco lleva un golpe aparte.
                if (this.puntos[quien] % 5 === 0) {
                    this.sonar('grupo');
                }
                setTimeout(caer, this.prisa ? Math.min(entreFosforos, 40) : entreFosforos);
            };

            caer();
        });
    },

    /**
     * El final de la partida es el gesto fuerte de la mesa, y lo hace el tanteador: en el cartel, los dos se
     * cuentan desde cero, de corrido y al mismo paso. El que perdió se queda en su tanteo y el que ganó sigue
     * hasta completar el último grupo. Corre una sola vez y no traba nada: los botones se usan desde que
     * aparecen. Con movimiento reducido los dos tanteos están completos de entrada.
     */
    contarElFinal() {
        const hasta = { ...this.puntos };

        if (this.reducido) {
            this.cuenta = hasta;
            this.contarLaTira();

            return;
        }

        this.cuenta = { vos: 0, rival: 0 };

        const caer = () => {
            const faltan = LADOS.filter((lado) => this.cuenta[lado] < hasta[lado]);

            faltan.forEach((lado) => this.cuenta[lado]++);

            if (faltan.length) {
                setTimeout(caer, ENTRE_FOSFOROS_DEL_FINAL);
            } else {
                this.contarLaTira();
            }
        };

        setTimeout(caer, ANTES_DE_CONTAR_EL_FINAL);
    },

    /**
     * Después de los tanteadores, los fósforos que la partida le anotó al nivel caen de a uno en la tira.
     * Si la tira se llena, hay un respiro, el nivel sube y la tira arranca de nuevo. Al final se dice lo
     * que destrabó. Con movimiento reducido, la tira aparece ya contada.
     */
    contarLaTira() {
        const experiencia = this.experiencia;

        if (! experiencia) {
            return;
        }

        const enLaTira = (fosforos, nivel) => (nivel === ULTIMO_NIVEL ? POR_NIVEL : fosforos % POR_NIVEL);
        const subio = experiencia.nivelAhora > experiencia.nivelAntes;

        if (this.reducido || experiencia.ganados === 0) {
            this.tira = { nivel: experiencia.nivelAhora, lleva: enLaTira(experiencia.ahora, experiencia.nivelAhora), subio, contada: true };
            this.sellarLaLibreta();

            return;
        }

        this.tira = { nivel: experiencia.nivelAntes, lleva: enLaTira(experiencia.antes, experiencia.nivelAntes), subio: false, contada: false };

        let anotados = 0;

        const caer = () => {
            // La tira llena: el nivel sube y se empieza otra. Va antes de terminar: el último fósforo puede ser el que la llena.
            if (this.tira.lleva === POR_NIVEL && this.tira.nivel < experiencia.nivelAhora) {
                this.tira.nivel++;
                this.tira.lleva = 0;
                setTimeout(caer, AL_LLENAR_LA_TIRA);

                return;
            }

            if (anotados === experiencia.ganados) {
                this.tira.subio = subio;
                this.tira.contada = true;
                this.sellarLaLibreta();

                return;
            }

            anotados++;
            this.tira.lleva = Math.min(POR_NIVEL, this.tira.lleva + 1);
            this.sonar('punto', 0, { tono: this.tira.lleva });
            setTimeout(caer, ENTRE_FOSFOROS_DEL_NIVEL);
        };

        setTimeout(caer, ENTRE_FOSFOROS_DEL_NIVEL);
    },

    /**
     * Después de la tira, los sellos de la libreta que dio la partida golpean el papel de a uno, como un sello de
     * goma: entran grandes y levantados, golpean con un rebote chico y quedan en su lugar, cada uno con su golpe
     * seco. No traban nada: los botones del final siguen andando. Con movimiento reducido aparecen con un fundido.
     */
    sellarLaLibreta() {
        const sellos = this.experiencia?.sellos?.length ?? 0;

        // Lo que dio la partida llega cuando el último sello ya golpeó (se ven hasta tres).
        setTimeout(() => this.mostrarLosPremios(), sellos === 0 || this.reducido ? 0 : (Math.min(sellos, 3) - 1) * ENTRE_SELLOS + SELLA.duration);

        if (sellos === 0) {
            return;
        }

        this.sellando = true;

        this.$nextTick(() => {
            this.$root.querySelectorAll('[data-sello-nuevo]').forEach((sello, numero) => {
                const demora = numero * ENTRE_SELLOS;

                if (this.reducido) {
                    sello.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 200, delay: demora, easing: 'ease', fill: 'backwards' });

                    return;
                }

                sello.querySelector('.final-sello').animate([
                    { opacity: 0, transform: 'translateY(-14%) scale(1.5) rotate(-7deg)' },
                    { opacity: 1, offset: 0.3 },
                    { opacity: 1, transform: 'none' },
                ], { ...SELLA, delay: demora, fill: 'backwards' });
                // El nombre se lee después del golpe.
                sello.lastElementChild.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 200, delay: demora + 140, easing: 'ease', fill: 'backwards' });
                this.sonar('sello', demora + 110);
            });
        });
    },

    /**
     * Después de los sellos, lo que dio la partida: el nivel nuevo y cada pieza ganada como una carta chica que llega boca
     * abajo con tu dorso y se da vuelta. Las cartas se piden aparte (traen su propio sprite); si no llegan, se dice
     * con palabras. No traban nada: los botones del final siguen andando.
     */
    async mostrarLosPremios() {
        const experiencia = this.experiencia;
        const lugar = this.$refs.premios;

        if (! experiencia || ! lugar || (! experiencia.premiosEn && experiencia.nivelAhora === experiencia.nivelAntes)) {
            return;
        }

        if (experiencia.premiosEn) {
            try {
                const respuesta = await fetch(experiencia.premiosEn, { headers: { Accept: 'text/html' } });

                if (! respuesta.ok) {
                    throw new Error(`Las cartas de los premios no llegaron (${respuesta.status}).`);
                }

                lugar.innerHTML = await respuesta.text();
            } catch (falla) {
                console.error(falla);
                this.premios.enPalabras = true;
            }
        }

        const cartas = [...lugar.querySelectorAll('[data-premio]')];
        this.premios.enlace = cartas[0]?.getAttribute('href') ?? null;
        this.premios.aLaVista = true;
        this.$nextTick(() => this.darVueltaLosPremios(cartas));
    },

    /**
     * Cada carta entra un poco más chica, con un fundido, y se da vuelta al asentarse; la siguiente, cuando la anterior
     * ya está. Con movimiento reducido aparecen con un fundido y ya dadas vuelta (.giro no gira).
     */
    darVueltaLosPremios(cartas) {
        cartas.forEach((carta, numero) => {
            const demora = numero * (ENTRA_EL_PREMIO + ENTRE_PREMIOS);
            const giro = carta.querySelector('.giro');

            premiosEnCurso.animaciones.push(carta.animate(
                this.reducido ? [{ opacity: 0 }, { opacity: 1 }] : [{ opacity: 0, transform: 'translateY(0.4rem) scale(0.95)' }, { opacity: 1, transform: 'none' }],
                { duration: this.reducido ? 200 : ENTRA_EL_PREMIO, delay: demora, easing: this.reducido ? 'ease' : LLEGADA, fill: 'backwards' },
            ));
            // La vuelta y su sonido, en el mismo reloj: apurar los corta juntos.
            premiosEnCurso.relojes.push(setTimeout(() => {
                giro.dataset.vuelta = 'true';
                this.sonar('carta');
            }, this.reducido ? demora : demora + ENTRA_EL_PREMIO - 40));
        });
    },

    /**
     * Tocar los premios mientras llegan: quedan todos a la vista y dados vuelta, ya.
     */
    apurarLosPremios() {
        premiosEnCurso.relojes.forEach(clearTimeout);
        premiosEnCurso.animaciones.forEach((animacion) => animacion.finish());
        premiosEnCurso.relojes = [];
        premiosEnCurso.animaciones = [];
        this.$refs.premios?.querySelectorAll('.giro').forEach((giro) => {
            giro.dataset.vuelta = 'true';
        });
    },

    /**
     * El link debajo de los premios: lleva a la Colección, en el primero (o a crear la cuenta). Si no entraron todos,
     * dice cuántos son.
     */
    get textoDeLaColeccion() {
        const cuantos = this.experiencia?.premios?.length ?? 0;

        // Sin cuenta no hay Colección: el link lleva a crearla, que se queda con lo ganado.
        if (this.experiencia?.sinCuenta) {
            return cuantos === 1 ? 'Creá tu cuenta para usarlo' : 'Creá tu cuenta para usarlos';
        }

        if (cuantos > 3) {
            return `Ver los ${cuantos} en tu colección`;
        }

        return cuantos === 1 ? 'Probalo en tu colección' : 'Probalos en tu colección';
    },

    /**
     * Cuántos fósforos anotó la partida, dicho al lado del nivel.
     */
    get fraseDeFosforos() {
        const ganados = this.experiencia?.ganados ?? 0;

        return ganados === 0 ? 'Esta partida no anota' : `+${ganados} ${ganados === 1 ? 'fósforo' : 'fósforos'}`;
    },

    /**
     * Lo mismo para el lector de pantalla, en una frase.
     */
    get fraseDelNivel() {
        const experiencia = this.experiencia;

        if (! experiencia) {
            return '';
        }

        const anoto = experiencia.ganados === 0 ? 'Esta partida no anotó fósforos.' : `Anotaste ${experiencia.ganados} ${experiencia.ganados === 1 ? 'fósforo' : 'fósforos'}.`;

        const nivel = experiencia.nivelAhora > experiencia.nivelAntes ? `Subiste al nivel ${experiencia.nivelAhora}.` : `Nivel ${experiencia.nivelAhora}.`;
        const premios = (experiencia.premios ?? []).map((premio) => `${TIPO_DE_PREMIO[premio.tipo].toLowerCase()} ${premio.nombre}`);

        return `${anoto} ${nivel}${premios.length ? ` Ganaste: ${premios.join(', ')}.` : ''}`;
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

                hueco.replaceChildren(...(jugada ? [asentarEnLaBaza(plantilla(jugada[1]))] : []));
                delete hueco.dataset.gana;
                delete hueco.dataset.pierde;

                if (baza?.cerrada && baza.ganador !== null) {
                    hueco.dataset[baza.ganador === asiento ? 'gana' : 'pierde'] = '';
                }
            });
        });

        this.$refs.rival.replaceChildren();
        this.$refs.mano.removeAttribute('data-tanto');
        [...this.$refs.mano.children].forEach((lugar) => {
            lugar.replaceChildren();
            lugar.hidden = false;
        });

        // Se reparte de a una para cada uno, y la primera es para el mano, como en la mesa de verdad.
        const soyMano = vista.mano === this.yo;
        const pasar = this.pasarElMazo(soyMano ? 'rival' : 'vos', animar);

        vista.misCartas.forEach((carta, i) => {
            const boton = document.createElement('button');

            boton.type = 'button';
            boton.className = 'naipe-jugable';
            boton.dataset.carta = carta;
            boton.setAttribute('aria-label', `${nombreDe(carta)}, jugar esta carta`);
            boton.append(plantilla(carta));
            boton.addEventListener('click', (evento) => {
                // El toque no sube hasta la mesa: si jugó la carta, no tiene que apurar además lo que conteste el rival.
                evento.stopPropagation();
                this.jugar(carta, boton);
            });
            this.arrastrable(boton, carta);
            this.$refs.mano.children[i].replaceChildren(boton);

            if (animar) {
                this.llegar(boton, i * 2 + (soyMano ? 0 : 1));
            }
        });

        for (let i = 0; i < vista.cartasEnMano[this.ellos]; i++) {
            const dorso = document.createElement('div');

            dorso.append(plantilla('dorso'));
            this.$refs.rival.append(dorso);

            if (animar) {
                this.llegar(dorso, i * 2 + (soyMano ? 1 : 0));
            }
        }

        // Recién ahora: cada carta ya tomó como punto de partida el lugar nuevo del mazo, y sale cuando llega.
        pasar();

        this.aviso = animar ? 'Repartiendo.' : this.aviso;
    },

    /**
     * En la mesa ancha el mazo está delante de quien reparte, y reparte el que no es mano: en cada mano
     * nueva se pasa al otro lado. Lo deja en su lugar nuevo y devuelve el pase, que lo trae desde donde
     * estaba (se llama después de que las cartas midieron de dónde salen). Mientras dura el pase, las
     * cartas esperan (demoraDelReparto). Sin mesa ancha, o sin cambio de lado, no hace nada.
     */
    pasarElMazo(reparte, animar) {
        const costado = this.$refs.costadoDelMazo;
        const pila = this.$refs.pilaAlCostado;

        this.demoraDelReparto = 0;

        if (! costado || costado.dataset.reparte === reparte) {
            return () => {};
        }

        const antes = pila.getBoundingClientRect();

        costado.dataset.reparte = reparte;

        if (! animar || this.reducido || costado.offsetParent === null) {
            return () => {};
        }

        const despues = pila.getBoundingClientRect();
        this.demoraDelReparto = PASE_DEL_MAZO;

        return () => pila.animate(
            [{ transform: `translateY(${antes.top - despues.top}px)` }, { transform: 'none' }],
            { duration: PASE_DEL_MAZO, easing: 'cubic-bezier(0.77, 0, 0.175, 1)' },
        );
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
        this.fin = vista.ganador === null || this.esManoConObjetivo ? null : this.quien(vista.ganador);
        this.cuenta = { ...this.puntos };
        this.atenderLaRevancha();
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
    /**
     * La carta también se juega arrastrándola a la mesa (arrastre.js). Está sobre la mesa cuando el dedo
     * pasó el borde de arriba de tu mano; mientras tanto se marca el lugar de la baza en juego.
     */
    arrastrable(boton, carta) {
        hacerArrastrable(boton, {
            sobreLaMesa: (y) => y < this.$refs.mano.getBoundingClientRect().top,
            alSoltar: () => {
                const vale = ! this.ocupada && this.vista.acciones.some((accion) => accion.tipo === 'jugar' && accion.carta === carta);

                if (vale) {
                    this.jugar(carta, boton);
                }

                return vale;
            },
            // En la mesa de a dos se marca la baza en juego; en la de a cuatro, la cruz del centro.
            alApuntar: (apunta) => (this.$refs.bazas ?? this.$refs.cruz)?.toggleAttribute('data-apuntada', apunta),
            reducido: () => this.reducido,
        });
    },

    jugar(carta, boton) {
        // Mientras la mesa cuenta lo que pasó, tocar una carta la apura, igual que tocar el paño. No la juega:
        // quien la tocó todavía no terminó de ver lo que hizo el rival.
        if (this.ocupada) {
            this.apurar();

            return;
        }

        if (! this.vista.acciones.some((accion) => accion.tipo === 'jugar' && accion.carta === carta)) {
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
        this.reacomodarMano();
    },

    /**
     * Cuando sale una carta de la mano, las que quedan se juntan con un resorte, como cuando se acomodan
     * las cartas en la mano de verdad. El lugar vacío se esconde mientras quede alguna carta; sin cartas,
     * vuelven los tres lugares (ahí va el cierre de la mano) y la fila mide lo mismo.
     */
    reacomodarMano() {
        const lugares = [...this.$refs.mano.children];
        const conCarta = lugares.filter((lugar) => lugar.firstElementChild);

        if (conCarta.length === 0) {
            lugares.forEach((lugar) => (lugar.hidden = false));

            return;
        }

        const antes = new Map(conCarta.map((lugar) => [lugar, lugar.getBoundingClientRect().left]));

        lugares.forEach((lugar) => (lugar.hidden = ! lugar.firstElementChild));

        if (this.reducido) {
            return;
        }

        conCarta.forEach((lugar) => {
            const corrida = antes.get(lugar) - lugar.getBoundingClientRect().left;

            if (Math.abs(corrida) > 0.5) {
                lugar.firstElementChild.animate([{ transform: `translateX(${corrida}px)` }, { transform: 'none' }], SE_JUNTA);
            }
        });
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
        // En un desafío el título no es de quién fue la mano sino si se cumplió el objetivo: se puede cumplir perdiéndola.
        this.superado = this.esManoConObjetivo ? Boolean(vista.superado) : null;

        const deLaMano = motivo === 'partida'
            ? 'Se terminó la partida'
            // Con otra persona el título no lleva su apodo: uno largo lo partiría en tres renglones.
            : (ganador === this.yo ? 'Ganaste la mano' : (entrePersonas ? 'Perdiste la mano' : 'La mano es de la máquina'));
        const titulo = this.superado === null ? deLaMano : (this.superado ? 'Desafío superado' : 'No salió esta vez');
        const razon = this.razonDelCierre(vista);
        const lineas = anotado.map((linea) => ({
            puntos: linea.puntos,
            texto: `${this.conceptoDe(linea.concepto, vista)}, para ${linea.equipo === this.yo ? 'vos' : this.rival}`,
        }));
        const delBot = (mostradas[this.ellos] ?? []).filter((carta) => ! vista.bazas.some((baza) => baza.jugadas.some(([, otra]) => otra === carta)));
        const dorsos = [...this.$refs.rival.children];

        this.callarTantos();
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
            this.superado !== null || vista.fase !== 'por_repartir' ? '' : (entrePersonas ? 'Se reparte solo en unos segundos. Repartir lo apura.' : 'Apretá Repartir para seguir.'),
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
        this.callarDicho();
        this.aviso = 'Repartiendo.';
        cartas.forEach((carta, i) => this.guardar(carta, i));

        return this.esperar(this.reducido ? 130 : 210 + cartas.length * 30);
    },
});
