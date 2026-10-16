<?php

namespace Tests\Feature;

use App\Juego\Mazos;
use App\Juego\Mesa;
use App\Juego\Premios;
use App\Models\Jugador;
use App\View\Components\Carta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Los mazos de la tanda 5: se ganan por nivel, se eligen en la Colección y se ven solo en la mesa de quien los
 * eligió. Cada página trae solo las piezas del mazo que dibuja.
 */
class MazosTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_mazo_del_camino_esta_armado_con_piezas_que_existen(): void
    {
        $this->assertSame(array_keys(Mazos::TODOS), array_keys(Premios::POR_NIVEL['mazo']));

        foreach (Mazos::TODOS as $clave => $piezas) {
            $this->assertContains($piezas['tinta'], ['fabrica', 'noche', 'una'], $clave);
            $this->assertContains($piezas['palos'], ['fabrica', 'fosforo'], $clave);
            $this->assertContains($piezas['figuras'], ['fabrica', 'medio', 'entero'], $clave);
            $this->assertContains($piezas['marco'], ['fabrica', 'arco'], $clave);
        }

        // Ninguno repite al de otro: si no, serían el mismo premio dos veces.
        $this->assertSame(count(Mazos::TODOS), count(array_unique(array_map('serialize', Mazos::TODOS))));
    }

    public function test_un_mazo_no_se_usa_sin_haberlo_ganado(): void
    {
        $jugador = Jugador::factory()->create();
        $jugador->forceFill(['fosforos' => 8 * 10])->save();

        // En el nivel 9 llega el de noche; Retratos (nivel 22) todavía no, tampoco mandando el pedido a mano.
        $this->actingAs($jugador)->put('/perfil/mazo', ['mazo' => 'retratos'])->assertSessionHasErrors('mazo');
        $this->assertNull($jugador->fresh()->mazo);

        $this->actingAs($jugador)->put('/perfil/mazo', ['mazo' => 'noche'])->assertRedirect('/perfil#coleccion')->assertSessionHasNoErrors();
        $this->assertSame('noche', (new Premios)->elegidaDe('mazo', $jugador->fresh()));

        // Uno que no existe tampoco, y el de fábrica es de todos.
        $this->actingAs($jugador)->put('/perfil/mazo', ['mazo' => 'brillante'])->assertSessionHasErrors('mazo');
        $this->actingAs($jugador)->put('/perfil/mazo', ['mazo' => 'fabrica'])->assertSessionHasNoErrors();
        $this->assertSame('fabrica', (new Premios)->elegidaDe('mazo', $jugador->fresh()));
    }

    public function test_si_baja_de_nivel_vuelve_al_mazo_de_fabrica(): void
    {
        $jugador = Jugador::factory()->create();
        $jugador->forceFill(['fosforos' => 30 * 10, 'mazo' => 'imprenta'])->save();
        $this->assertSame('imprenta', (new Premios)->elegidaDe('mazo', $jugador));

        // Si un recálculo lo deja por debajo del nivel 29, lo elegido ya no vale.
        $jugador->forceFill(['fosforos' => 10 * 10])->save();
        $this->assertSame('fabrica', (new Premios)->elegidaDe('mazo', $jugador->fresh()));
    }

    public function test_la_administracion_tiene_todos_los_mazos(): void
    {
        $administracion = Jugador::factory()->administrador()->create();

        foreach (array_keys(Mazos::TODOS) as $mazo) {
            $this->actingAs($administracion)->put('/perfil/mazo', ['mazo' => $mazo])->assertSessionHasNoErrors();
            $this->assertSame($mazo, (new Premios)->elegidaDe('mazo', $administracion->fresh()));
        }
    }

    public function test_desde_el_probador_se_elige_sin_recargar(): void
    {
        $jugador = Jugador::factory()->create();
        $jugador->forceFill(['fosforos' => 20 * 10])->save();

        $this->actingAs($jugador)->putJson('/perfil/mazo', ['mazo' => 'palos-de-fosforo'])->assertOk()->assertJson(['mensaje' => 'Mazo elegido.']);
        $this->actingAs($jugador)->putJson('/perfil/mazo', ['mazo' => 'retratos'])->assertStatus(422);
        $this->assertSame('palos-de-fosforo', $jugador->fresh()->mazo);
    }

    public function test_la_mesa_sale_con_el_mazo_propio_y_las_demas_paginas_con_el_de_fabrica(): void
    {
        $jugador = Jugador::factory()->create();
        $jugador->forceFill(['fosforos' => 30 * 10, 'mazo' => 'fosforo-de-noche'])->save();

        $this->actingAs($jugador)->post('/jugar')->assertRedirect('/mesa');

        $mesa = $this->actingAs($jugador)->get('/mesa')->assertOk();
        $mesa->assertSee('mazo-tinta-noche')->assertSee('href="#palo-oro-fosforo"', false)
            ->assertSee('id="palo-oro-fosforo"', false)->assertSee('id="figura-rey-entero"', false)
            // Lo que no usa su mazo no viaja: ni las figuras de los retratos.
            ->assertDontSee('id="figura-rey-medio"', false);

        // Las demás páginas siguen con el mazo de fábrica y no traen piezas de otro.
        $this->actingAs($jugador)->get('/como-se-juega')->assertOk()
            ->assertDontSee('mazo-tinta-noche')->assertDontSee('id="palo-oro-fosforo"', false)->assertDontSee('id="figura-rey-entero"', false);
    }

    public function test_el_rival_no_recibe_el_mazo(): void
    {
        $uno = Jugador::factory()->create();
        $dos = Jugador::factory()->create();
        $dos->forceFill(['fosforos' => 30 * 10, 'mazo' => 'retratos'])->save();

        $mesa = $this->app->make(Mesa::class);
        $sala = $mesa->crearSala($uno);
        $mesa->sentarse($sala->codigo, $dos);

        $respuesta = $this->actingAs($uno)->get('/mesa')->assertOk();
        $this->assertArrayNotHasKey('mazo', $respuesta->viewData('socio'));
        $respuesta->assertDontSee('id="figura-rey-medio"', false)->assertDontSee('retratos');

        // Y ella ve sus cartas, también las del Tano, con su mazo.
        $this->actingAs($dos)->get('/mesa')->assertOk()->assertSee('id="figura-rey-medio"', false);
    }

    public function test_cada_carta_dibuja_las_piezas_de_su_mazo(): void
    {
        $fabrica = Blade::render('<x-carta palo="oro" :numero="12" mazo="fabrica" />');
        $this->assertStringContainsString('href="#figura-rey"', $fabrica);
        $this->assertStringContainsString('href="#palo-oro"', $fabrica);
        $this->assertStringNotContainsString('mazo-tinta', $fabrica);

        $imprenta = Blade::render('<x-carta palo="oro" :numero="12" mazo="imprenta" />');
        $this->assertStringContainsString('href="#figura-rey-medio"', $imprenta);
        $this->assertStringContainsString('mazo-tinta-una', $imprenta);

        $fosforo = Blade::render('<x-carta palo="basto" :numero="11" mazo="palos-de-fosforo" />');
        $this->assertStringContainsString('href="#figura-caballo-entero"', $fosforo);
        $this->assertStringContainsString('href="#palo-basto-fosforo"', $fosforo);

        // El arco va solo en las cartas de número de los mazos que lo llevan (las figuras traen el suyo).
        $this->assertStringContainsString(Carta::ARCO, Blade::render('<x-carta palo="copa" :numero="4" mazo="retratos" />'));
        $this->assertStringNotContainsString(Carta::ARCO, Blade::render('<x-carta palo="copa" :numero="4" mazo="palos-de-fosforo" />'));
        $this->assertStringNotContainsString(Carta::ARCO, Blade::render('<x-carta palo="copa" :numero="12" mazo="retratos" />'));
    }

    public function test_en_el_arco_cada_carta_de_numero_lleva_tantos_palos_como_vale_y_adentro_del_arco(): void
    {
        foreach (Carta::PALOS as $palo) {
            foreach ([1, 2, 3, 4, 5, 6, 7] as $numero) {
                $pintas = (new Carta($palo, $numero, 'retratos'))->pintas();
                $this->assertCount($numero, $pintas);

                foreach ($pintas as [$x, $y, $ancho, $alto]) {
                    // Entre los costados del arco (16 y 84) y su pie (128), y debajo de la curva arriba (24).
                    $this->assertGreaterThanOrEqual(16, $x, "{$numero} de {$palo}");
                    $this->assertLessThanOrEqual(84, $x + $ancho, "{$numero} de {$palo}");
                    $this->assertGreaterThanOrEqual(24, $y, "{$numero} de {$palo}");
                    $this->assertLessThanOrEqual(128, $y + $alto, "{$numero} de {$palo}");
                }
            }
        }
    }
}
