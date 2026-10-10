<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * El torneo contra bots en un navegador de verdad: se elige su carta en la pantalla de modos, se arma,
 * se ven las llaves y de ahí se entra a la mesa de la primera partida.
 *
 * Prueba lo que los tests del servidor no ven: que la carta del torneo se puede elegir, que el botón
 * manda el tamaño y que la mesa de una partida de torneo abre con sus cartas.
 */
class ArmarUnTorneoTest extends DuskTestCase
{
    public function test_se_arma_un_torneo_desde_los_modos_y_se_entra_a_jugar_la_semifinal(): void
    {
        $this->browse(function (Browser $navegador) {
            $navegador->visit('/modos')->waitFor('.naipe-juego');

            // La segunda carta de la mano es la del torneo. Entra elegido el de cuatro.
            $navegador->script("document.querySelectorAll('.naipe-juego')[1].click()");
            $navegador->waitForText('Dos rondas, a 15 puntos.')
                ->press('Armar el torneo')
                ->waitForText('Las llaves', 15)
                ->assertPathBeginsWith('/torneo/')
                ->assertSee('Jugás la semifinal contra')
                ->assertSee('Te toca')
                ->assertPresent('.torneo-duelo [data-carta="4-copa"]');

            // De las llaves a la mesa: la partida va contra un bot con apodo y la barra dice qué partido es.
            $navegador->press('Jugar la semifinal')
                ->waitForLocation('/mesa')
                ->waitFor('.mesa-mano button', 15)
                ->assertSeeIn('.mesa-barra', 'Semifinal');

            $this->assertSame(15, $navegador->script("return Alpine.\$data(document.querySelector('.mesa')).vista.puntosParaGanar")[0]);
        });
    }
}
