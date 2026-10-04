<?php

namespace Tests\Feature;

use App\Models\Jugador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngresoTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pantalla_de_ingreso_responde(): void
    {
        $this->get('/ingresar')->assertOk()->assertSee('Ingresá');
    }

    public function test_se_ingresa_con_email_y_contrasena(): void
    {
        $jugador = Jugador::factory()->create();

        $this->post('/ingresar', ['email' => $jugador->email, 'password' => 'password'])->assertRedirect('/');

        $this->assertAuthenticatedAs($jugador);
    }

    public function test_el_email_se_acepta_con_mayusculas(): void
    {
        $jugador = Jugador::factory()->create(['email' => 'tano@example.com']);

        $this->post('/ingresar', ['email' => 'Tano@Example.com', 'password' => 'password']);

        $this->assertAuthenticatedAs($jugador);
    }

    public function test_una_contrasena_incorrecta_no_abre_la_sesion(): void
    {
        $jugador = Jugador::factory()->create();

        $this->post('/ingresar', ['email' => $jugador->email, 'password' => 'otra-clave'])
            ->assertSessionHasErrors(['email' => 'El email o la contraseña no coinciden.']);

        $this->assertGuest();
    }

    public function test_un_email_desconocido_da_el_mismo_error_que_una_contrasena_incorrecta(): void
    {
        $this->post('/ingresar', ['email' => 'nadie@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'El email o la contraseña no coinciden.']);
    }

    public function test_despues_de_cinco_intentos_fallidos_hay_que_esperar(): void
    {
        // Con el reloj quieto, la espera que se informa es siempre el minuto entero.
        $this->freezeTime();
        $jugador = Jugador::factory()->create();

        for ($intento = 1; $intento <= 5; $intento++) {
            $this->post('/ingresar', ['email' => $jugador->email, 'password' => 'otra-clave']);
        }

        // El sexto se rechaza aunque la contraseña sea la correcta.
        $this->post('/ingresar', ['email' => $jugador->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Demasiados intentos. Probá de nuevo en 60 segundos.']);

        $this->assertGuest();
    }

    public function test_un_invitado_no_tiene_con_que_ingresar(): void
    {
        Jugador::factory()->invitado()->create();

        $this->post('/ingresar', ['email' => '', 'password' => ''])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_el_invitado_puede_ingresar_a_su_cuenta(): void
    {
        $cuenta = Jugador::factory()->create();

        $this->actingAs(Jugador::factory()->invitado()->create())
            ->post('/ingresar', ['email' => $cuenta->email, 'password' => 'password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($cuenta);
    }

    public function test_salir_cierra_la_sesion(): void
    {
        $this->actingAs(Jugador::factory()->create())->post('/salir')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_quien_ya_ingreso_no_ve_la_pantalla_de_ingreso(): void
    {
        $this->actingAs(Jugador::factory()->create())->get('/ingresar')->assertRedirect('/perfil');
    }

    public function test_el_encabezado_muestra_ingresar_o_el_apodo(): void
    {
        $this->get('/ranking')->assertSee('Ingresar');

        $this->actingAs(Jugador::factory()->create(['apodo' => 'El Tano']))
            ->get('/ranking')
            ->assertSee('El Tano')
            ->assertDontSee('Ingresar');
    }
}
