<?php

namespace Tests\Feature;

use App\Models\Jugador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistroTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function datos(array $cambios = []): array
    {
        return [...['apodo' => 'El Tano', 'email' => 'tano@example.com', 'password' => 'una-clave-larga'], ...$cambios];
    }

    public function test_registrarse_crea_la_cuenta_y_deja_la_sesion_abierta(): void
    {
        $this->post('/registro', $this->datos())->assertRedirect('/perfil');

        $jugador = Jugador::sole();

        $this->assertSame('El Tano', $jugador->apodo);
        $this->assertSame('tano@example.com', $jugador->email);
        $this->assertFalse($jugador->esInvitado());
        $this->assertAuthenticatedAs($jugador);
    }

    public function test_la_contrasena_se_guarda_cifrada(): void
    {
        $this->post('/registro', $this->datos());

        $guardada = Jugador::sole()->password;

        $this->assertNotSame('una-clave-larga', $guardada);
        $this->assertTrue(Hash::check('una-clave-larga', $guardada));
    }

    public function test_el_email_se_guarda_en_minusculas(): void
    {
        $this->post('/registro', $this->datos(['email' => 'Tano@Example.COM']));

        $this->assertSame('tano@example.com', Jugador::sole()->email);
    }

    public function test_un_invitado_que_se_registra_conserva_su_fila(): void
    {
        $invitado = Jugador::factory()->invitado()->create();

        $this->actingAs($invitado)->post('/registro', $this->datos())->assertRedirect('/perfil');

        // No se creó un jugador nuevo: el invitado pasó a tener cuenta.
        $jugador = Jugador::sole();

        $this->assertSame($invitado->id, $jugador->id);
        $this->assertSame('El Tano', $jugador->apodo);
        $this->assertFalse($jugador->esInvitado());
    }

    public function test_no_se_puede_usar_un_apodo_que_ya_existe(): void
    {
        Jugador::factory()->create(['apodo' => 'El Tano']);

        $this->post('/registro', $this->datos(['email' => 'otro@example.com']))
            ->assertSessionHasErrors(['apodo' => 'Ese apodo ya está en uso. Probá con otro.']);

        $this->assertSame(1, Jugador::count());
    }

    public function test_no_se_puede_usar_un_email_que_ya_tiene_cuenta(): void
    {
        Jugador::factory()->create(['email' => 'tano@example.com']);

        $this->post('/registro', $this->datos(['apodo' => 'Otro']))->assertSessionHasErrors('email');

        $this->assertSame(1, Jugador::count());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function datosInvalidos(): array
    {
        return [
            'apodo vacío' => ['apodo', ''],
            'apodo corto' => ['apodo', 'ab'],
            'apodo largo' => ['apodo', str_repeat('a', 21)],
            'apodo con símbolos' => ['apodo', '<b>tano</b>'],
            'apodo con dos espacios seguidos' => ['apodo', 'El  Tano'],
            'apodo reservado para invitados' => ['apodo', 'Invitado 12345'],
            'apodo reservado, en minúsculas' => ['apodo', 'invitado'],
            'email vacío' => ['email', ''],
            'email mal escrito' => ['email', 'tano-arroba-example'],
            'contraseña vacía' => ['password', ''],
            'contraseña corta' => ['password', '1234567'],
        ];
    }

    #[DataProvider('datosInvalidos')]
    public function test_los_datos_invalidos_se_rechazan_y_no_crean_cuenta(string $campo, string $valor): void
    {
        $this->post('/registro', $this->datos([$campo => $valor]))->assertSessionHasErrors($campo);

        $this->assertSame(0, Jugador::count());
        $this->assertGuest();
    }

    public function test_el_apodo_acepta_letras_con_acento_numeros_y_guiones(): void
    {
        $this->post('/registro', $this->datos(['apodo' => 'Ñato_99 Román-C']))->assertSessionHasNoErrors();

        $this->assertSame('Ñato_99 Román-C', Jugador::sole()->apodo);
    }

    public function test_quien_ya_tiene_cuenta_no_ve_el_registro(): void
    {
        $this->actingAs(Jugador::factory()->create())->get('/registro')->assertRedirect('/perfil');
    }
}
