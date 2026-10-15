/*
 | Arrastrar una carta de la mano a la mesa para jugarla.
 |
 | Tocar la carta (o elegirla con el teclado) la sigue jugando igual: arrastrar es una forma más, para
 | quien prefiere tirarla con el dedo. Mientras se arrastra, la carta sigue al dedo, se levanta apenas y
 | se inclina hacia donde va. Al soltarla juega si quedó sobre la mesa o si se la tiró con un tirón
 | rápido hacia arriba, aunque no haya llegado lejos. Si no, vuelve a su lugar.
 |
 | En el truco no hay deshacer: un movimiento chico no juega nada. Hasta pasar el umbral es un toque
 | común, y hacia abajo la carta se resiste.
 |
 | Solo se mueve con transform. Se ignora un segundo dedo, y el puntero queda capturado por la carta
 | mientras dura el arrastre, así no se pierde si el dedo sale de ella.
 */

/** Cuántos píxeles tiene que moverse el dedo para que el toque pase a ser arrastre. */
const UMBRAL = 10;

/** Un tirón hacia arriba más rápido que esto (píxeles por milisegundo) juega aunque no llegue a la mesa. */
const TIRON = 0.6;

/** Y tiene que haber subido por lo menos esto: un temblor rápido no es un tirón. */
const TIRON_MINIMO = 30;

/** La vuelta a la mano, con la curva de llegada del sitio. */
const VUELTA = 200;
const CURVA = 'cubic-bezier(0.23, 1, 0.32, 1)';

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

    carta.addEventListener('pointerdown', (evento) => {
        if (! evento.isPrimary || evento.button !== 0 || inicio !== null) {
            return;
        }

        inicio = { x: evento.clientX, y: evento.clientY, puntero: evento.pointerId };
        muestras = [{ y: evento.clientY, t: evento.timeStamp }];
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
            carta.setPointerCapture(evento.pointerId);
            carta.classList.add('arrastrando');
            // Si estaba volviendo de un arrastre anterior, se la agarra desde donde va.
            carta.getAnimations().forEach((animacion) => animacion.cancel());
        }

        // Hacia abajo se resiste: ahí no hay nada que hacer, y así no parece que se cae.
        const y = dy > 0 ? dy * 0.3 : dy;
        const giro = Math.max(-8, Math.min(8, dx * 0.04));

        carta.style.transform = `translate(${dx}px, ${y}px) rotate(${giro}deg) scale(1.04)`;

        muestras.push({ y: evento.clientY, t: evento.timeStamp });

        if (muestras.length > 5) {
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
        const donde = carta.style.transform;

        carta.style.transform = '';
        carta.classList.remove('arrastrando');

        if (donde && ! reducido()) {
            carta.animate([{ transform: donde }, { transform: 'none' }], { duration: VUELTA, easing: CURVA });
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
}
