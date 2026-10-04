/*
 | Animación del logo: contar hasta cuatro.
 |
 | Los cuatro fósforos del isotipo caen de a uno, con el mismo gesto con el que
 | se anota un punto en el tanteador, y la cabeza de cada uno se enciende al
 | llegar. Cuando cae el cuarto, el nombre acusa el golpe.
 |
 | Responde a una acción (pasar el mouse o llegar con el teclado), nunca corre
 | sola. Solo anima transform y opacity. Si ya está corriendo, no se reinicia.
 */

import { LLEGADA, movimientoReducido } from './cartas';

const ENTRE_FOSFOROS = 75;
const CAIDA = 280;

export function contarCuatro(logo) {
    if (movimientoReducido.matches || logo.dataset.contando) {
        return;
    }

    const fosforos = [...logo.querySelectorAll('.iso-fosforo')];
    const nombre = logo.querySelector('.logo-nombre');

    logo.dataset.contando = 'si';

    const animaciones = fosforos.flatMap((fosforo, i) => {
        const espera = i * ENTRE_FOSFOROS;

        return [
            fosforo.animate(
                [
                    { opacity: 0, transform: 'translateY(-9px) rotate(-18deg)' },
                    { opacity: 1, transform: 'none' },
                ],
                { duration: CAIDA, delay: espera, easing: LLEGADA, fill: 'backwards' },
            ),
            fosforo.querySelector('.iso-llama').animate(
                [
                    { opacity: 0, transform: 'scale(1)' },
                    { opacity: 1, transform: 'scale(1.9)', offset: 0.25 },
                    { opacity: 1, transform: 'scale(1.35)', offset: 0.6 },
                    { opacity: 0, transform: 'scale(1)' },
                ],
                { duration: 620, delay: espera + CAIDA * 0.45, easing: 'ease-out' },
            ),
        ];
    });

    if (nombre) {
        animaciones.push(
            nombre.animate(
                [{ transform: 'none' }, { transform: 'translateX(0.09em) skewX(-5deg)' }, { transform: 'none' }],
                { duration: 300, delay: 3 * ENTRE_FOSFOROS + CAIDA * 0.5, easing: LLEGADA },
            ),
        );
    }

    Promise.allSettled(animaciones.map((animacion) => animacion.finished)).then(() => delete logo.dataset.contando);
}

export function prepararLogos() {
    document.querySelectorAll('a:has(.logo)').forEach((enlace) => {
        const logo = enlace.querySelector('.logo');

        // El hover solo cuenta con mouse: en una pantalla táctil el toque ya es la navegación.
        enlace.addEventListener('pointerenter', (evento) => evento.pointerType === 'mouse' && contarCuatro(logo));
        enlace.addEventListener('focus', () => enlace.matches(':focus-visible') && contarCuatro(logo));
    });

    document.querySelectorAll('[data-contar-cuatro]').forEach((boton) => {
        boton.addEventListener('click', () => {
            document.querySelectorAll(boton.dataset.contarCuatro).forEach(contarCuatro);
        });
    });
}
