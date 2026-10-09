/*
 | El sonido de la mesa: cartas, fósforos y cantos.
 |
 | No hay ningún archivo de audio. Cada sonido lo fabrica el navegador en el momento (Web Audio): un
 | soplo de ruido filtrado para lo que roza o golpea, y un tono corto que baja para lo que tiene cuerpo.
 | Así no hay nada que descargar, ni licencias que anotar, ni peso que sumarle al sitio.
 |
 | Para que no suenen a máquina se armaron con lo que hace agradable a un sonido corto:
 |
 | - capas: un chasquido muy breve al principio, un cuerpo debajo y, si algo golpea, un rebote;
 | - nunca dos veces igual: cada vez que suena cambia apenas de tono y de volumen, y el ruido arranca
 |   en otro punto. Una carta se juega tres veces por mano: idéntica, cansa;
 | - los fósforos suben: en una suma, cada fósforo suena un poco más agudo que el anterior, y el que
 |   completa un grupo de cinco lleva un golpe aparte. Se oye que el tanteo sube;
 | - cada canto tiene su golpe: el truco pega dos veces, el "quiero" suena más claro y el "no quiero" más apagado.
 |
 | Arranca apagado. Mientras está apagado no se abre nada: ni un contexto de audio. Lo prende quien
 | juega desde los ajustes de la mesa, y la elección queda en su navegador.
 |
 | El sonido acompaña, nunca avisa solo: todo lo que suena también se ve y se lee en la mesa.
 */

const CLAVE = 'vale-cuatro:sonido';

// El volumen general. Los sonidos son cortos y secos: tienen que acompañar, no sobresaltar.
const VOLUMEN = 0.7;

// Cuánto cambia cada sonido de una vez a la otra: hasta un 5 % de tono y un 12 % de volumen, para arriba o para abajo.
const VARIACION_DE_TONO = 0.05;
const VARIACION_DE_VOLUMEN = 0.12;

// Un golpe de nudillos sobre la mesa, hecho de cuatro capas: el contacto, la madera, su brillo y el aire.
// "grave" corre todo el golpe de tono (más de 1 es más agudo) y "en" lo atrasa.
const golpe = ({ grave = 1, brillo = 1, volumen = 1, en = 0 } = {}) => [
    { ruido: 'highpass', frecuencia: 3000, q: 0.7, dura: 0.008, volumen: 0.2 * volumen, en },
    { tono: 'sine', desde: 205 * grave, hasta: 105 * grave, dura: 0.15, volumen: 0.42 * volumen, en },
    { tono: 'sine', desde: 470 * grave, hasta: 390 * grave, dura: 0.05, volumen: 0.12 * volumen * brillo, en },
    { ruido: 'lowpass', frecuencia: 800, q: 0.6, dura: 0.05, volumen: 0.16 * volumen, en },
];

/*
 | Los sonidos. Cada uno es una lista de capas que suenan juntas o una atrás de otra:
 |
 | - "ruido": un soplo de ruido blanco pasado por un filtro ("highpass" deja lo agudo, "bandpass" una franja,
 |   "lowpass" lo grave) a esa frecuencia; con "hasta", el filtro se va corriendo mientras suena (un roce);
 | - "tono": una nota que va de una frecuencia a otra;
 | - "dura" y "en" van en segundos: cuánto dura la capa y cuándo empieza. Ningún sonido entero llega al
 |   segundo y medio (hay un test que lo mira).
 */
export const SONIDOS = {
    // Una carta que sale del mazo: un roce que baja de agudo a grave, con el borde raspando al arrancar.
    // En un reparto suena seis veces seguidas.
    reparto: [
        { ruido: 'bandpass', frecuencia: 3400, hasta: 1500, q: 0.9, dura: 0.11, volumen: 0.11 },
        { ruido: 'highpass', frecuencia: 5200, q: 0.7, dura: 0.02, volumen: 0.06 },
    ],
    // Una carta que se apoya en el paño: el chasquido, el cartón, el golpe sordo debajo y un rebote chico.
    carta: [
        { ruido: 'highpass', frecuencia: 4500, q: 0.7, dura: 0.012, volumen: 0.28 },
        { ruido: 'bandpass', frecuencia: 1500, q: 0.9, dura: 0.085, volumen: 0.3 },
        { tono: 'sine', desde: 165, hasta: 72, dura: 0.09, volumen: 0.26 },
        { ruido: 'bandpass', frecuencia: 1100, q: 1.2, dura: 0.05, volumen: 0.08, en: 0.022 },
    ],
    // Un fósforo que cae en el tanteador: madera chica contra madera, un tic con nota.
    punto: [
        { tono: 'triangle', desde: 1750, hasta: 1500, dura: 0.045, volumen: 0.12 },
        { tono: 'sine', desde: 3400, hasta: 2900, dura: 0.02, volumen: 0.05 },
        { ruido: 'bandpass', frecuencia: 5200, q: 2.5, dura: 0.012, volumen: 0.12 },
    ],
    // El fósforo que cruza y completa un grupo de cinco: un golpe más lleno, encima de su tic.
    grupo: [
        { tono: 'sine', desde: 620, hasta: 480, dura: 0.09, volumen: 0.16 },
        { ruido: 'bandpass', frecuencia: 2400, q: 1.5, dura: 0.02, volumen: 0.08 },
    ],
    // Los cantos: golpes de nudillos sobre la mesa. No llevan voz: la palabra está en la pantalla.
    canto: golpe(),
    // El truco y sus subidas pegan dos veces.
    truco: [...golpe(), ...golpe({ volumen: 0.85, en: 0.13 })],
    // "Quiero" suena más claro; "no quiero", más grave y apagado.
    quiero: golpe({ grave: 1.28, brillo: 1.5 }),
    noQuiero: golpe({ grave: 0.74, brillo: 0.3 }),
    // El final de la partida: tres golpes, el último más grave y más largo. Sobrio, como el cierre.
    final: [
        ...golpe({ volumen: 0.8 }),
        ...golpe({ volumen: 0.8, en: 0.16 }),
        { tono: 'sine', desde: 150, hasta: 55, dura: 0.34, volumen: 0.42, en: 0.42 },
        { tono: 'sine', desde: 300, hasta: 220, dura: 0.09, volumen: 0.1, en: 0.42 },
        { ruido: 'lowpass', frecuencia: 700, q: 0.5, dura: 0.08, volumen: 0.18, en: 0.42 },
    ],
};

// Lo que existe solo con el sonido prendido.
let contexto = null;
let salida = null;
let ruido = null;

/**
 * Si quien juega dejó el sonido prendido la última vez. Sin nada guardado, apagado.
 */
export function sonidoGuardado() {
    try {
        return localStorage.getItem(CLAVE) === 'si';
    } catch {
        return false;
    }
}

export function guardarSonido(prendido) {
    try {
        localStorage.setItem(CLAVE, prendido ? 'si' : 'no');
    } catch {
        // Sin almacenamiento el sonido igual cambia; solo no se recuerda.
    }
}

/**
 * Si hay un contexto de audio abierto. Con el sonido apagado nunca lo hay.
 */
export function audioAbierto() {
    return contexto !== null;
}

/**
 * Abre el audio. Los navegadores no dejan sonar nada hasta que la persona toca algo: si esto se llama
 * al cargar la página (venía prendido de antes), el contexto queda en espera y arranca con el primer
 * toque o la primera tecla.
 */
export function prenderSonido() {
    if (contexto !== null) {
        return;
    }

    const Contexto = window.AudioContext ?? window.webkitAudioContext;

    if (! Contexto) {
        return;
    }

    contexto = new Contexto();

    // Todo pasa por un compresor suave antes de salir: cuando caen muchos fósforos juntos o se pisan dos
    // sonidos, empareja el volumen en vez de saturar.
    const compresor = contexto.createDynamicsCompressor();

    salida = contexto.createGain();
    salida.gain.value = VOLUMEN;
    salida.connect(compresor).connect(contexto.destination);

    // Un tercio de segundo de ruido blanco, que usan todas las capas de ruido.
    ruido = contexto.createBuffer(1, Math.floor(contexto.sampleRate * 0.35), contexto.sampleRate);

    const muestras = ruido.getChannelData(0);

    for (let i = 0; i < muestras.length; i++) {
        muestras[i] = Math.random() * 2 - 1;
    }

    if (contexto.state === 'suspended') {
        const despertar = () => contexto?.resume().catch(() => {});

        window.addEventListener('pointerdown', despertar, { once: true });
        window.addEventListener('keydown', despertar, { once: true });
    }
}

/**
 * Cierra el audio y suelta todo: apagado, el sitio no tiene nada de sonido abierto.
 */
export function apagarSonido() {
    contexto?.close().catch(() => {});
    contexto = null;
    salida = null;
    ruido = null;
}

/**
 * Un número cerca de 1: lo que cambia un sonido de una vez a la otra.
 */
function variar(cuanto) {
    return 1 + (Math.random() * 2 - 1) * cuanto;
}

/**
 * Hace sonar uno de los sonidos, ahora o dentro de unos milisegundos (para caer junto con la animación
 * que acompaña). "tono" lo corre entero de altura (más de 1 es más agudo): así suben los fósforos de una
 * suma. No suena si el audio no está andando o si la pestaña no está a la vista: una partida que sigue
 * en otra pestaña no tiene por qué hacer ruido.
 */
export function sonar(nombre, enMilisegundos = 0, { tono = 1 } = {}) {
    if (contexto === null || contexto.state !== 'running' || document.hidden) {
        return;
    }

    const ahora = contexto.currentTime + enMilisegundos / 1000;
    // La variación es una sola para todas las capas: el sonido entero sale un poco distinto, no desarmado.
    // Si el tono viene pedido (los fósforos de una suma, que suben de a un semitono), casi no se lo mueve:
    // una variación grande podría dar vuelta la subida.
    const altura = tono * variar(tono === 1 ? VARIACION_DE_TONO : 0.01);
    const fuerza = variar(VARIACION_DE_VOLUMEN);

    for (const capa of SONIDOS[nombre] ?? []) {
        const cuando = ahora + (capa.en ?? 0);

        // El volumen sube en un instante y cae hasta casi nada: sin eso, cada sonido empezaría y terminaría con un clic.
        const nivel = contexto.createGain();

        nivel.gain.setValueAtTime(0.0001, cuando);
        nivel.gain.exponentialRampToValueAtTime(capa.volumen * fuerza, cuando + 0.003);
        nivel.gain.exponentialRampToValueAtTime(0.0001, cuando + capa.dura);
        nivel.connect(salida);

        if (capa.ruido) {
            const fuente = contexto.createBufferSource();
            const filtro = contexto.createBiquadFilter();

            fuente.buffer = ruido;
            filtro.type = capa.ruido;
            filtro.Q.value = capa.q;
            filtro.frequency.setValueAtTime(capa.frecuencia * altura, cuando);

            if (capa.hasta) {
                filtro.frequency.exponentialRampToValueAtTime(capa.hasta * altura, cuando + capa.dura);
            }

            fuente.connect(filtro).connect(nivel);
            // Cada vez arranca en otro punto del ruido, para que dos golpes seguidos no suenen calcados.
            fuente.start(cuando, Math.random() * 0.2);
            fuente.stop(cuando + capa.dura + 0.02);
        } else {
            const nota = contexto.createOscillator();

            nota.type = capa.tono;
            nota.frequency.setValueAtTime(capa.desde * altura, cuando);
            nota.frequency.exponentialRampToValueAtTime(capa.hasta * altura, cuando + capa.dura);
            nota.connect(nivel);
            nota.start(cuando);
            nota.stop(cuando + capa.dura + 0.02);
        }
    }
}
