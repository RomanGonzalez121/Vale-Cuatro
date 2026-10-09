<?php

namespace Tests\Feature;

use App\Juego\Nivel;
use App\Models\Jugador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La pantalla del ranking: qué ve cada uno. La tabla es pública; lo que cambia es "Tu puesto".
 */
class RankingPorHttpTest extends TestCase
{
    use AnotandoResultados;
    use JugandoPartidas;
    use RefreshDatabase;

    public function test_sin_ninguna_partida_la_pantalla_lo_dice_y_no_inventa_jugadores(): void
    {
        $this->get('/ranking')
            ->assertOk()
            ->assertSee('Todavía no hay partidas que cuenten.')
            ->assertSee('Todavía no tenés partidas que cuenten.')
            ->assertSee('Jugar una partida')
            ->assertDontSee('Los cuatro que mandan')
            ->assertDontSee('Don Anselmo');
    }

    public function test_muestra_la_tabla_con_los_de_ejemplo_marcados_y_las_cuentas_sin_marca(): void
    {
        $deEjemplo = Jugador::factory()->deEjemplo()->count(6)->create();
        $cuenta = Jugador::factory()->create(['apodo' => 'La Tana']);

        foreach ($deEjemplo as $numero => $jugador) {
            $this->anotarle($jugador, str_repeat('G', 10 - $numero));
        }

        $this->anotarle($cuenta, 'GGPG', envidosJugados: 4, envidosGanados: 3);

        $pagina = $this->get('/ranking')
            ->assertOk()
            ->assertSee('Los cuatro que mandan')
            ->assertSee('La Tana')
            ->assertSee('75 %')
            ->assertDontSee('Todavía no hay partidas que cuenten.')
            ->getContent();

        // Seis de ejemplo, seis marcas: cuatro arriba y dos en la tabla. La cuenta no lleva ninguna.
        $this->assertSame(6, preg_match_all('/<\/svg>\s*bot\s*<\/span>/', $pagina));

    }

    public function test_quien_tiene_cuenta_ve_su_puesto_y_a_quien_tiene_que_alcanzar(): void
    {
        [$arriba, $vos] = [Jugador::factory()->create(['apodo' => 'La de arriba']), Jugador::factory()->create(['apodo' => 'El Tano'])];

        $this->anotarle($arriba, 'GGGGG');
        $this->anotarle($vos, 'GGP');

        $this->actingAs($vos)->get('/ranking')
            ->assertOk()
            ->assertSeeInOrder(['Tu puesto', '2', 'ganadas de 3', 'Te faltan', '3', 'para alcanzar a La de arriba.'])
            ->assertDontSee('Estarías')
            ->assertDontSee('Creá una cuenta');

        // Quien va primero lo lee así.
        $this->actingAs($arriba)->get('/ranking')->assertSee('Vas primero.');
    }

    public function test_quien_juega_sin_cuenta_ve_donde_estaria_y_no_figura_en_la_tabla(): void
    {
        $cuenta = Jugador::factory()->create(['apodo' => 'La de arriba']);
        $invitado = Jugador::factory()->invitado()->create(['apodo' => 'Invitado 77123']);

        $this->anotarle($cuenta, 'GGG');
        $this->anotarle($invitado, 'GG');

        $this->actingAs($invitado)->get('/ranking')
            ->assertOk()
            ->assertSeeInOrder(['Estarías en el puesto', '2', 'Te falta', '1', 'para alcanzar a La de arriba.', 'Creá una cuenta', 'para aparecer en la tabla.'])
            ->assertSee('href="'.route('registro').'"', false)
            ->assertDontSee('Invitado 77123');

        // A los demás tampoco se les muestra.
        $this->actingAs($cuenta)->get('/ranking')->assertDontSee('Invitado 77123');
    }

    public function test_una_partida_jugada_de_verdad_llega_a_la_tabla(): void
    {
        $jugador = Jugador::factory()->create(['apodo' => 'El Tano']);

        $this->actingAs($jugador)->get('/ranking')->assertSee('Todavía no tenés partidas que cuenten.');

        [$partida] = $this->partidaContraElBot($jugador, Nivel::Intermedio);

        $this->actingAs($jugador)->get('/ranking')
            ->assertOk()
            ->assertSeeInOrder(['Tu puesto', '1', $partida->ganador === 0 ? '1' : '0', 'de 1', 'Vas primero.'])
            ->assertDontSee('Todavía no tenés partidas que cuenten.');
    }

    public function test_con_una_partida_sin_terminar_el_boton_la_retoma(): void
    {
        $jugador = Jugador::factory()->create();
        $this->laMesa()->abrir($jugador);

        $this->actingAs($jugador)->get('/ranking')
            ->assertOk()
            ->assertSee('Seguir la partida')
            ->assertSee('href="'.route('mesa').'"', false)
            ->assertDontSee('Jugar una partida');
    }
}
