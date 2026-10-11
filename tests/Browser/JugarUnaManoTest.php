<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Una mano entera contra el bot, jugada en un navegador de verdad tocando la pantalla.
 *
 * Los tests del motor prueban el reglamento y los de la mesa prueban el servidor. Este prueba lo que
 * ninguno de esos ve: que la pantalla muestra las cartas, que al tocarlas se juegan, que la mano se
 * cierra y que se puede repartir la siguiente.
 */
class JugarUnaManoTest extends DuskTestCase
{
    /** Cuántas veces se mira la mesa antes de dar la mano por trabada, y cada cuánto. */
    private const VUELTAS = 500;

    private const PAUSA = 150;

    public function test_se_juega_una_mano_entera_contra_el_bot_y_se_reparte_la_siguiente(): void
    {
        $this->browse(function (Browser $navegador) {
            $navegador->visit('/');

            // El ritmo ágil acorta las pausas para leer: la mano se juega igual, en menos tiempo.
            $navegador->script("localStorage.setItem('vale-cuatro:ritmo', 'agil')");

            $navegador->press('Jugar contra el bot')
                ->waitForLocation('/mesa')
                ->waitFor('.mesa-mano button', 15);

            $this->assertSame(1, $this->mesa($navegador)['mano']);

            $this->jugarHastaCerrarLaMano($navegador);

            // La mano se cerró: alguien sumó, y la barra de cantos ofrece repartir.
            $cerrada = $this->mesa($navegador);

            $this->assertSame('por_repartir', $cerrada['fase']);
            $this->assertGreaterThan(0, $cerrada['puntos']);
            $navegador->assertVisible('[data-boton="repartir"]');

            // Y se reparte la que sigue: otra vez tres cartas en la mano.
            $navegador->click('[data-boton="repartir"]');
            $navegador->waitUsing(20, self::PAUSA, fn () => $this->mesa($navegador)['mano'] === 2 && $this->mesa($navegador)['fase'] === 'jugando');

            $navegador->waitUsing(20, self::PAUSA, fn () => count($navegador->elements('.mesa-mano button')) === 3);

            $this->assertQueNoQuedoGuardadaNingunaPartida($navegador);
        });
    }

    /**
     * Después de jugar, lo que el service worker dejó guardado en el navegador es solo lo estático (lo
     * compilado, los íconos) y la página de "sin conexión". Ni la mesa, ni sus pedidos, ni nada con cartas.
     */
    private function assertQueNoQuedoGuardadaNingunaPartida(Browser $navegador): void
    {
        // Con Vite sirviendo los archivos (en desarrollo) el service worker no se registra: no hay nada que mirar.
        if ($navegador->script("return document.querySelector('meta[name=version]').content")[0] === 'desarrollo') {
            return;
        }

        $guardado = $navegador->driver->executeAsyncScript(<<<'JS'
            const listo = arguments[arguments.length - 1];

            (async () => {
                await navigator.serviceWorker.ready;

                const lista = [];

                for (const nombre of await caches.keys()) {
                    const guardado = await caches.open(nombre);

                    for (const pedido of await guardado.keys()) {
                        const respuesta = await guardado.match(pedido);

                        lista.push([new URL(pedido.url).pathname, (respuesta.headers.get('content-type') || '').split(';')[0], await respuesta.clone().text()]);
                    }
                }

                listo(lista);
            })();
        JS);

        $rutas = array_column($guardado, 0);

        $this->assertContains('/sin-conexion', $rutas, 'El service worker no guardó la página de sin conexión.');

        foreach ($guardado as [$ruta, $tipo, $contenido]) {
            $this->assertMatchesRegularExpression('#^/(build/assets/|iconos/|favicon\.(svg|ico)$|manifest\.json$|sin-conexion$)#', $ruta, "Quedó guardado {$ruta}.");

            // La única página guardada es la de sin conexión, y no trae ninguna mesa.
            if ($tipo === 'text/html') {
                $this->assertSame('/sin-conexion', $ruta);
                $this->assertStringNotContainsString('x-data="mesa(', $contenido);
                $this->assertStringNotContainsString('misCartas', $contenido);
            }

            // Y nada de lo guardado es una respuesta de la mesa.
            if ($tipo === 'application/json') {
                $this->assertSame('/manifest.json', $ruta);
            }
        }
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

            // Mientras la mesa cuenta una jugada (o juega el bot) no hay nada que tocar.
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
     * Toca lo primero que coincida, si está. Entre mirar la mesa y tocar pueden pasar cosas (el bot
     * juega, una carta se va): si justo no está, se mira de nuevo en la vuelta siguiente.
     */
    private function tocar(Browser $navegador, string $selector): void
    {
        $navegador->script("document.querySelector('{$selector}')?.click()");
    }

    /**
     * Cómo está la mesa, leído del componente de la pantalla.
     *
     * @return array{ocupada: bool, fase: string, mano: int, acciones: list<string>, puntos: int}
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
            };
        JS)[0];
    }
}
