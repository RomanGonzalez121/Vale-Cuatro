<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * El link de un premio del final de la partida ("Probalo en tu colección"), en un navegador de verdad: abre la
 * Colección en la pestaña de esa pieza y la muestra en el probador.
 *
 * Subir de nivel desde una cuenta nueva pide varias partidas ganadas, y estas pruebas no tocan la base: el final con
 * las cartas se prueba en el servidor (PremiosALaVistaTest). Esta prueba lo que el servidor no ve: que la página
 * busca la pieza y abre el probador sola.
 */
class ProbarUnPremioTest extends DuskTestCase
{
    public function test_el_link_de_un_premio_abre_la_coleccion_en_esa_pieza(): void
    {
        $numero = random_int(1000, 9999);

        $this->browse(function (Browser $navegador) use ($numero) {
            $navegador->visit('/registro')
                ->type('apodo', "Premio {$numero}")
                ->type('email', "premio{$numero}@valecuatro.test")
                ->type('password', 'una-clave-larga-1')
                ->press('Crear cuenta')
                ->waitUntilMissing('input[name="email"]', 15);

            // La mesa de fábrica es de todos: se puede abrir sin haber ganado nada.
            $navegador->visit('/perfil?pieza=mesa:fabrica#coleccion')
                ->waitFor('#probador-nombre', 10)
                ->assertAttribute('#pestana-mesas', 'aria-selected', 'true')
                ->waitForTextIn('#probador-nombre', 'Mesa Paño', 10);

            $this->assertTrue($navegador->script("return document.querySelector('dialog:has(#probador-nombre)').open")[0]);
        });
    }
}
