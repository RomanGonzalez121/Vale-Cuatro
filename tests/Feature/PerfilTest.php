<?php

namespace Tests\Feature;

use App\Models\Jugador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_sesion_el_perfil_manda_a_ingresar(): void
    {
        $this->get('/perfil')->assertRedirect('/ingresar');
    }

    public function test_despues_de_ingresar_se_vuelve_al_perfil_que_se_habia_pedido(): void
    {
        $jugador = Jugador::factory()->create();

        $this->get('/perfil');

        $this->post('/ingresar', ['email' => $jugador->email, 'password' => 'password'])->assertRedirect('/perfil');
    }

    public function test_al_invitado_el_perfil_lo_manda_a_crear_la_cuenta(): void
    {
        $invitado = Jugador::factory()->invitado()->create();

        $this->actingAs($invitado)->get('/perfil')->assertRedirect('/registro');
        $this->actingAs($invitado)->put('/perfil', ['apodo' => 'Colado'])->assertRedirect('/registro');

        $this->assertNotSame('Colado', $invitado->fresh()->apodo);
    }

    public function test_el_perfil_muestra_el_apodo_y_el_email(): void
    {
        $jugador = Jugador::factory()->create(['apodo' => 'El Tano', 'email' => 'tano@example.com']);

        $this->actingAs($jugador)->get('/perfil')->assertOk()->assertSee('El Tano')->assertSee('tano@example.com');
    }

    public function test_se_puede_cambiar_el_apodo(): void
    {
        $jugador = Jugador::factory()->create(['apodo' => 'El Tano']);

        $this->actingAs($jugador)->put('/perfil', ['apodo' => 'La Tana'])
            ->assertRedirect('/perfil')
            ->assertSessionHas('hecho', 'Apodo guardado.');

        $this->assertSame('La Tana', $jugador->fresh()->apodo);
    }

    public function test_guardar_el_mismo_apodo_no_choca_con_uno_mismo(): void
    {
        $jugador = Jugador::factory()->create(['apodo' => 'El Tano']);

        $this->actingAs($jugador)->put('/perfil', ['apodo' => 'El Tano'])->assertSessionHasNoErrors();
    }

    public function test_no_se_puede_tomar_el_apodo_de_otro(): void
    {
        Jugador::factory()->create(['apodo' => 'La Tana']);
        $jugador = Jugador::factory()->create(['apodo' => 'El Tano']);

        $this->actingAs($jugador)->put('/perfil', ['apodo' => 'La Tana'])->assertSessionHasErrors('apodo');

        $this->assertSame('El Tano', $jugador->fresh()->apodo);
    }

    public function test_un_apodo_invalido_no_se_guarda(): void
    {
        $jugador = Jugador::factory()->create(['apodo' => 'El Tano']);

        foreach (['', 'ab', str_repeat('a', 21), 'Invitado 9', 'con@arroba'] as $apodo) {
            $this->actingAs($jugador)->put('/perfil', ['apodo' => $apodo])->assertSessionHasErrors('apodo');
        }

        $this->assertSame('El Tano', $jugador->fresh()->apodo);
    }

    public function test_el_perfil_solo_cambia_el_apodo(): void
    {
        $jugador = Jugador::factory()->create(['email' => 'tano@example.com']);

        $this->actingAs($jugador)->put('/perfil', ['apodo' => 'La Tana', 'email' => 'otro@example.com', 'password' => 'colada-123']);

        $this->assertSame('tano@example.com', $jugador->fresh()->email);
        $this->assertSame($jugador->password, $jugador->fresh()->password);
    }
}
