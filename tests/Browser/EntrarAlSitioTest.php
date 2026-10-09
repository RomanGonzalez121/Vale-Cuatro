<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Las puertas de entrada, en un navegador de verdad: jugar sin cuenta con un solo botón, registrarse,
 * salir y volver a ingresar.
 */
class EntrarAlSitioTest extends DuskTestCase
{
    public function test_quien_entra_sin_cuenta_llega_a_la_mesa_con_un_solo_boton(): void
    {
        $this->browse(function (Browser $navegador) {
            $navegador->visit('/')
                ->assertSee('Vale Cuatro')
                ->press('Jugar contra el bot')
                ->waitForLocation('/mesa')
                ->waitFor('.mesa-mano button', 15)
                // Sin formulario en el medio: ya hay tres cartas en la mano y un rival enfrente.
                ->assertPresent('.mesa')
                ->assertSee('Bot');

            $this->assertCount(3, $navegador->elements('.mesa-mano button'));
        });
    }

    public function test_se_puede_crear_una_cuenta_salir_y_volver_a_ingresar(): void
    {
        // Un apodo y un correo distintos en cada corrida: la base de estas pruebas puede no estar vacía.
        $numero = random_int(1000, 9999);
        $apodo = "Dusk {$numero}";
        $email = "dusk{$numero}@valecuatro.test";

        $this->browse(function (Browser $navegador) use ($apodo, $email) {
            $navegador->visit('/registro')
                ->type('apodo', $apodo)
                ->type('email', $email)
                ->type('password', 'una-clave-larga-1')
                ->press('Crear cuenta')
                ->waitUntilMissing('input[name="email"]', 15)
                // Con cuenta, el encabezado muestra el apodo en vez de "Ingresar".
                ->assertSee($apodo);

            // Salir, desde el perfil.
            $navegador->visit('/perfil')
                ->assertInputValue('apodo', $apodo)
                ->click('form[action$="/salir"] button')
                ->waitForLocation('/')
                ->assertDontSee($apodo);

            // Y volver a entrar con lo mismo.
            $navegador->visit('/ingresar')
                ->type('email', $email)
                ->type('password', 'una-clave-larga-1')
                ->press('Ingresar')
                ->waitUntilMissing('input[name="password"]', 15)
                ->assertSee($apodo);
        });
    }
}
