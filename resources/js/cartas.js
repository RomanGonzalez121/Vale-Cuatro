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

/** Las cartas que arman el tanto: las dos del mismo palo que más suman o, si no hay, la más alta. */
export function cartasDelTanto(cartas) {
    let mejores = [cartas.reduce((a, b) => (valorDeEnvido(b) > valorDeEnvido(a) ? b : a))];

    cartas.forEach((a, i) => {
        cartas.slice(i + 1).forEach((b) => {
            if (paloDe(a) === paloDe(b) && tanto([a, b]) >= tanto(mejores)) {
                mejores = [a, b];
            }
        });
    });

    return mejores;
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

/**
 * El reparto: una carta que ya está en su lugar llega desde el mazo, con 70 ms entre carta y carta.
 * Lo usan la mesa y la sala de espera, para que el reparto sea el mismo en las dos. Con movimiento
 * reducido aparece con un fundido corto. Devuelve la animación.
 */
export function llegarDelMazo(carta, mazo, orden, { reducido = movimientoReducido.matches, demora = 0 } = {}) {
    const origen = mazo.getBoundingClientRect();
    const destino = carta.getBoundingClientRect();
    const cuadros = reducido
        ? [{ opacity: 0 }, { opacity: 1 }]
        : [
            { opacity: 0, transform: `translate(${origen.left - destino.left}px, ${origen.top - destino.top}px) rotate(18deg)` },
            { opacity: 1, offset: 0.35 },
            { opacity: 1, transform: 'none' },
        ];

    return carta.animate(cuadros, {
        duration: reducido ? 150 : 280,
        delay: reducido ? 0 : demora + orden * 70,
        easing: LLEGADA,
        fill: 'backwards',
    });
}

/*
 | Un canto de varias palabras en una mesa angosta queda con letras chicas ("Contraflor al resto" a 360 px
 | medía 28 px contra los 93 de "Truco"), y el canto es el gesto fuerte de la mesa. Si partido en dos
 | renglones se lee bastante más grande, se parte por donde los dos queden más parejos; si no, va entero.
 |
 | Cada pantalla tiene su propia cuenta del tamaño (la mesa y la repetición miden contra cosas distintas):
 | pasa en "letra" una función que dice cuántos píxeles tendría la letra con esos renglones.
 */
const LARGO_PARA_PARTIR = 9;
const GANANCIA_PARA_PARTIR = 1.15;

export function cantoEnRenglones(texto, letra) {
    const palabras = texto.split(' ');

    // Hasta ese largo el canto va siempre en un renglón: "No quiero" se lee bien entero.
    if (palabras.length < 2 || texto.length <= LARGO_PARA_PARTIR) {
        return texto;
    }

    const masLargo = (renglones) => Math.max(...renglones.map((renglon) => renglon.length));
    const partido = palabras.slice(1)
        .map((_, corte) => [palabras.slice(0, corte + 1).join(' '), palabras.slice(corte + 1).join(' ')])
        .reduce((mejor, renglones) => (masLargo(renglones) < masLargo(mejor) ? renglones : mejor));

    return letra(partido) > letra([texto]) * GANANCIA_PARA_PARTIR ? partido.join('\n') : texto;
}

/*
 | Lo que se pasa de un cuadro a otro en la repetición sin que nadie lo haya pedido con un clic (las flechas
 | del teclado, volver atrás, saltar de mano) no se anima: se repite muchas veces y la animación lo haría
 | sentir lento. Quien cambia el cuadro pone "quieto" en true y lo saca cuando la pantalla ya se dibujó.
 */
export const reparto = { quieto: false };

/** Pone una carta en su lugar y la hace llegar como recién repartida. */
export function repartirEn(lugar, carta, orden = 0) {
    const naipe = plantilla(carta);

    lugar.replaceChildren(naipe);

    if (reparto.quieto) {
        return;
    }

    naipe.animate(
        movimientoReducido.matches
            ? [{ opacity: 0 }, { opacity: 1 }]
            : [{ opacity: 0, transform: 'translateY(-28%) rotate(9deg)' }, { opacity: 1, transform: 'none' }],
        { duration: movimientoReducido.matches ? 150 : 280, delay: movimientoReducido.matches ? 0 : orden * 70, easing: LLEGADA, fill: 'backwards' },
    );
}
