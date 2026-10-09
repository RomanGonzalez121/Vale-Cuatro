<?php

namespace Tests\Feature;

use App\Juego\Mesa;
use App\Models\Jugador;
use App\Models\Partida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Abrir una sala, mandar el link y sentarse, por las rutas del sitio. Cada ruta que toca una sala
 * revisa quién la pide: solo quien la abrió la ve, la consulta y la cancela.
 */
class SalaPorHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_quien_llega_sin_sesion_abre_una_sala_como_invitado_y_va_a_ella(): void
    {
        $respuesta = $this->post('/invitar');

        $sala = Partida::sole();

        $respuesta->assertRedirect(route('sala', $sala->codigo));
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->esInvitado());
        $this->assertSame(auth()->id(), $sala->jugador_id);
        $this->assertTrue($sala->esperando());
    }

    public function test_un_toque_repetido_vuelve_a_la_misma_sala(): void
    {
        $this->post('/invitar');
        $sala = Partida::sole();

        $this->post('/invitar')->assertRedirect(route('sala', $sala->codigo));

        $this->assertSame(1, Partida::count());
    }

    public function test_quien_ya_juega_contra_el_bot_no_abre_una_sala_y_vuelve_a_su_mesa(): void
    {
        $jugador = Jugador::factory()->invitado()->create();
        $this->actingAs($jugador)->post('/jugar');

        $this->actingAs($jugador)->post('/invitar')->assertRedirect(route('mesa'));

        $this->assertSame(1, Partida::count());
        $this->assertFalse(Partida::sole()->entre_personas);
    }

    public function test_con_una_sala_esperando_jugar_contra_el_bot_no_abre_otra_partida(): void
    {
        $jugador = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($jugador);

        $this->actingAs($jugador)->post('/jugar')->assertRedirect(route('mesa'));
        $this->assertSame(1, Partida::count(), 'No se abre una partida contra el bot encima de la sala.');

        // Y la mesa lo lleva de vuelta a su sala.
        $this->actingAs($jugador)->get('/mesa')->assertRedirect(route('sala', $sala->codigo));
    }

    public function test_quien_abre_el_link_sin_sesion_se_sienta_como_invitado_y_empieza_la_partida(): void
    {
        $creador = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($creador);

        $this->post(route('invitacion.entrar', $sala->codigo))->assertRedirect(route('mesa'));

        $partida = $sala->fresh();

        $this->assertTrue($partida->enCurso());
        $this->assertAuthenticated();
        $this->assertSame(auth()->id(), $partida->invitado_id);
        $this->assertNotSame($creador->id, $partida->invitado_id);
        $this->assertSame(1, $partida->asientoDe(auth()->user()));
    }

    public function test_quien_abrio_la_sala_en_su_propio_link_no_se_sienta_y_vuelve_a_su_sala(): void
    {
        $creador = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($creador);

        $this->actingAs($creador)->post(route('invitacion.entrar', $sala->codigo))->assertRedirect(route('mesa'));
        $this->actingAs($creador)->get('/mesa')->assertRedirect(route('sala', $sala->codigo));

        $this->assertNull($sala->fresh()->invitado_id);
        $this->assertTrue($sala->fresh()->esperando());

        // Lo mismo si abre el link: lo manda a su sala.
        $this->actingAs($creador)->get(route('invitacion', $sala->codigo))->assertRedirect(route('sala', $sala->codigo));
    }

    public function test_con_la_sala_llena_un_tercero_vuelve_a_la_invitacion_con_el_motivo(): void
    {
        $sala = $this->mesa()->crearSala(Jugador::factory()->invitado()->create());
        $this->mesa()->sentarse($sala->codigo, Jugador::factory()->invitado()->create());
        $tercero = Jugador::factory()->invitado()->create();

        $this->actingAs($tercero)->post(route('invitacion.entrar', $sala->codigo))
            ->assertRedirect(route('invitacion', $sala->codigo))
            ->assertSessionHas('aviso', 'Esa partida ya tiene sus dos jugadores.');

        $this->assertNotSame($tercero->id, $sala->fresh()->invitado_id);
    }

    public function test_un_link_que_no_existe_o_mal_escrito_no_encuentra_nada(): void
    {
        $sala = $this->mesa()->crearSala(Jugador::factory()->invitado()->create());

        $this->get(route('invitacion', 'estenoexiste0000'))->assertNotFound();
        $this->post(route('invitacion.entrar', 'estenoexiste0000'))->assertNotFound();
        $this->get('/invitacion/corto')->assertNotFound();
        $this->get('/invitacion/'.strtoupper($sala->codigo))->assertNotFound();

        // Mirar un link inexistente no fabrica jugadores.
        $this->assertGuest();
        $this->assertSame(1, Jugador::count());
    }

    public function test_una_partida_contra_el_bot_no_se_puede_abrir_por_un_codigo(): void
    {
        $jugador = Jugador::factory()->invitado()->create();
        $contraElBot = $this->mesa()->abrir($jugador);

        // Aunque alguien conociera el id o inventara un código: el bot no tiene link.
        $this->assertNull($contraElBot->codigo);
        $this->post(route('invitacion.entrar', 'abcdefghijklmnop'))->assertNotFound();
    }

    public function test_solo_quien_abrio_la_sala_consulta_si_ya_empezo(): void
    {
        $creador = Jugador::factory()->invitado()->create();
        $otro = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($creador);

        $this->actingAs($creador)->getJson(route('sala.estado', $sala->codigo))
            ->assertOk()->assertExactJson(['empezo' => false, 'cerrada' => false, 'rival' => null, 'mano' => false]);

        $this->actingAs($otro)->getJson(route('sala.estado', $sala->codigo))->assertForbidden();

        $rival = Jugador::factory()->invitado()->create();
        $this->mesa()->sentarse($sala->codigo, $rival);

        // Cuando se sienta, se sabe quién (solo el apodo) y quién es mano, que se sortea en ese momento.
        $this->actingAs($creador)->getJson(route('sala.estado', $sala->codigo))
            ->assertOk()->assertExactJson(['empezo' => true, 'cerrada' => false, 'rival' => $rival->apodo, 'mano' => $sala->fresh()->primer_mano === 0]);
    }

    public function test_sin_sesion_no_se_ve_ni_se_consulta_una_sala(): void
    {
        $sala = $this->mesa()->crearSala(Jugador::factory()->invitado()->create());

        $this->getJson(route('sala.estado', $sala->codigo))->assertUnauthorized();
        $this->get(route('sala', $sala->codigo))->assertRedirect();
        $this->post(route('sala.cancelar', $sala->codigo))->assertRedirect();

        $this->assertTrue($sala->fresh()->esperando());
    }

    public function test_quien_no_abrio_la_sala_va_a_la_invitacion_y_no_puede_cancelarla(): void
    {
        $sala = $this->mesa()->crearSala(Jugador::factory()->invitado()->create());
        $otro = Jugador::factory()->invitado()->create();

        $this->actingAs($otro)->get(route('sala', $sala->codigo))->assertRedirect(route('invitacion', $sala->codigo));
        $this->actingAs($otro)->post(route('sala.cancelar', $sala->codigo))->assertForbidden();

        $this->assertTrue($sala->fresh()->esperando());
    }

    public function test_quien_abrio_la_sala_la_cancela_y_puede_abrir_otra(): void
    {
        $creador = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($creador);

        $this->actingAs($creador)->post(route('sala.cancelar', $sala->codigo))
            ->assertRedirect(route('modos'))
            ->assertSessionHas('aviso', 'Cerraste la sala.');

        $this->assertSame(Partida::ABANDONADA, $sala->fresh()->estado);
        $this->assertNull($sala->fresh()->ganador, 'Una sala que se cancela no la ganó nadie.');

        // Y su sala vieja ya no es un lugar al que entrar.
        $this->actingAs($creador)->get(route('sala', $sala->codigo))->assertRedirect(route('modos'));
        $this->actingAs($creador)->getJson(route('sala.estado', $sala->codigo))
            ->assertOk()->assertExactJson(['empezo' => false, 'cerrada' => true, 'rival' => null, 'mano' => false]);

        $this->actingAs($creador)->post('/invitar');
        $this->assertSame(2, Partida::count());
    }

    public function test_si_justo_se_sento_alguien_cancelar_lleva_a_la_mesa_y_no_cierra_nada(): void
    {
        $creador = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($creador);
        $this->mesa()->sentarse($sala->codigo, Jugador::factory()->invitado()->create());

        $this->actingAs($creador)->post(route('sala.cancelar', $sala->codigo))->assertRedirect(route('mesa'));

        $this->assertTrue($sala->fresh()->enCurso());
    }

    public function test_con_la_partida_en_curso_la_sala_lleva_a_la_mesa(): void
    {
        $creador = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($creador);
        $this->mesa()->sentarse($sala->codigo, Jugador::factory()->invitado()->create());

        $this->actingAs($creador)->get(route('sala', $sala->codigo))->assertRedirect(route('mesa'));
    }

    private function mesa(): Mesa
    {
        return $this->app->make(Mesa::class);
    }
}
