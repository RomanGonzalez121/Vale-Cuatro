<?php

namespace Tests\Feature;

use App\Administracion\Credenciales;
use App\Console\Commands\CrearAdministrador;
use App\Models\Jugador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * La cuenta que administra el sitio la crea un comando de consola, con un email y una contraseña que
 * salen del entorno (en el sitio publicado, de dos variables cargadas en el panel del servicio).
 * Lo que diga el entorno es lo que vale: esa cuenta administra, y ninguna otra.
 */
class CuentaDeAdministracionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_email_o_sin_contrasena_nadie_administra_el_sitio(): void
    {
        foreach ([[null, null], ['admin@example.com', null], [null, 'una-clave-bien-larga'], ['', '']] as [$email, $password]) {
            $this->conCredenciales($email, $password);

            $this->artisan('administrador:crear')->assertSuccessful();
        }

        $this->assertSame(0, Jugador::query()->count());
    }

    public function test_el_comando_crea_la_cuenta_y_con_ella_se_entra_al_panel(): void
    {
        $this->conCredenciales('Admin@Example.com', 'una-clave-bien-larga');

        $this->artisan('administrador:crear')->expectsOutput('Cuenta de administración creada.')->assertSuccessful();

        $cuenta = Jugador::query()->sole();

        $this->assertSame('admin@example.com', $cuenta->email);
        $this->assertSame('Cantinero', $cuenta->apodo);
        $this->assertTrue($cuenta->esAdministrador());
        $this->assertFalse($cuenta->esInvitado());
        // La contraseña no queda guardada tal cual, ni en la configuración del sitio.
        $this->assertNotSame('una-clave-bien-larga', $cuenta->password);
        $this->assertStringNotContainsString('una-clave-bien-larga', json_encode(config()->all()));

        $this->post('/ingresar', ['email' => 'admin@example.com', 'password' => 'una-clave-bien-larga'])->assertRedirect(route('administracion'));
        $this->get('/administracion')->assertOk();
    }

    public function test_correrlo_de_nuevo_no_duplica_la_cuenta_ni_le_cierra_la_sesion(): void
    {
        config(['session.driver' => 'database']);
        $this->conCredenciales('admin@example.com', 'una-clave-bien-larga');
        $this->artisan('administrador:crear');

        $cuenta = Jugador::query()->sole();
        $recordada = $cuenta->getRememberToken();
        $guardada = $cuenta->password;
        DB::table('sessions')->insert(['id' => 'abierta', 'user_id' => $cuenta->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);

        // El contenedor lo corre en cada arranque: la cuenta tiene que quedar como estaba.
        $this->artisan('administrador:crear')->expectsOutput('La cuenta de administración ya estaba al día.')->assertSuccessful();

        $cuenta->refresh();

        $this->assertSame(1, Jugador::query()->count());
        $this->assertSame($guardada, $cuenta->password);
        $this->assertSame($recordada, $cuenta->getRememberToken());
        $this->assertSame(1, DB::table('sessions')->count());
    }

    public function test_si_alguien_ya_habia_registrado_ese_email_no_hereda_el_panel(): void
    {
        // Alguien se registró con ese email antes de que existiera la cuenta de administración, y dejó una sesión abierta.
        $ajeno = Jugador::factory()->create(['apodo' => 'Vivo', 'email' => 'admin@example.com', 'password' => 'la-clave-del-vivo']);
        $ajeno->setRememberToken('token-del-vivo');
        $ajeno->save();
        $otro = Jugador::factory()->create();

        config(['session.driver' => 'database']);
        $this->conCredenciales('admin@example.com', 'una-clave-bien-larga');
        DB::table('sessions')->insert([
            ['id' => 'del-vivo', 'user_id' => $ajeno->id, 'payload' => '', 'last_activity' => now()->getTimestamp()],
            ['id' => 'de-otro', 'user_id' => $otro->id, 'payload' => '', 'last_activity' => now()->getTimestamp()],
        ]);

        $this->artisan('administrador:crear')->assertSuccessful();

        $ajeno->refresh();

        // La cuenta pasa a ser la de administración, con la contraseña del entorno...
        $this->assertTrue($ajeno->esAdministrador());
        $this->assertTrue(Hash::check('una-clave-bien-larga', $ajeno->password));
        $this->assertFalse(Hash::check('la-clave-del-vivo', $ajeno->password));
        // ...y quien había entrado antes se queda afuera: ni su sesión ni su "recordarme" sirven más.
        $this->assertNotSame('token-del-vivo', $ajeno->getRememberToken());
        $this->assertSame(['de-otro'], DB::table('sessions')->pluck('id')->all());
    }

    public function test_si_las_sesiones_no_estan_en_la_base_no_se_toma_una_cuenta_que_ya_existia(): void
    {
        // Sin poder cerrarle la sesión a quien la tenga abierta, darle el rol a esa cuenta sería regalarle el panel.
        $ajeno = Jugador::factory()->create(['email' => 'admin@example.com', 'password' => 'la-clave-del-vivo']);

        config(['session.driver' => 'file']);
        $this->conCredenciales('admin@example.com', 'una-clave-bien-larga');

        $this->artisan('administrador:crear')->assertFailed();

        $ajeno->refresh();

        $this->assertFalse($ajeno->esAdministrador());
        $this->assertTrue(Hash::check('la-clave-del-vivo', $ajeno->password));
    }

    public function test_cambiar_la_contrasena_en_el_entorno_la_cambia_en_la_cuenta(): void
    {
        $this->conCredenciales('admin@example.com', 'una-clave-bien-larga');
        $this->artisan('administrador:crear');

        $this->conCredenciales('admin@example.com', 'otra-clave-bien-larga');
        $this->artisan('administrador:crear')->assertSuccessful();

        $cuenta = Jugador::query()->sole();

        $this->assertTrue(Hash::check('otra-clave-bien-larga', $cuenta->password));
        $this->assertTrue($cuenta->esAdministrador());
    }

    public function test_cambiar_el_email_le_saca_el_panel_a_la_cuenta_anterior(): void
    {
        config(['session.driver' => 'database']);
        $this->conCredenciales('vieja@example.com', 'una-clave-bien-larga');
        $this->artisan('administrador:crear');

        $vieja = Jugador::query()->sole();
        DB::table('sessions')->insert(['id' => 'de-la-vieja', 'user_id' => $vieja->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);

        // Se sospecha de la cuenta vieja: en el entorno se pone otro email y otra contraseña.
        $this->conCredenciales('nueva@example.com', 'otra-clave-bien-larga');
        $this->artisan('administrador:crear')->assertSuccessful();

        $this->assertFalse($vieja->fresh()->esAdministrador());
        $this->assertSame(0, DB::table('sessions')->count());
        $this->assertSame(['nueva@example.com'], Jugador::query()->where('es_administrador', true)->pluck('email')->all());

        // La vieja sigue existiendo como una cuenta común: para ella el panel ya no existe.
        $this->actingAs($vieja->fresh())->get('/administracion')->assertNotFound();
    }

    public function test_vaciar_el_entorno_deja_el_sitio_sin_nadie_que_lo_administre(): void
    {
        $this->conCredenciales('admin@example.com', 'una-clave-bien-larga');
        $this->artisan('administrador:crear');

        $this->conCredenciales(null, null);
        $this->artisan('administrador:crear')->assertSuccessful();

        $this->assertSame(1, Jugador::query()->count());
        $this->assertSame(0, Jugador::query()->where('es_administrador', true)->count());
    }

    public function test_una_contrasena_corta_o_un_email_mal_escrito_no_crean_nada(): void
    {
        $this->conCredenciales('admin@example.com', str_repeat('a', CrearAdministrador::LARGO_MINIMO - 1));
        $this->artisan('administrador:crear')->assertFailed();

        $this->conCredenciales('no-es-un-email', 'una-clave-bien-larga');
        $this->artisan('administrador:crear')->assertFailed();

        $this->assertSame(0, Jugador::query()->count());
    }

    public function test_si_el_apodo_ya_es_de_otro_jugador_la_cuenta_toma_uno_parecido(): void
    {
        Jugador::factory()->create(['apodo' => 'Cantinero']);
        $this->conCredenciales('admin@example.com', 'una-clave-bien-larga');

        $this->artisan('administrador:crear')->assertSuccessful();

        $this->assertSame('Cantinero 2', Jugador::query()->where('email', 'admin@example.com')->sole()->apodo);
    }

    public function test_la_cuenta_de_administracion_no_se_borra_con_la_limpieza_ni_aparece_marcada_en_el_ranking(): void
    {
        $this->conCredenciales('admin@example.com', 'una-clave-bien-larga');
        $this->artisan('administrador:crear');

        $this->travel(Jugador::DIAS_DE_INVITADO + 5)->days();
        $this->artisan('model:prune', ['--model' => [Jugador::class]]);

        // Tiene email, así que no es un invitado: es una cuenta como cualquier otra, que además administra.
        $this->assertSame(1, Jugador::query()->count());
        $this->get('/ranking')->assertOk()->assertDontSee('Cantinero');
    }

    /**
     * Lo que el comando va a leer como si viniera del entorno.
     */
    private function conCredenciales(?string $email, ?string $password): void
    {
        $this->app->bind(Credenciales::class, fn () => new Credenciales($email, $password));
    }
}
