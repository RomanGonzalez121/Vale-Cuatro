<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Una parada de la gira jugada en un navegador de verdad, del plano a la mesa y de vuelta al plano.
 *
 * Los tests del servidor prueban el candado, los fósforos y el ambiente de cada parada. Este prueba lo que
 * ellos no ven: que el plano deja elegir una parada y dice por qué otra está cerrada, que la mesa abre con la
 * mesa del bar y el nombre del rival, que "Jugar con mis cosas" la cambia, y que al perder la parada el final
 * lo dice, ofrece probar de nuevo (no seguir) y el plano la sigue mostrando como la que sigue.
 *
 * Se pierde a propósito: irse al mazo en cada mano termina igual con cualquier reparto. Ganarla dependería
 * de las cartas que toquen.
 */
class JugarUnaParadaDeLaGiraTest extends DuskTestCase
{
    /** Cuántas veces se mira la mesa antes de dar la partida por trabada, y cada cuánto. */
    private const VUELTAS = 1500;

    private const PAUSA = 150;

    public function test_se_juega_la_primera_parada_de_boedo_con_la_mesa_del_bar_y_al_perderla_se_vuelve_al_plano(): void
    {
        $this->browse(function (Browser $navegador) {
            // Quien entra por primera vez va a Boedo, con la primera parada elegida y la segunda cerrada.
            $navegador->visit('/gira')
                ->waitForLocation('/gira/boedo')
                ->assertSee('La gira de los clubes')
                ->assertSee('Llevás 0 fósforos de');

            $this->assertEquals(['sigue' => '1', 'primera' => 'sigue', 'segunda' => 'cerrada'], $this->plano($navegador));

            // Elegir la segunda muestra por qué no se juega, sin botón; la primera vuelve a ofrecer "Jugar".
            $this->elegir($navegador, 2);
            $navegador->waitForText('Se abre al pasar la parada 1.', 5);
            $this->assertFalse($this->hayBoton($navegador, 2));

            $this->elegir($navegador, 1);
            $navegador->waitUntil('[...document.querySelectorAll(\'form[action$="/gira/boedo/1"] button\')].some((boton) => boton.offsetParent !== null)', 5);

            // Con el ritmo ágil la prueba no espera las pausas de lectura.
            $navegador->script("localStorage.setItem('vale-cuatro:ritmo', 'agil')");
            $this->tocarElVisible($navegador, 'form[action$="/gira/boedo/1"] button');

            // La mesa es la del bar de Boedo, contra alguien del club con nombre, y la barra dice qué parada es.
            $navegador->waitForLocation('/mesa', 15)
                ->waitFor('.mesa-mano button', 15)
                ->assertSeeIn('.mesa-barra', 'Parada 1')
                ->assertSeeIn('.mesa-barra', 'Pichón Ibarra')
                ->assertPresent('.mesa.mesa-de-madera');

            $this->assertSame(15, $navegador->script("return Alpine.\$data(document.querySelector('.mesa')).vista.puntosParaGanar")[0]);

            // "Jugar con mis cosas" vuelve a dibujar la mesa con la propia (la de fábrica), y se puede deshacer.
            $this->misCosas($navegador);
            $navegador->assertMissing('.mesa.mesa-de-madera')
                ->assertAttribute('[data-boton-mis-cosas]', 'aria-checked', 'true');

            $this->misCosas($navegador);
            $navegador->assertPresent('.mesa.mesa-de-madera')
                ->assertAttribute('[data-boton-mis-cosas]', 'aria-checked', 'false');

            $this->perderLaPartida($navegador);

            // El final lo dice y ofrece probar de nuevo, no seguir a la parada siguiente.
            $navegador->waitForText('La parada 1 no salió esta vez.', 10)
                ->waitForText('Probar de nuevo', 10)
                ->assertDontSee('Seguir a la parada 2')
                ->clickLink('Ver el plano')
                ->waitForLocation('/gira/boedo')
                ->assertSee('Llevás 0 fósforos de');

            $this->assertEquals(['sigue' => '1', 'primera' => 'sigue', 'segunda' => 'cerrada'], $this->plano($navegador));
        });
    }

    /**
     * Pierde la partida de la manera más corta: se va al mazo en cada mano y reparte la siguiente.
     */
    private function perderLaPartida(Browser $navegador): void
    {
        for ($vuelta = 0; $vuelta < self::VUELTAS; $vuelta++) {
            $mesa = $this->mesa($navegador);

            if (($mesa['fin'] ?? null) !== null) {
                $this->assertSame('rival', $mesa['fin']);

                return;
            }

            // Mientras la mesa cuenta una jugada (o juega el bot) no hay nada que tocar.
            if (isset($mesa['ocupada']) && ! $mesa['ocupada']) {
                if ($mesa['fase'] === 'por_repartir') {
                    $this->tocar($navegador, 'repartir');
                } elseif (in_array('mazo', $mesa['acciones'], true)) {
                    $this->tocar($navegador, 'mazo');
                }
            }

            $navegador->pause(self::PAUSA);
        }

        $this->fail('La partida no terminó: '.json_encode($this->mesa($navegador)));
    }

    /**
     * Prende o apaga "Jugar con mis cosas": es un formulario, así que la mesa se vuelve a cargar entera.
     */
    private function misCosas(Browser $navegador): void
    {
        $navegador->script("document.querySelector('.mesa').dataset.antes = '1'; document.querySelector('[data-boton-mis-cosas]').click()");
        $navegador->waitUsing(15, self::PAUSA, fn () => $navegador->script("const mesa = document.querySelector('.mesa'); return mesa !== null && mesa.dataset.antes === undefined && window.Alpine !== undefined")[0]);
        $navegador->waitFor('.mesa-mano button', 15);
    }

    /**
     * Toca un botón de la barra de cantos, si está: entre mirar la mesa y tocar pueden pasar cosas.
     */
    private function tocar(Browser $navegador, string $boton): void
    {
        $navegador->script("document.querySelector('[data-boton=\"{$boton}\"]')?.click()");
    }

    /**
     * Toca la parada en el plano que se ve (en escritorio el acostado; el parado queda escondido).
     */
    private function elegir(Browser $navegador, int $parada): void
    {
        $navegador->script("[...document.querySelectorAll('svg.plano .parada[data-parada=\"{$parada}\"]')].find((parada) => parada.getClientRects().length > 0).dispatchEvent(new MouseEvent('click', { bubbles: true }))");
    }

    /**
     * Si la ficha de esa parada que se ve tiene su botón para jugarla.
     */
    private function hayBoton(Browser $navegador, int $parada): bool
    {
        return $navegador->script("return [...document.querySelectorAll('form[action$=\"/gira/boedo/{$parada}\"] button')].some((boton) => boton.offsetParent !== null)")[0];
    }

    private function tocarElVisible(Browser $navegador, string $selector): void
    {
        $navegador->script('[...document.querySelectorAll('.json_encode($selector).')].find((boton) => boton.offsetParent !== null).click()');
    }

    /**
     * Cómo quedó el plano que se ve: qué parada sigue y en qué estado están las dos primeras.
     *
     * @return array{sigue: string, primera: string, segunda: string}
     */
    private function plano(Browser $navegador): array
    {
        return $navegador->script(<<<'JS'
            const plano = [...document.querySelectorAll('svg.plano[data-barrio="boedo"]')].find((svg) => svg.getClientRects().length > 0);

            return {
                sigue: plano.dataset.sigue,
                primera: plano.querySelector('.parada[data-parada="1"]').dataset.estado,
                segunda: plano.querySelector('.parada[data-parada="2"]').dataset.estado,
            };
        JS)[0];
    }

    /**
     * Cómo está la mesa, leído del componente de la pantalla. Vacío mientras la página está cambiando.
     *
     * @return array{ocupada?: bool, fase?: string, acciones?: list<string>, fin?: string|null}
     */
    private function mesa(Browser $navegador): array
    {
        return $navegador->script(<<<'JS'
            const raiz = document.querySelector('.mesa');

            if (! raiz || ! window.Alpine) {
                return {};
            }

            const mesa = Alpine.$data(raiz);

            return {
                ocupada: mesa.ocupada,
                fase: mesa.vista.fase,
                acciones: mesa.vista.acciones.map((accion) => accion.tipo),
                fin: mesa.fin,
            };
        JS)[0];
    }
}
