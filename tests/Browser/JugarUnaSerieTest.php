<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Una serie al mejor de tres contra el bot, en un navegador de verdad: se elige en la pantalla de modos,
 * se juega la primera partida hasta el final y de ahí se pasa a la segunda.
 *
 * Prueba lo que los tests del servidor no ven: que la casilla manda el pedido, que el final de la partida
 * dice cómo va la serie y ofrece la siguiente, y que la mesa siguiente muestra el marcador.
 */
class JugarUnaSerieTest extends DuskTestCase
{
    /** Cuántas veces se mira la mesa antes de dar la partida por trabada, y cada cuánto. */
    private const VUELTAS = 1500;

    private const PAUSA = 150;

    public function test_la_primera_partida_de_una_serie_termina_y_de_su_final_se_pasa_a_la_segunda(): void
    {
        $this->browse(function (Browser $navegador) {
            $navegador->visit('/modos');
            $navegador->script("localStorage.setItem('vale-cuatro:ritmo', 'agil')");

            // La casilla de la serie y el botón de siempre.
            $navegador->script("[...document.querySelectorAll('[data-formato]')].find((casilla) => casilla.offsetParent !== null).click()");
            $navegador->press('Jugar contra el bot')
                ->waitForLocation('/mesa')
                ->waitFor('.mesa-mano button', 15)
                ->assertSeeIn('.mesa-barra', 'Serie 0 a 0');

            $primera = $this->mesa($navegador)['partida'];

            $this->perderLaPartida($navegador);

            // El final dice quién ganó la partida y cómo va la serie, y ofrece la que sigue: no la revancha.
            $navegador->waitForText('La serie va 0 a 1.', 10)
                ->assertSee('Ganó el bot')
                ->assertSeeLink('Jugar la siguiente partida')
                ->assertDontSee('Jugar la revancha');

            $navegador->clickLink('Jugar la siguiente partida');
            $navegador->waitUsing(20, self::PAUSA, fn () => ($this->mesa($navegador)['partida'] ?? $primera) !== $primera);

            // La segunda ya estaba repartida, y la barra muestra el marcador de la serie.
            $navegador->waitFor('.mesa-mano button', 15)->assertSeeIn('.mesa-barra', 'Serie 0 a 1');
            $this->assertSame(0, $this->mesa($navegador)['puntos']);
        });
    }

    /**
     * Pierde la partida de la manera más corta: se va al mazo en cada mano y reparte la siguiente.
     * No depende de las cartas que toquen: con cada mazo el bot suma, hasta llegar a 30.
     */
    private function perderLaPartida(Browser $navegador): void
    {
        for ($vuelta = 0; $vuelta < self::VUELTAS; $vuelta++) {
            $mesa = $this->mesa($navegador);

            if ($mesa['fin'] !== null) {
                $this->assertSame('rival', $mesa['fin']);

                return;
            }

            // Mientras la mesa cuenta una jugada (o juega el bot) no hay nada que tocar.
            if (! $mesa['ocupada']) {
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
     * Toca un botón de la barra de cantos, si está: entre mirar la mesa y tocar pueden pasar cosas.
     */
    private function tocar(Browser $navegador, string $boton): void
    {
        $navegador->script("document.querySelector('[data-boton=\"{$boton}\"]')?.click()");
    }

    /**
     * Cómo está la mesa, leído del componente de la pantalla. Vacío mientras la página está cambiando.
     *
     * @return array{partida?: int, ocupada?: bool, fase?: string, acciones?: list<string>, puntos?: int, fin?: string|null}
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
                partida: mesa.vista.partida,
                ocupada: mesa.ocupada,
                fase: mesa.vista.fase,
                acciones: mesa.vista.acciones.map((accion) => accion.tipo),
                puntos: mesa.puntos.vos + mesa.puntos.rival,
                fin: mesa.fin,
            };
        JS)[0];
    }
}
