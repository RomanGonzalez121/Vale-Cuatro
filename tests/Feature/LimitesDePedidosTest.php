<?php

namespace Tests\Feature;

use App\Models\Jugador;
use App\Models\Partida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los límites de pedidos por minuto. Cada grupo de rutas lleva su propia cuenta: lo que la mesa pregunta
 * mientras se juega no puede dejar a nadie sin poder empezar otra partida.
 */
class LimitesDePedidosTest extends TestCase
{
    use RefreshDatabase;

    public function test_lo_que_pregunta_la_mesa_no_gasta_el_limite_de_jugar_ni_el_de_invitar(): void
    {
        $jugador = Jugador::factory()->create();
        $this->actingAs($jugador)->post('/jugar')->assertRedirect(route('mesa'));
        $partida = Partida::query()->sole();

        // Una mesa contra el bot pregunta más de una vez por segundo: en un minuto son muchas más de diez.
        for ($veces = 0; $veces < 30; $veces++) {
            $this->actingAs($jugador)->getJson("/mesa/estado?partida={$partida->id}&desde=1")->assertOk();
            $this->actingAs($jugador)->getJson("/revancha?partida={$partida->id}")->assertOk();
        }

        $this->actingAs($jugador)->post('/mesa/abandonar')->assertRedirect(route('modos'));

        // Enseguida después, empezar otra partida o abrir una sala tiene que andar.
        $this->actingAs($jugador)->post('/jugar')->assertRedirect(route('mesa'));
        $this->actingAs($jugador)->post('/mesa/abandonar')->assertRedirect(route('modos'));
        $this->actingAs($jugador)->post('/invitar')->assertRedirect();
    }

    public function test_entrar_a_jugar_sigue_teniendo_su_propio_limite(): void
    {
        $jugador = Jugador::factory()->create();

        for ($veces = 0; $veces < 10; $veces++) {
            $this->actingAs($jugador)->post('/jugar')->assertRedirect(route('mesa'));
        }

        $this->actingAs($jugador)->post('/jugar')->assertTooManyRequests();
        // Y es uno solo para las tres puertas de entrada.
        $this->actingAs($jugador)->post('/invitar')->assertTooManyRequests();

        // La mesa de la partida que ya tiene sigue andando.
        $this->actingAs($jugador)->get('/mesa')->assertOk();
        $this->actingAs($jugador)->getJson('/mesa/estado')->assertOk();
    }
}
