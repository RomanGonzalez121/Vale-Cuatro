/*
 | Modo claro y oscuro: el club de día y de noche.
 |
 | El modo inicial lo pone un script chico en el <head>, antes de pintar, para
 | que la página no parpadee. Acá está el cambio: la carta del botón se da
 | vuelta (CSS) y la página nueva se abre en círculo desde esa carta, con la
 | View Transitions API. Donde no existe, el modo cambia de golpe.
 */

import { LLEGADA, movimientoReducido } from './cartas';

const CLAVE = 'vale-cuatro:modo';

function modoActual() {
    return document.documentElement.dataset.modo === 'noche' ? 'noche' : 'dia';
}

function aplicar(modo) {
    document.documentElement.dataset.modo = modo;

    document.querySelectorAll('[data-cambiar-modo]').forEach((boton) => {
        boton.setAttribute('aria-label', modo === 'noche' ? 'Pasar a modo claro' : 'Pasar a modo oscuro');
    });

    // Donde el modo se dice con palabras (los ajustes de la mesa).
    document.querySelectorAll('[data-nombre-del-modo]').forEach((texto) => {
        texto.textContent = modo === 'noche' ? 'De noche' : 'De día';
    });

    // El color de la barra del navegador acompaña al fondo de la página.
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', getComputedStyle(document.body).backgroundColor);
}

function cambiar(boton) {
    const modo = modoActual() === 'noche' ? 'dia' : 'noche';

    try {
        localStorage.setItem(CLAVE, modo);
    } catch {
        // Sin almacenamiento el modo igual cambia; solo no se recuerda.
    }

    if (! document.startViewTransition) {
        aplicar(modo);

        return;
    }

    const transicion = document.startViewTransition(() => aplicar(modo));

    if (movimientoReducido.matches) {
        return;
    }

    // El círculo sale de la carta, no del botón entero: en los ajustes de la mesa el botón es un renglón.
    const { left, top, width, height } = (boton.querySelector('.modo-carta') ?? boton).getBoundingClientRect();
    const x = left + width / 2;
    const y = top + height / 2;
    const radio = Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y));

    transicion.ready.then(() => {
        document.documentElement.animate(
            { clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${radio}px at ${x}px ${y}px)`] },
            { duration: 480, easing: LLEGADA, pseudoElement: '::view-transition-new(root)' },
        );
    });
}

export function prepararModo() {
    aplicar(modoActual());

    document.querySelectorAll('[data-cambiar-modo]').forEach((boton) => {
        boton.addEventListener('click', () => cambiar(boton));
    });
}
