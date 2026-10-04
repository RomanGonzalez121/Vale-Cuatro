<?php

namespace Tests\Feature;

use App\Models\Jugador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InvitadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_jugar_sin_sesion_crea_un_invitado_y_lleva_a_la_mesa(): void
    {
        // Sin ningún dato: apretar el botón alcanza.
        $this->post('/jugar')->assertRedirect('/mesa');

        $invitado = Jugador::sole();

        $this->assertTrue($invitado->esInvitado());
        $this->assertMatchesRegularExpression('/^Invitado \d{5}$/', $invitado->apodo);
        $this->assertAuthenticatedAs($invitado);

        $this->get('/mesa')->assertOk();
    }

    public function test_el_boton_de_la_portada_no_pide_ningun_dato(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // "Jugar" también está en el menú: se busca el formulario del botón grande.
        preg_match_all('/<form[^>]*action="[^"]*\/jugar"[^>]*>(.*?)<\/form>/s', $html, $formularios);

        $formulario = collect($formularios[1])->first(fn (string $contenido) => str_contains($contenido, 'Jugar contra el bot'));

        $this->assertNotNull($formulario, 'La portada no tiene el botón de jugar contra el bot.');

        // El único campo es el de seguridad que agrega Laravel, y va oculto.
        preg_match_all('/<(?:input|select|textarea)\b[^>]*>/', $formulario, $campos);

        $this->assertCount(1, $campos[0]);
        $this->assertStringContainsString('type="hidden"', $campos[0][0]);
    }

    public function test_el_invitado_que_vuelve_a_jugar_sigue_siendo_el_mismo(): void
    {
        $this->post('/jugar');
        $this->post('/jugar')->assertRedirect('/mesa');

        $this->assertSame(1, Jugador::count());
    }

    public function test_quien_tiene_cuenta_juega_con_su_cuenta(): void
    {
        $jugador = Jugador::factory()->create();

        $this->actingAs($jugador)->post('/jugar')->assertRedirect('/mesa');

        $this->assertSame(1, Jugador::count());
        $this->assertAuthenticatedAs($jugador);
    }

    public function test_la_mesa_sin_jugador_vuelve_a_la_portada(): void
    {
        $this->get('/mesa')->assertRedirect('/');
    }

    public function test_ninguna_pantalla_publica_lleva_a_la_mesa_con_un_link(): void
    {
        // A la mesa se entra con el botón de jugar, que crea el invitado. Un link
        // directo dejaría al visitante sin sesión rebotando en la portada.
        foreach (['/', '/ranking', '/historial', '/como-se-juega', '/identidad', '/ingresar', '/registro'] as $ruta) {
            $this->assertStringNotContainsString('href="'.route('mesa').'"', $this->get($ruta)->getContent(), "{$ruta} tiene un link a la mesa.");
        }
    }

    public function test_el_invitado_que_se_registra_o_ingresa_deja_de_estar_recordado(): void
    {
        $recordarme = Auth::getRecallerName();

        // Al entrar como invitado queda guardada la cookie de "recordarme". Los
        // tests no la reenvían solos como un navegador: se la pasa a mano.
        $cookie = $this->post('/jugar')->assertCookieNotExpired($recordarme)->getCookie($recordarme);
        $this->withCookie($recordarme, $cookie->getValue());

        $this->post('/registro', ['apodo' => 'El Tano', 'email' => 'tano@example.com', 'password' => 'una-clave-larga'])
            ->assertCookieExpired($recordarme);

        $this->post('/salir');
        $this->post('/jugar');

        $this->post('/ingresar', ['email' => 'tano@example.com', 'password' => 'una-clave-larga'])
            ->assertCookieExpired($recordarme);
    }

    public function test_quien_pide_que_lo_recuerden_queda_recordado(): void
    {
        $jugador = Jugador::factory()->create();

        $this->post('/ingresar', ['email' => $jugador->email, 'password' => 'password', 'recordarme' => '1'])
            ->assertCookieNotExpired(Auth::getRecallerName());
    }

    public function test_dos_invitados_no_reciben_el_mismo_apodo(): void
    {
        $apodos = collect(range(1, 40))->map(fn () => Jugador::invitado()->apodo);

        $this->assertCount(40, $apodos->unique());
    }

    public function test_la_limpieza_borra_solo_a_los_invitados_que_no_volvieron(): void
    {
        $viejo = Jugador::factory()->invitado()->create(['updated_at' => now()->subDays(Jugador::DIAS_DE_INVITADO + 1)]);
        $reciente = Jugador::factory()->invitado()->create(['updated_at' => now()->subDays(Jugador::DIAS_DE_INVITADO - 1)]);
        $cuentaVieja = Jugador::factory()->create(['updated_at' => now()->subYear()]);

        $this->artisan('model:prune', ['--model' => [Jugador::class]])->assertSuccessful();

        $this->assertModelMissing($viejo);
        $this->assertModelExists($reciente);
        $this->assertModelExists($cuentaVieja);
    }

    public function test_volver_a_jugar_le_renueva_el_plazo_al_invitado(): void
    {
        $invitado = Jugador::factory()->invitado()->create(['updated_at' => now()->subDays(Jugador::DIAS_DE_INVITADO + 1)]);

        $this->actingAs($invitado)->post('/jugar');
        $this->artisan('model:prune', ['--model' => [Jugador::class]]);

        $this->assertModelExists($invitado);
    }

    public function test_un_formulario_vencido_vuelve_atras_con_un_aviso(): void
    {
        // Laravel no revisa el campo de seguridad durante los tests, así que el
        // error 419 se provoca a mano: es el que da una página que venció.
        Route::post('/vencida', fn () => abort(419))->middleware('web');

        $this->from('/')->post('/vencida')
            ->assertRedirect('/')
            ->assertSessionHas('aviso', 'La página había vencido. Probá de nuevo.');
    }
}
