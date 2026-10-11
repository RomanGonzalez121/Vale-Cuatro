/*
 | La apertura de la aplicación instalada.
 |
 | Al abrirla, el sistema muestra una pantalla quieta con el ícono y el nombre. La portada la continúa con
 | el mismo dibujo (resources/views/components/apertura.blade.php) y acá se mueve: las cuatro cabezas de
 | los fósforos se encienden de a una, el nombre acusa el golpe de la cuarta y todo se funde a la portada.
 |
 | Cuándo se muestra lo decide el layout antes de pintar (pone "abriendo" en la raíz): solo instalada, una
 | vez por apertura y sin movimiento reducido. Dura menos de un segundo y se corta tocando o con una tecla.
 | Solo anima transform y opacity.
 */

import { LLEGADA, movimientoReducido } from './cartas';

const ANTES = 60;
const ENTRE_CABEZAS = 80;
const ENCENDIDO = 420;
const GOLPE = 300;
const FUNDIDO = 180;

export function prepararApertura() {
    const raiz = document.documentElement;
    const apertura = document.querySelector('.apertura');

    if (! apertura || ! raiz.classList.contains('abriendo')) {
        return;
    }

    if (movimientoReducido.matches) {
        raiz.classList.remove('abriendo');

        return;
    }

    let cerrando = false;

    const cerrar = (dura) => {
        if (cerrando) {
            return;
        }

        cerrando = true;
        document.removeEventListener('keydown', cortar);

        apertura
            .animate([{ opacity: 1 }, { opacity: 0 }], { duration: dura, easing: LLEGADA, fill: 'forwards' })
            .finished.catch(() => {})
            .then(() => raiz.classList.remove('abriendo'));
    };

    // Quien ya la vio no tiene por qué esperarla: un toque o una tecla la corren enseguida.
    const cortar = () => cerrar(120);

    apertura.addEventListener('pointerdown', cortar);
    document.addEventListener('keydown', cortar);

    const llamas = [...apertura.querySelectorAll('.iso-llama')];
    const ultima = ANTES + (llamas.length - 1) * ENTRE_CABEZAS;

    // Cada cabeza prende de golpe y se asienta, y queda encendida: no vuelve a apagarse como en el logo.
    llamas.forEach((llama, i) => {
        llama.animate(
            [
                { opacity: 0, transform: 'scale(1)' },
                { opacity: 1, transform: 'scale(1.9)', offset: 0.3 },
                { opacity: 1, transform: 'scale(1.3)' },
            ],
            { duration: ENCENDIDO, delay: ANTES + i * ENTRE_CABEZAS, easing: LLEGADA, fill: 'both' },
        );
    });

    const golpe = apertura.querySelector('.apertura-nombre').animate(
        [{ transform: 'none' }, { transform: 'translateX(0.09em) skewX(-5deg)' }, { transform: 'none' }],
        { duration: GOLPE, delay: ultima + ENCENDIDO * 0.3, easing: LLEGADA },
    );

    golpe.finished.catch(() => {}).then(() => cerrar(FUNDIDO));
}
