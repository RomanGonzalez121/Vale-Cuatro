import Alpine from 'alpinejs';
import { prepararLogos } from './logo';
import mesa from './mesa';
import { duelo, tantoDeEnvido } from './reglas';

Alpine.data('mesa', mesa);
Alpine.data('duelo', duelo);
Alpine.data('tantoDeEnvido', tantoDeEnvido);

window.Alpine = Alpine;
Alpine.start();

prepararLogos();
