/*
 | El plano de un barrio de la gira: al volver después de pasar una parada, tu carta camina por las calles
 | hasta la parada que sigue, el tramo del recorrido se enciende como un fósforo y la parada nueva aparece.
 |
 | El servidor dibuja el plano como quedó: la carta ya en la parada que sigue y el último tramo ya hecho (va en
 | su propio grupo, .plano-tramo, con sus puntos en data-tramo). Acá solo se cuenta el camino que no se vio.
 | Qué parada se vio por última vez lo recuerda este navegador; la primera vez que se ve un barrio no se cuenta.
 |
 | Solo se mueven transform y opacity. Un toque o una tecla lo terminan en el acto. Con movimiento reducido la
 | carta y el tramo aparecen con un fundido, sin caminar.
 */

import { LLEGADA, movimientoReducido } from './cartas';

const CLAVE = 'vale-cuatro:gira:';

// Moverse por la pantalla: arranca y frena suave. Cuánto tarda depende del largo del tramo, con topes.
const CAMINAR = 'cubic-bezier(0.77, 0, 0.175, 1)';
const POR_UNIDAD = 2.4;
const CAMINA_MENOS = 380;
const CAMINA_MAS = 720;
const ANTES = 260;
const APARECE = 220;
const FUNDIDO = 200;

export function prepararGira() {
    const plano = [...document.querySelectorAll('svg.plano[data-barrio]')].find((svg) => svg.getClientRects().length > 0);

    if (! plano) {
        return;
    }

    const clave = CLAVE + plano.dataset.barrio;
    const ahora = plano.dataset.sigue;
    const antes = recordado(clave);

    recordar(clave, ahora);

    // Se cuenta solo si la última vez la parada que seguía era justo la anterior a esta.
    if (antes === null || ahora === 'completo' || Number(antes) !== Number(ahora) - 1 || ! plano.dataset.tramo) {
        return;
    }

    caminar(plano);
}

function caminar(plano) {
    const marcador = plano.querySelector('.plano-marcador');
    const tramo = plano.querySelector('.plano-tramo');
    const cara = plano.querySelector('.parada[data-estado="sigue"] .parada-cara');
    const puntos = plano.dataset.tramo.split(' ').map((par) => par.split(',').map(Number));

    if (! marcador || ! tramo || puntos.length < 2) {
        return;
    }

    if (movimientoReducido.matches) {
        const animaciones = [marcador, tramo].map((parte) => parte.animate(
            [{ opacity: 0 }, { opacity: 1 }],
            { duration: FUNDIDO, delay: ANTES, easing: 'ease', fill: 'backwards' },
        ));
        cortarAlTocar(animaciones);

        return;
    }

    // La carta está dibujada en la parada nueva: se la corre hacia atrás y vuelve por cada esquina del tramo.
    const [finX, finY] = puntos.at(-1);
    const largos = puntos.slice(1).map(([x, y], i) => Math.hypot(x - puntos[i][0], y - puntos[i][1]));
    const largo = largos.reduce((suma, uno) => suma + uno, 0);
    let andado = 0;
    const cuadros = puntos.map(([x, y], i) => {
        andado += i === 0 ? 0 : largos[i - 1];

        return { transform: `translate(${x - finX}px, ${y - finY}px)`, offset: largo === 0 ? i / (puntos.length - 1) : andado / largo };
    });
    const dura = Math.min(CAMINA_MAS, Math.max(CAMINA_MENOS, largo * POR_UNIDAD));

    const animaciones = [
        marcador.animate(cuadros, { duration: dura, delay: ANTES, easing: CAMINAR, fill: 'backwards' }),
        // El tramo se enciende mientras la carta lo recorre.
        tramo.animate([{ opacity: 0 }, { opacity: 1 }], { duration: dura, delay: ANTES, easing: LLEGADA, fill: 'backwards' }),
    ];

    // La parada nueva aparece cuando la carta llega.
    if (cara) {
        animaciones.push(cara.animate(
            [{ opacity: 0, transform: 'scale(0.85)' }, { opacity: 1, transform: 'scale(1)' }],
            { duration: APARECE, delay: ANTES + dura - 60, easing: LLEGADA, fill: 'backwards' },
        ));
    }

    cortarAlTocar(animaciones);
}

// Un toque o una tecla terminan todo en el acto: el plano queda como lo dibujó el servidor.
function cortarAlTocar(animaciones) {
    const cortar = () => {
        animaciones.forEach((animacion) => animacion.finish());
        quitar();
    };
    const quitar = () => {
        window.removeEventListener('pointerdown', cortar, true);
        window.removeEventListener('keydown', cortar, true);
    };

    window.addEventListener('pointerdown', cortar, true);
    window.addEventListener('keydown', cortar, true);
    Promise.all(animaciones.map((animacion) => animacion.finished)).then(quitar, quitar);
}

function recordado(clave) {
    try {
        return localStorage.getItem(clave);
    } catch {
        return null;
    }
}

function recordar(clave, valor) {
    try {
        localStorage.setItem(clave, valor);
    } catch {
        // Sin almacenamiento la próxima vez no se cuenta nada: el plano igual queda bien dibujado.
    }
}
