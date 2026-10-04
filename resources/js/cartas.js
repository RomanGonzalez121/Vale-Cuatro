/*
 | Ayudas para dibujar y comparar cartas en el navegador.
 |
 | Sirven a las pantallas que muestran cartas sin estar jugando una partida
 | real (la maqueta de la mesa, las mesitas de "Cómo se juega"). En una partida
 | de verdad quien decide es el motor de reglas del servidor, no este archivo.
 */

export const LLEGADA = 'cubic-bezier(0.23, 1, 0.32, 1)';

const PALOS = ['espada', 'basto', 'oro', 'copa'];
const NUMEROS = [1, 2, 3, 4, 5, 6, 7, 10, 11, 12];

export const movimientoReducido = window.matchMedia('(prefers-reduced-motion: reduce)');

export function numeroDe(carta) {
    return Number(carta.split('-')[0]);
}

export function paloDe(carta) {
    return carta.split('-')[1];
}

export function nombreDe(carta) {
    return `${numeroDe(carta)} de ${paloDe(carta)}`;
}

/** De 14 (el 1 de espada) a 1 (los 4). Dos cartas con la misma fuerza empatan. */
export function fuerza(carta) {
    const especiales = { '1-espada': 14, '1-basto': 13, '7-espada': 12, '7-oro': 11 };

    return especiales[carta] ?? { 3: 10, 2: 9, 1: 8, 12: 7, 11: 6, 10: 5, 7: 4, 6: 3, 5: 2, 4: 1 }[numeroDe(carta)];
}

/** El escalón de la carta en el orden del truco: 1 es la más fuerte, 14 la más débil. */
export function escalon(carta) {
    return 15 - fuerza(carta);
}

export function valorDeEnvido(carta) {
    return numeroDe(carta) >= 10 ? 0 : numeroDe(carta);
}

export function tanto(cartas) {
    let mejor = Math.max(...cartas.map(valorDeEnvido));

    cartas.forEach((a, i) => {
        cartas.slice(i + 1).forEach((b) => {
            if (paloDe(a) === paloDe(b)) {
                mejor = Math.max(mejor, 20 + valorDeEnvido(a) + valorDeEnvido(b));
            }
        });
    });

    return mejor;
}

export function cartasAlAzar(cantidad) {
    const mazo = PALOS.flatMap((palo) => NUMEROS.map((numero) => `${numero}-${palo}`));

    for (let i = mazo.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));

        [mazo[i], mazo[j]] = [mazo[j], mazo[i]];
    }

    return mazo.slice(0, cantidad);
}

/** Una carta nueva (o el dorso) clonada de las plantillas que deja <x-mazo.plantillas />. */
export function plantilla(nombre) {
    return document.querySelector(`template[data-plantilla="${nombre}"]`).content.firstElementChild.cloneNode(true);
}

/** Pone una carta en su lugar y la hace llegar como recién repartida. */
export function repartirEn(lugar, carta, orden = 0) {
    const naipe = plantilla(carta);

    lugar.replaceChildren(naipe);
    naipe.animate(
        movimientoReducido.matches
            ? [{ opacity: 0 }, { opacity: 1 }]
            : [{ opacity: 0, transform: 'translateY(-28%) rotate(9deg)' }, { opacity: 1, transform: 'none' }],
        { duration: movimientoReducido.matches ? 150 : 280, delay: movimientoReducido.matches ? 0 : orden * 70, easing: LLEGADA, fill: 'backwards' },
    );
}
