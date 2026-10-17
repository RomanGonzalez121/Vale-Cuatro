<?php

namespace Tests\Feature;

use App\Juego\Mesa;
use App\Juego\Premios;
use App\Models\Jugador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * El ambiente de cada parada de la gira: la mesa del bar del barrio, y el mazo y el dorso de quien los trae.
 * Se impone en la mesa salvo que la persona juegue con sus cosas, y jugar con un premio no lo destraba.
 */
class AmbienteDeLaGiraTest extends TestCase
{
    use RefreshDatabase;

    private function sentarEn(Jugador $jugador, string $barrio, int $numero): void
    {
        Queue::fake();
        app(Mesa::class)->abrirEnGira($jugador, $barrio, $numero);
    }

    public function test_cada_barrio_se_juega_en_la_mesa_de_su_bar(): void
    {
        $jugador = Jugador::factory()->create();
        $this->sentarEn($jugador, 'san-telmo', 1);

        $this->actingAs($jugador)->get('/mesa')->assertOk()
            ->assertSee('mesa-de-adoquines')
            ->assertSee('Jugar con mis cosas')
            ->assertSee('Ahora, con las cosas del bar');

        $this->assertFalse((new Premios)->tiene('mesa', $jugador->fresh(), 'adoquines'), 'Jugar en la mesa del bar no la destraba.');
    }

    public function test_el_jefe_trae_su_mazo_y_su_dorso(): void
    {
        $jugador = Jugador::factory()->create();
        $this->sentarEn($jugador, 'san-telmo', 6);

        // Don Ceferino: mazo Catalán y dorso Grabado, en la mesa de adoquines.
        $this->actingAs($jugador)->get('/mesa')->assertOk()
            ->assertSee('mesa-de-adoquines')
            ->assertSee('id="dorso-grabado"', false)
            ->assertSee('catalan-papel', false);
    }

    public function test_un_rival_comun_puede_traer_lo_suyo(): void
    {
        $jugador = Jugador::factory()->create();
        $this->sentarEn($jugador, 'pompeya', 1);

        // Doña Elvira juega en su mesa bordada, con su mazo bordado.
        $this->actingAs($jugador)->get('/mesa')->assertOk()
            ->assertSee('mesa-de-bordado')
            ->assertSee('bordado-tela', false);
    }

    public function test_con_mis_cosas_se_juega_con_lo_propio(): void
    {
        $jugador = Jugador::factory()->create();
        $this->sentarEn($jugador, 'san-telmo', 6);

        $this->actingAs($jugador)->post('/mesa/mis-cosas')->assertRedirect(route('mesa'));
        $this->assertTrue($jugador->fresh()->con_mis_cosas);

        $this->get('/mesa')->assertOk()
            ->assertDontSee('mesa-de-adoquines')
            ->assertDontSee('id="dorso-grabado"', false)
            ->assertSee('Tu mesa, tu mazo y tu dorso');

        // Se vuelve a apagar con la misma llave.
        $this->post('/mesa/mis-cosas');
        $this->assertFalse($jugador->fresh()->con_mis_cosas);
    }

    public function test_fuera_de_la_gira_no_hay_ambiente_ni_llave(): void
    {
        Queue::fake();
        $jugador = Jugador::factory()->create();
        app(Mesa::class)->abrir($jugador);

        $this->actingAs($jugador)->get('/mesa')->assertOk()
            ->assertDontSee('Jugar con mis cosas')
            ->assertDontSee('mesa-de-adoquines');
    }

    public function test_la_ficha_del_jefe_dice_lo_que_trae(): void
    {
        $this->get('/gira/san-telmo')->assertOk()->assertSee('Trae su mazo Catalán y su dorso Grabado.');
    }
}
