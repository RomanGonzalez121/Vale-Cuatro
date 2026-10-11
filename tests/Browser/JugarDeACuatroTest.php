<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Una mano de truco de a cuatro contra bots, jugada en un navegador de verdad tocando la pantalla.
 *
 * Prueba lo que no ven los tests del servidor: que la mesa en cruz reparte a los cuatro, que la seña se
 * hace desde su ventanita, que los tres bots juegan solos, que la mano se cierra y que se reparte la siguiente.
 */
class JugarDeACuatroTest extends DuskTestCase
{
    /** Cuántas veces se mira la mesa antes de dar la mano por trabada, y cada cuánto. */
    private const VUELTAS = 700;

    private const PAUSA = 150;

    public function test_se_juega_una_mano_de_a_cuatro_con_una_sena_y_se_reparte_la_siguiente(): void
    {
        $this->browse(function (Browser $navegador) {
            $navegador->visit('/modos');

            // El ritmo ágil acorta las pausas para leer: la mano se juega igual, en menos tiempo.
            $navegador->script("localStorage.setItem('vale-cuatro:ritmo', 'agil')");

            // La carta del de a cuatro, que se busca por su dibujo y no por su lugar en la mano.
            $navegador->waitFor('.naipe-juego')->script("document.querySelector('.naipe-juego:has([data-modo=de-a-cuatro])').click()");
            $navegador->waitForText('Jugar de a cuatro')
                ->press('Jugar de a cuatro')
                ->waitForLocation('/mesa')
                ->waitFor('.mesa-cuatro .mesa-mano button', 15);

            $navegador->waitUsing(20, self::PAUSA, fn () => ! $this->mesa($navegador)['ocupada']);

            $alEmpezar = $this->mesa($navegador);

            $this->assertSame(1, $alEmpezar['mano']);
            // Cada uno de los otros tres tiene sus cartas boca abajo, y su compañero ya le hizo sus señas.
            $this->assertNotSame([], $alEmpezar['delCompanero']);
            $this->assertSame(3, count($navegador->elements('[data-dorsos]')));

            // Una seña: se abre la ventanita, se toca la primera que se puede hacer y queda anotada.
            $navegador->press('Señas')->waitFor('#senas-de-la-mesa button.sena:not([disabled])');
            $navegador->script("document.querySelector('#senas-de-la-mesa button.sena:not([disabled])').click()");
            $navegador->waitUsing(10, self::PAUSA, fn () => count($this->mesa($navegador)['hechas']) === 1);
            // El mismo botón la cierra.
            $navegador->press('Señas')->waitUntilMissing('#senas-de-la-mesa button.sena');

            $this->jugarHastaCerrarLaMano($navegador);

            // La mano se cerró: alguna pareja sumó, y la barra de cantos ofrece repartir.
            $cerrada = $this->mesa($navegador);

            $this->assertSame('por_repartir', $cerrada['fase']);
            $this->assertGreaterThan(0, $cerrada['puntos']);
            $navegador->assertVisible('[data-boton="repartir"]');

            // Y se reparte la que sigue: otra vez tres cartas en la mano.
            $navegador->click('[data-boton="repartir"]');
            $navegador->waitUsing(20, self::PAUSA, fn () => $this->mesa($navegador)['mano'] === 2 && $this->mesa($navegador)['fase'] === 'jugando');
            $navegador->waitUsing(20, self::PAUSA, fn () => count($navegador->elements('.mesa-mano button')) === 3);
        });
    }

    /**
     * Juega como jugaría alguien que no quiere nada: tira cartas y a cada canto contesta "no quiero".
     * Así la mano se cierra siempre y la partida no puede terminarse en la primera mano.
     */
    private function jugarHastaCerrarLaMano(Browser $navegador): void
    {
        for ($vuelta = 0; $vuelta < self::VUELTAS; $vuelta++) {
            $mesa = $this->mesa($navegador);

            if ($mesa['fase'] === 'por_repartir' && ! $mesa['ocupada']) {
                return;
            }

            // Mientras la mesa cuenta una jugada (o juegan los bots) no hay nada que tocar.
            if (! $mesa['ocupada']) {
                if (in_array('no_quiero', $mesa['acciones'], true)) {
                    $this->tocar($navegador, '[data-boton="no_quiero"]');
                } elseif (in_array('jugar', $mesa['acciones'], true)) {
                    $this->tocar($navegador, '.mesa-mano button');
                }
            }

            $navegador->pause(self::PAUSA);
        }

        $this->fail('La mano no se cerró: '.json_encode($this->mesa($navegador)));
    }

    /**
     * Toca lo primero que coincida, si está. Entre mirar la mesa y tocar pueden pasar cosas (un bot
     * juega, una carta se va): si justo no está, se mira de nuevo en la vuelta siguiente.
     */
    private function tocar(Browser $navegador, string $selector): void
    {
        $navegador->script("document.querySelector('{$selector}')?.click()");
    }

    /**
     * Cómo está la mesa, leído del componente de la pantalla.
     *
     * @return array{ocupada: bool, fase: string, mano: int, acciones: list<string>, puntos: int, delCompanero: list<string>, hechas: list<string>}
     */
    private function mesa(Browser $navegador): array
    {
        return $navegador->script(<<<'JS'
            const mesa = Alpine.$data(document.querySelector('.mesa'));

            return {
                ocupada: mesa.ocupada,
                fase: mesa.vista.fase,
                mano: mesa.vista.numeroDeMano,
                acciones: mesa.vista.acciones.map((accion) => accion.tipo),
                puntos: mesa.puntos.vos + mesa.puntos.rival,
                delCompanero: mesa.vista.senas?.companero ?? [],
                hechas: mesa.vista.senas?.hechas ?? [],
            };
        JS)[0];
    }
}
