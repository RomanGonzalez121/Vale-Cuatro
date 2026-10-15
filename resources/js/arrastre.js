/*
 | Arrastrar una carta de la mano a la mesa para jugarla, y cómo responde la carta bajo el cursor.
 |
 | Tocar la carta (o elegirla con el teclado) la sigue jugando igual: arrastrar es una forma más, para
 | quien prefiere tirarla con el dedo. Mientras se arrastra, la carta sigue al dedo, se levanta apenas y
 | se inclina según cómo se la lleva: rápido hacia un costado se tumba hacia ese lado, y cuando el dedo
 | se frena vuelve a enderezarse (como en Balatro; lo eligió Román el 15 de octubre de 2026). Al soltarla
 | juega si quedó sobre la mesa o si se la tiró con un tirón rápido hacia arriba, aunque no haya llegado
 | lejos. Si no, vuelve a su lugar con un resorte.
 |
 | En el truco no hay deshacer: un movimiento chico no juega nada. Hasta pasar el umbral es un toque
 | común, y hacia abajo la carta se resiste.
 |
 | Solo se mueve con transform. Se ignora un segundo dedo, y el puntero queda capturado por la carta
 | mientras dura el arrastre, así no se pierde si el dedo sale de ella. La inclinación se calcula cuadro a
 | cuadro solo mientras dura el arrastre: la carta nunca se mueve sola.
 */
import { resorte } from './cartas';

/** Cuántos píxeles tiene que moverse el dedo para que el toque pase a ser arrastre. */
const UMBRAL = 10;

/** Un tirón hacia arriba más rápido que esto (píxeles por milisegundo) juega aunque no llegue a la mesa. */
const TIRON = 0.6;

/** Y tiene que haber subido por lo menos esto: un temblor rápido no es un tirón. */
const TIRON_MINIMO = 30;

/** Cuánto se inclina por cada píxel por milisegundo de costado, hasta el tope. */
const INCLINACION = 16;
const TOPE = 14;

/** Cuánto se acerca la inclinación a la que corresponde en cada cuadro: suave, sin temblar. */
const SUAVIDAD = 0.22;

/** La vuelta a la mano: un resorte con poco rebote. */
const VUELTA = resorte(0.42, 0.18);

/** La inclinación hacia el cursor: poca, y solo con un mouse (en el celular no hay cursor). */
const HACIA_EL_CURSOR = 7;
const conCursor = window.matchMedia('(hover: hover) and (pointer: fine)');

/**
 * @param {HTMLElement} carta El botón de la carta, en la mano.
 * @param {object} opciones
 * @param {(y: number) => boolean} opciones.sobreLaMesa Si a esa altura ya está sobre la mesa.
 * @param {() => boolean} opciones.alSoltar Juega la carta si se puede; devuelve si la jugó.
 * @param {(apunta: boolean) => void} opciones.alApuntar Para marcar el lugar de la baza mientras está sobre la mesa.
 * @param {() => boolean} opciones.reducido Si hay que moverse menos.
 */
export function hacerArrastrable(carta, { sobreLaMesa, alSoltar, alApuntar, reducido }) {
    let inicio = null;
    let arrastrando = false;
    let muestras = [];
    let tragarElToque = false;
    let donde = { dx: 0, dy: 0 };
    let giro = 0;
    let cuadro = null;

    /** La velocidad de costado de los últimos instantes: si el dedo se frenó, es cero. */
    const velocidadDeCostado = (ahora) => {
        const recientes = muestras.filter((muestra) => ahora - muestra.t < 90);

        if (recientes.length < 2) {
            return 0;
        }

        const [primera, ultima] = [recientes[0], recientes[recientes.length - 1]];

        return (ultima.x - primera.x) / Math.max(1, ultima.t - primera.t);
    };

    /** Un cuadro del arrastre: la carta sigue al dedo y su inclinación se acerca a la que le corresponde. */
    const dibujar = (ahora) => {
        const objetivo = reducido() ? 0 : Math.max(-TOPE, Math.min(TOPE, velocidadDeCostado(ahora) * INCLINACION));

        giro += (objetivo - giro) * SUAVIDAD;
        carta.style.transform = `translate(${donde.dx}px, ${donde.dy}px) rotate(${giro.toFixed(2)}deg) scale(1.04)`;
        cuadro = requestAnimationFrame(dibujar);
    };

    carta.addEventListener('pointerdown', (evento) => {
        if (! evento.isPrimary || evento.button !== 0 || inicio !== null) {
            return;
        }

        inicio = { x: evento.clientX, y: evento.clientY, puntero: evento.pointerId };
        muestras = [{ x: evento.clientX, y: evento.clientY, t: evento.timeStamp }];
    });

    carta.addEventListener('pointermove', (evento) => {
        if (inicio === null || evento.pointerId !== inicio.puntero) {
            return;
        }

        const dx = evento.clientX - inicio.x;
        const dy = evento.clientY - inicio.y;

        if (! arrastrando) {
            if (Math.hypot(dx, dy) < UMBRAL) {
                return;
            }

            arrastrando = true;
            giro = 0;
            carta.setPointerCapture(evento.pointerId);
            carta.classList.add('arrastrando');
            // Si estaba volviendo de un arrastre anterior, se la agarra desde donde va.
            carta.getAnimations().forEach((animacion) => animacion.cancel());
            enderezar(carta);
            cuadro = requestAnimationFrame(dibujar);
        }

        // Hacia abajo se resiste: ahí no hay nada que hacer, y así no parece que se cae.
        donde = { dx, dy: dy > 0 ? dy * 0.3 : dy };

        muestras.push({ x: evento.clientX, y: evento.clientY, t: evento.timeStamp });

        if (muestras.length > 6) {
            muestras.shift();
        }

        alApuntar(sobreLaMesa(evento.clientY));
    });

    const terminar = (evento, cancelado) => {
        if (inicio === null || evento.pointerId !== inicio.puntero) {
            return;
        }

        const desde = inicio;
        inicio = null;

        if (! arrastrando) {
            return;
        }

        arrastrando = false;
        cancelAnimationFrame(cuadro);
        alApuntar(false);

        // Al soltar llega además un toque común: ese no tiene que volver a jugar la carta.
        tragarElToque = true;
        setTimeout(() => {
            tragarElToque = false;
        }, 0);

        const primera = muestras[0];
        const ultima = muestras[muestras.length - 1];
        const velocidad = (primera.y - ultima.y) / Math.max(1, ultima.t - primera.t);
        const tiron = velocidad > TIRON && desde.y - evento.clientY > TIRON_MINIMO;

        // Si la jugó, la carta sale volando desde donde quedó: no se la toca.
        if (! cancelado && (sobreLaMesa(evento.clientY) || tiron) && alSoltar()) {
            return;
        }

        volverALaMano();
    };

    const volverALaMano = () => {
        const desde = carta.style.transform;

        carta.style.transform = '';
        carta.classList.remove('arrastrando');

        if (desde && ! reducido()) {
            carta.animate([{ transform: desde }, { transform: 'none' }], VUELTA);
        }
    };

    carta.addEventListener('pointerup', (evento) => terminar(evento, false));
    carta.addEventListener('pointercancel', (evento) => terminar(evento, true));

    carta.addEventListener('click', (evento) => {
        if (tragarElToque) {
            evento.stopImmediatePropagation();
            tragarElToque = false;
        }
    }, { capture: true });

    inclinarHaciaElCursor(carta, { reducido, arrastrando: () => arrastrando });
}

/**
 * Con un mouse, la carta bajo el cursor se inclina apenas hacia él, en 3D: se siente que se la puede
 * agarrar. Se inclina el dibujo y no el botón, que ya se levanta al pasar el mouse (app.css). Responde
 * al cursor y se endereza al salir: nunca se mueve sola.
 */
function inclinarHaciaElCursor(carta, { reducido, arrastrando }) {
    carta.addEventListener('pointermove', (evento) => {
        if (! conCursor.matches || evento.pointerType !== 'mouse' || reducido() || arrastrando() || carta.disabled) {
            return;
        }

        const dibujo = carta.firstElementChild;
        const caja = carta.getBoundingClientRect();
        // De -0,5 a 0,5 desde el centro de la carta.
        const x = (evento.clientX - caja.left) / caja.width - 0.5;
        const y = (evento.clientY - caja.top) / caja.height - 0.5;

        carta.classList.add('al-cursor');
        dibujo.style.transform = `perspective(40em) rotateX(${(-y * HACIA_EL_CURSOR).toFixed(2)}deg) rotateY(${(x * HACIA_EL_CURSOR).toFixed(2)}deg)`;
    });

    carta.addEventListener('pointerleave', () => enderezar(carta));
}

/** Deja el dibujo derecho. Al salir el cursor vuelve con la transición de los estilos (app.css, .naipe-jugable > .carta). */
function enderezar(carta) {
    carta.classList.remove('al-cursor');

    if (carta.firstElementChild) {
        carta.firstElementChild.style.transform = '';
    }
}
