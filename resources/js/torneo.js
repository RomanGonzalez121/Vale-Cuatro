/*
 | Las llaves de un torneo: cuenta los resultados nuevos.
 |
 | La página llega del servidor ya dibujada como quedó. Cuando alguien vuelve de jugar su partida hay
 | resultados que todavía no vio: en vez de aparecer de golpe, se cuentan. La carta de quien perdió se da
 | vuelta, aparece el tanteo, se enciende la llave y las cartas de quienes pasaron llegan al cruce siguiente.
 |
 | Cada cruce cuenta lo suyo cuando está a la vista: las llaves suelen empezar debajo de la primera
 | pantalla, y en el celular las rondas van una debajo de la otra. Lo que está a la vista a la vez se
 | cuenta en orden: los resultados de una ronda, quiénes llegan a la siguiente, sus resultados, y así.
 |
 | Qué resultados ya se mostraron lo recuerda este navegador. La primera vez que se ve un torneo no se
 | cuenta nada: no hay nada que la persona haya dejado de ver.
 |
 | Solo se mueven transform y opacity. Un toque o Escape cortan el relato y dejan todo como quedó.
 | Con movimiento reducido no se cuenta nada: la página ya dice cómo quedó.
 */

import { LLEGADA, movimientoReducido } from './cartas';

const CLAVE = 'vale-cuatro:torneo:';

// Cuánto se deja ver un cruce antes de empezar a contarlo, y cuánto se espera entre dos cruces de la misma ronda.
const ANTES = 260;
const ENTRE_CRUCES = 70;

// Los resultados de un cruce: cuándo empieza cada cosa, contando desde que se da vuelta la carta.
const TANTEO = 170;
const BRAZO = 320;
const DURAN_LOS_RESULTADOS = 540;

// La llegada a un cruce: el palito de la llave, la llama de su cabeza y las cartas.
const LLAMA = 160;
const CARTAS = 220;
const DURA_LA_LLEGADA = 600;

export function prepararLlaves() {
    const llaves = document.querySelector('.llaves[data-torneo]');

    if (! llaves) {
        return;
    }

    const cruces = [...llaves.querySelectorAll('[data-cruce]')];
    const con = (marca) => cruces.filter((cruce) => marca in cruce.dataset).map((cruce) => cruce.dataset.cruce);
    const ahora = { resueltos: con('resuelto'), completos: con('completo') };
    const antes = recordado(llaves.dataset.torneo);

    recordar(llaves.dataset.torneo, ahora);

    if (! antes || movimientoReducido.matches || ! ('IntersectionObserver' in window)) {
        return;
    }

    const nuevo = (cruce, clase) => ahora[clase].includes(cruce.dataset.cruce) && ! antes[clase].includes(cruce.dataset.cruce);
    // Primero los resultados: ponen boca arriba la carta que perdió, y la llegada tiene que encontrarla así.
    const partes = cruces.flatMap((cruce) => [
        nuevo(cruce, 'resueltos') ? resultados(cruce) : null,
        nuevo(cruce, 'completos') ? llegada(cruce, llaves) : null,
    ]).filter(Boolean);

    if (partes.length > 0) {
        contar(partes);
    }
}

/**
 * Cuenta cada parte cuando su cruce está a la vista. "paso" es el lugar de la parte en el relato (los
 * resultados de la primera ronda, la llegada a la segunda, sus resultados...): una parte no empieza antes
 * de que termine el paso anterior, si ese ya está en marcha.
 */
function contar(partes) {
    const terminaEn = new Map();

    const empezar = (parte) => {
        const ahora = performance.now();
        const desde = Math.max(ahora + ANTES, terminaEn.get(parte.paso - 1) ?? 0) + parte.orden * ENTRE_CRUCES;

        terminaEn.set(parte.paso, Math.max(terminaEn.get(parte.paso) ?? 0, desde + parte.dura));
        parte.empezar(desde - ahora);
    };

    const vigia = new IntersectionObserver((entradas) => {
        // Las que aparecen juntas arrancan en el orden del relato.
        entradas
            .filter((entrada) => entrada.isIntersecting)
            .flatMap((entrada) => partes.filter((parte) => parte.cruce === entrada.target && ! parte.empezada))
            .sort((una, otra) => una.paso - otra.paso || una.orden - otra.orden)
            .forEach((parte) => {
                parte.empezada = true;
                empezar(parte);
            });
    }, { threshold: 0.5 });

    new Set(partes.map((parte) => parte.cruce)).forEach((cruce) => vigia.observe(cruce));

    // Un toque o Escape cortan el relato: todo queda como lo mandó el servidor. Se escucha el toque entero
    // (click) y no el dedo que apoya: en el celular, bajar hasta las llaves no puede cortarlo.
    const cortar = (evento) => {
        if (evento.type === 'keydown' && evento.key !== 'Escape') {
            return;
        }

        vigia.disconnect();
        partes.forEach((parte) => parte.terminar());
        document.removeEventListener('click', cortar, true);
        document.removeEventListener('keydown', cortar, true);
    };

    document.addEventListener('click', cortar, true);
    document.addEventListener('keydown', cortar, true);
}

/**
 * Los resultados de un cruce: la carta de quien perdió se da vuelta, aparecen los tanteos y se enciende
 * el brazo de la llave que sale hacia la ronda siguiente.
 */
function resultados(cruce) {
    const ronda = Number(cruce.dataset.ronda);
    const esLaFinal = cruce.closest('.llave-final') !== null;
    const hueco = cruce.querySelector('.hueco-baza[data-boca-abajo]');
    // Hasta que le toque, la carta que perdió está boca arriba: así se la ve perder.
    const giro = hueco ? bocaArriba(hueco) : null;
    const movimientos = [
        ...[...cruce.querySelectorAll('.cruce-tanteo')].map((tanteo) => quieta(tanteo, [{ opacity: 0, transform: 'translateY(0.3em)' }, { opacity: 1, transform: 'none' }], TANTEO, 200)),
        // El brazo de la final es una línea derecha y se dibuja; el de las otras rondas dobla, y aparece.
        quieta(cruce.querySelector('.llave-brazo'), esLaFinal ? [{ transform: 'scaleX(0)' }, { transform: 'none' }] : [{ opacity: 0 }, { opacity: 1 }], BRAZO, 200),
    ].filter(Boolean);
    let espera = null;

    return {
        cruce,
        paso: ronda * 2 - 1,
        orden: ordenEnSuRonda(cruce),
        dura: DURAN_LOS_RESULTADOS,
        empezar(demora) {
            espera = setTimeout(() => giro?.darVuelta(), demora);
            movimientos.forEach((movimiento) => movimiento.soltar(demora));
        },
        terminar() {
            clearTimeout(espera);
            giro?.terminar();
            movimientos.forEach((movimiento) => movimiento.terminar());
        },
    };
}

/**
 * La llegada a un cruce (o al lugar del campeón): se dibuja el palito de la llave, se enciende su cabeza
 * y llegan las cartas de quienes pasaron, con sus nombres.
 */
function llegada(cruce, llaves) {
    const ronda = cruce.dataset.ronda ? Number(cruce.dataset.ronda) : rondas(llaves) + 1;
    const movimientos = [
        quieta(cruce.querySelector('.llave-palito'), [{ transform: 'scaleX(0)' }, { transform: 'none' }], 0, 200),
        quieta(cruce.querySelector('.cruce-llama'), [{ opacity: 0, transform: 'scale(0.5)' }, { opacity: 1, transform: 'none' }], LLAMA, 160),
        ...[...cruce.querySelectorAll('.hueco-baza > :not(template), .carta-campeon > *')].map((carta, cual) => quieta(carta, [{ opacity: 0, transform: 'translateX(-45%) rotate(-8deg)' }, { opacity: 1, transform: 'none' }], CARTAS + cual * ENTRE_CRUCES, 280)),
        ...[...cruce.querySelectorAll('.cruce-lado')].map((lado, cual) => quieta(lado, [{ opacity: 0 }, { opacity: 1 }], CARTAS + cual * ENTRE_CRUCES, 200)),
    ].filter(Boolean);

    return {
        cruce,
        paso: (ronda - 1) * 2,
        orden: ordenEnSuRonda(cruce),
        dura: DURA_LA_LLEGADA,
        empezar(demora) {
            movimientos.forEach((movimiento) => movimiento.soltar(demora));
        },
        terminar() {
            movimientos.forEach((movimiento) => movimiento.terminar());
        },
    };
}

/**
 * Una animación armada y en pausa en su primer cuadro: el elemento espera así hasta que le toque.
 * "soltar" la larga después de una demora (más la suya propia) y "terminar" la deja en su final.
 */
function quieta(elemento, cuadros, desde, dura) {
    if (! elemento) {
        return null;
    }

    const animacion = elemento.animate(cuadros, { duration: dura, delay: desde, easing: LLEGADA, fill: 'backwards' });

    animacion.pause();

    return {
        soltar(demora) {
            animacion.effect.updateTiming({ delay: desde + demora });
            animacion.play();
        },
        terminar() {
            animacion.finish();
        },
    };
}

/**
 * Pone boca arriba una carta que el servidor mandó boca abajo, lista para darse vuelta: la cara de un
 * lado y el dorso del otro, con la pieza del sitio para eso (.giro). Devuelve cómo darla vuelta y cómo
 * dejarla terminada sin esperar.
 */
function bocaArriba(hueco) {
    const dorso = hueco.querySelector('svg');
    const cara = hueco.querySelector('template[data-cara]').content.firstElementChild.cloneNode(true);
    const carta = document.createElement('div');
    let lista = false;

    carta.className = 'giro';
    dorso.replaceWith(carta);
    dorso.classList.add('giro-cara');
    carta.append(cara, dorso);

    // Terminada, vuelve a ser lo que mandó el servidor: el dorso solo.
    const terminar = () => {
        if (! lista) {
            lista = true;
            dorso.classList.remove('giro-cara');
            carta.replaceWith(dorso);
        }
    };

    return {
        terminar,
        darVuelta() {
            carta.addEventListener('transitionend', terminar, { once: true });
            carta.dataset.vuelta = 'true';
        },
    };
}

function rondas(llaves) {
    return Math.max(...[...llaves.querySelectorAll('[data-ronda]')].map((cruce) => Number(cruce.dataset.ronda)));
}

/** El puesto del cruce entre los de su ronda, de arriba hacia abajo. */
function ordenEnSuRonda(cruce) {
    return [...cruce.parentElement.children].indexOf(cruce);
}

function recordado(torneo) {
    try {
        const guardado = JSON.parse(localStorage.getItem(CLAVE + torneo));

        return Array.isArray(guardado?.resueltos) && Array.isArray(guardado?.completos) ? guardado : null;
    } catch {
        return null;
    }
}

function recordar(torneo, estado) {
    try {
        localStorage.setItem(CLAVE + torneo, JSON.stringify(estado));
    } catch {
        // Sin dónde recordar, la próxima vez no se cuenta nada: la página se ve igual.
    }
}
