<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Un desafío jugado en un navegador de verdad, de la lista a la mesa y de vuelta.
 *
 * Los tests del servidor prueban que el objetivo se decide bien. Este prueba lo que ellos no ven: que
 * la mesa muestra el objetivo, que al cerrar la mano dice cómo salió en vez de ofrecer repartir, y que
 * la lista lo marca como superado.
 */
class JugarUnDesafioTest extends DuskTestCase
{
    private const PAUSA = 150;

    public function test_se_supera_un_desafio_y_queda_marcado_en_la_lista(): void
    {
        $this->browse(function (Browser $navegador) {
            $navegador->visit('/desafios')
                ->assertSee('de 8 superados');

            // Con el ritmo ágil la prueba no espera las pausas de lectura.
            $navegador->script("localStorage.setItem('vale-cuatro:ritmo', 'agil')");

            // "Parda la primera": el bot salió con el ancho falso. Se emparda con el otro ancho falso y se gana la segunda.
            $navegador->click('form[action$="/desafios/parda-la-primera"] button')
                ->waitForLocation('/mesa')
                ->waitFor('.mesa-mano button[data-carta="1-copa"]', 15);

            // El objetivo se muestra solo al llegar, y se corre con la primera jugada.
            $navegador->waitFor('#objetivo-de-la-mesa', 5)
                ->assertSeeIn('#objetivo-de-la-mesa', 'Parda la primera')
                ->assertSeeIn('#objetivo-de-la-mesa', 'Ganá la mano');

            $this->jugar($navegador, '1-copa');
            $navegador->waitUntilMissing('#objetivo-de-la-mesa', 5);
            $this->jugar($navegador, '3-copa');

            // La mano cerró y con ella el desafío: no se reparte, se dice cómo salió y qué sigue.
            $navegador->waitUsing(20, self::PAUSA, fn () => $this->mesa($navegador)['cerro']);

            $this->assertTrue($this->mesa($navegador)['superado']);
            $navegador->assertSee('Desafío superado')
                ->assertMissing('[data-boton="repartir"]')
                ->assertVisible('[data-boton="reintentar"]')
                ->click('[data-boton="volver-a-la-lista"]')
                ->waitForLocation('/desafios')
                ->assertSee('Jugar de nuevo');

            $this->assertSame('1', trim($navegador->text('header p.sobre-pano span')));
        });
    }

    /**
     * Toca esa carta de la mano cuando la mesa está lista para recibirla.
     */
    private function jugar(Browser $navegador, string $carta): void
    {
        $navegador->waitUsing(20, self::PAUSA, fn () => $this->mesa($navegador)['puedeJugar']);
        $navegador->script("document.querySelector('.mesa-mano button[data-carta=\"{$carta}\"]').click()");
    }

    /**
     * @return array{puedeJugar: bool, cerro: bool, superado: bool|null}
     */
    private function mesa(Browser $navegador): array
    {
        return $navegador->script(<<<'JS'
            const mesa = Alpine.$data(document.querySelector('.mesa'));

            return {
                puedeJugar: ! mesa.ocupada && mesa.vista.acciones.some((accion) => accion.tipo === 'jugar'),
                cerro: mesa.cerroElDesafio,
                superado: mesa.superado,
            };
        JS)[0];
    }
}
