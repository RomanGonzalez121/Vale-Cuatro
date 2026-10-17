import Alpine from 'alpinejs';
import { prepararApertura } from './apertura';
import avisoFlotante from './aviso-flotante';
import { repartirEn } from './cartas';
import historial from './historial';
import { prepararInstalacion } from './instalar';
import { prepararLogos } from './logo';
import mesa from './mesa';
import mesaDeCuatro from './mesa-cuatro';
import { prepararModo } from './modo';
import perfil from './perfil';
import { duelo, tantoDeEnvido } from './reglas';
import repeticion from './repeticion';
import sala from './sala';
import sinConexion from './sin-conexion';
import { prepararLlaves } from './torneo';
import { prepararGira } from './gira';

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
Alpine.data('mesaDeCuatro', mesaDeCuatro);
Alpine.data('perfil', perfil);
Alpine.data('avisoFlotante', avisoFlotante);
Alpine.data('duelo', duelo);
Alpine.data('tantoDeEnvido', tantoDeEnvido);
Alpine.data('repeticion', repeticion);
Alpine.data('historial', historial);
Alpine.data('sala', sala);
Alpine.data('sinConexion', sinConexion);

window.Alpine = Alpine;
Alpine.start();

prepararApertura();
prepararLogos();
prepararModo();
prepararLlaves();
prepararGira();
prepararInstalacion();
