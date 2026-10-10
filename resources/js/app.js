import Alpine from 'alpinejs';
import { repartirEn } from './cartas';
import historial from './historial';
import { prepararLogos } from './logo';
import mesa from './mesa';
import { prepararModo } from './modo';
import { duelo, tantoDeEnvido } from './reglas';
import repeticion from './repeticion';
import sala from './sala';
import { prepararLlaves } from './torneo';

/*
 | x-carta="expresión": dibuja en el elemento la carta que diga la expresión
 | ('7-oro', 'dorso') o lo vacía si no hay ninguna. Solo vuelve a dibujar
 | cuando la carta cambia, para que las que ya estaban no se muevan. Con
 | data-orden, las que llegan juntas (un reparto) entran de a una.
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
            } else {
                repartirEn(elemento, carta, Number(elemento.dataset.orden ?? 0));
            }
        });
    });
});

Alpine.data('mesa', mesa);
Alpine.data('duelo', duelo);
Alpine.data('tantoDeEnvido', tantoDeEnvido);
Alpine.data('repeticion', repeticion);
Alpine.data('historial', historial);
Alpine.data('sala', sala);

window.Alpine = Alpine;
Alpine.start();

prepararLogos();
prepararModo();
prepararLlaves();
