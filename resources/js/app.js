import Alpine from 'alpinejs';
import { plantilla, repartirEn } from './cartas';
import { prepararLogos } from './logo';
import mesa from './mesa';
import { prepararModo } from './modo';
import { duelo, tantoDeEnvido } from './reglas';
import repeticion from './repeticion';
import sala from './sala';

/*
 | x-carta="expresión": dibuja en el elemento la carta que diga la expresión
 | ('7-oro', 'dorso') o lo vacía si no hay ninguna. Solo vuelve a dibujar
 | cuando la carta cambia, para que las que ya estaban no se muevan.
 */
Alpine.directive('carta', (elemento, { expression }, { evaluateLater, effect }) => {
    const leer = evaluateLater(expression);
    let actual = null;

    effect(() => {
        leer((carta) => {
            if (carta === actual) {
                return;
            }

            actual = carta;

            if (! carta) {
                elemento.replaceChildren();
            } else if (carta === 'dorso') {
                elemento.replaceChildren(plantilla('dorso'));
            } else {
                repartirEn(elemento, carta);
            }
        });
    });
});

Alpine.data('mesa', mesa);
Alpine.data('duelo', duelo);
Alpine.data('tantoDeEnvido', tantoDeEnvido);
Alpine.data('repeticion', repeticion);
Alpine.data('sala', sala);

window.Alpine = Alpine;
Alpine.start();

prepararLogos();
prepararModo();
