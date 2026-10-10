<?php

namespace Tests\Feature;

use App\Identidad\Iconos;
use App\Juego\JugadoresDeEjemplo;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Juego\Revanchas;
use App\Juego\Torneos;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Torneo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * El torneo contra bots por las rutas del sitio: armarlo, mirar las llaves, jugar la partida de la ronda
 * en la mesa y dejarlo.
 */
class TorneoPorHttpTest extends TestCase
{
    use RefreshDatabase, TorneosDePrueba;

    protected function setUp(): void
    {
        parent::setUp();

        // El bot no juega solo y las llaves no avanzan solas: la pantalla de las llaves las pone al día al abrirse,
        // que es lo que hace también en el sitio cuando el trabajo de la cola todavía no corrió.
        Queue::fake();
        // Las rutas juegan contra el bot de prueba, que hace lo que el test le diga.
        $this->app->instance(Mesa::class, $this->mesaDePrueba());
    }

    public function test_quien_llega_sin_sesion_arma_un_torneo_como_invitado_y_ve_sus_llaves(): void
    {
        $respuesta = $this->post('/torneo', ['lugares' => 8]);

        $torneo = Torneo::query()->sole();

        $respuesta->assertRedirect(route('torneo', $torneo));
        $this->assertAuthenticated();
        $this->assertTrue($torneo->jugador->esInvitado());
        $this->assertSame([8, Torneos::PUNTOS], [$torneo->lugares, $torneo->puntos]);

        $html = $this->get(route('torneo', $torneo))->assertOk()
            ->assertSeeInOrder(['Las llaves', 'Cuartos de final', 'Semifinales', 'Final', 'Campeón'])
            ->assertSee('Jugás los cuartos de final contra')
            ->assertSee('Jugar los cuartos de final')
            ->assertSee('Te toca jugarla.')
            ->assertSee('Se juega a la par de la tuya.')
            ->getContent();

        // Siete cruces, y la persona está una sola vez.
        $this->assertSame(7, substr_count($html, 'class="llave-lugar"') - 1);
        $this->assertSame(1, preg_match_all('/>\s*Vos\s*</', $html));
    }

    public function test_todo_bot_de_las_llaves_se_ve_marcado_como_bot_con_su_nivel(): void
    {
        $jugador = Jugador::factory()->create();
        $torneo = $this->torneos()->crear($jugador, 8, 31);

        $html = $this->actingAs($jugador)->get(route('torneo', $torneo))->assertOk()->getContent();

        foreach ($torneo->inscriptos as $lugar => $inscripto) {
            if ($lugar === $torneo->lugarDeLaPersona()) {
                continue;
            }

            $nivel = mb_strtolower($torneo->nivelDe($lugar)->nombre());

            // Junto a su apodo, dicho para el lector de pantalla; y a la vista, el ícono del bot y los fósforos del nivel.
            $this->assertMatchesRegularExpression('/'.preg_quote(e($inscripto['apodo']), '/').'\s*<span class="sr-only">, bot '.preg_quote($nivel, '/').'<\/span>/', $html);
        }

        // Uno por cada bot en la primera ronda, y uno más arriba: el del rival que le tocó a la persona.
        $this->assertSame(8, substr_count($html, Iconos::de('bot')[0]));
    }

    public function test_un_torneo_solo_se_arma_de_cuatro_o_de_ocho(): void
    {
        $jugador = Jugador::factory()->create();

        foreach ([null, 2, 6, 16, 'ocho'] as $lugares) {
            $this->actingAs($jugador)->post('/torneo', ['lugares' => $lugares])->assertSessionHasErrors('lugares');
        }

        $this->assertSame(0, Torneo::query()->count());
    }

    public function test_con_una_partida_sin_terminar_no_se_arma_un_torneo_se_vuelve_a_la_mesa(): void
    {
        $jugador = Jugador::factory()->create();
        $this->mesaDePrueba()->abrir($jugador, Nivel::Facil);

        $this->actingAs($jugador)->post('/torneo', ['lugares' => 4])->assertRedirect(route('mesa'));

        $this->assertSame(0, Torneo::query()->count());
    }

    public function test_armar_un_torneo_con_otro_sin_terminar_lleva_al_que_ya_estaba(): void
    {
        $jugador = Jugador::factory()->create();
        $torneo = $this->torneos()->crear($jugador, 4);

        $this->actingAs($jugador)->post('/torneo', ['lugares' => 8])->assertRedirect(route('torneo', $torneo));

        $this->assertSame(1, Torneo::query()->count());
    }

    public function test_el_torneo_de_otro_no_existe_y_sin_sesion_no_se_entra(): void
    {
        $torneo = $this->torneos()->crear(Jugador::factory()->create(), 4);

        $this->get(route('torneo', $torneo))->assertRedirect(route('portada'));
        $this->post(route('torneo.jugar', $torneo))->assertRedirect(route('portada'));

        $otro = Jugador::factory()->create();

        $this->actingAs($otro)->get(route('torneo', $torneo))->assertNotFound();
        $this->actingAs($otro)->post(route('torneo.jugar', $torneo))->assertNotFound();
        $this->actingAs($otro)->post(route('torneo.abandonar', $torneo))->assertNotFound();

        $this->assertTrue($torneo->fresh()->enCurso());
        $this->assertSame(0, Partida::query()->count());
    }

    public function test_jugar_la_ronda_lleva_a_la_mesa_contra_el_bot_que_toco_con_su_apodo_y_a_quince(): void
    {
        $jugador = Jugador::factory()->create();
        $torneo = $this->torneos()->crear($jugador, 4, 12);
        $rival = $torneo->apodoDe($this->torneos()->cruceDeLaPersona($torneo)->rivalDe($torneo->lugarDeLaPersona()));

        $this->actingAs($jugador)->post(route('torneo.jugar', $torneo))->assertRedirect(route('mesa'));

        $partida = Partida::query()->sole();
        $html = $this->get('/mesa')->assertOk()
            ->assertSee($rival)
            ->assertSee('Semifinal')
            ->assertSee('quedás afuera del torneo')
            ->assertSee('Ver las llaves')
            ->assertDontSee('Jugar la revancha')
            ->getContent();

        $this->assertSame($torneo->id, $partida->torneo_id);
        $this->assertArrayHasKey($rival, JugadoresDeEjemplo::lista());
        // El tanteador de la mesa va a quince: tres grupos de cinco por jugador y sin la raya de las malas y las buenas.
        $this->assertSame(1, preg_match('/<section aria-label="Tanteador".*?<\/section>/s', $html, $tanteador));
        $this->assertSame(6, substr_count($tanteador[0], '<svg'));
        $this->assertStringNotContainsString('w-px', $tanteador[0]);
        // Con la partida empezada, las llaves ofrecen seguirla.
        $this->get(route('torneo', $torneo))->assertOk()->assertSee('Estás jugando la semifinal')->assertSee('Seguir la partida')->assertSee('La estás jugando.');
    }

    public function test_con_otra_partida_abierta_la_del_torneo_no_empieza_y_las_llaves_dicen_por_que(): void
    {
        $jugador = Jugador::factory()->create();
        $torneo = $this->torneos()->crear($jugador, 4);
        $this->mesaDePrueba()->abrir($jugador, Nivel::Facil);

        $this->actingAs($jugador)->post(route('torneo.jugar', $torneo))
            ->assertRedirect(route('torneo', $torneo))
            ->assertSessionHas('aviso', 'Tenés otra partida sin terminar. Terminala o abandonala antes de jugar la del torneo.');

        $this->assertSame(1, Partida::query()->count());
    }

    public function test_ganar_la_partida_hace_pasar_de_ronda_y_ganar_la_final_es_ser_campeon(): void
    {
        $jugador = Jugador::factory()->create();
        $torneo = $this->torneoCorto($jugador);

        $this->actingAs($jugador)->post(route('torneo.jugar', $torneo));
        $this->cerrarContraElBot(Partida::query()->sole(), ganaLaPersona: true);

        // Terminada la partida, la mesa manda a las llaves, y ahí ya está la final armada.
        $this->get('/mesa')->assertRedirect(route('torneo', $torneo));
        $this->get(route('torneo', $torneo))->assertOk()
            ->assertSee('Jugás la final contra')
            ->assertSee('Jugar la final')
            ->assertDontSee('Se juega a la par de la tuya.');

        $this->post(route('torneo.jugar', $torneo))->assertRedirect(route('mesa'));
        $this->cerrarContraElBot(Partida::query()->latest('id')->first(), ganaLaPersona: true);

        $this->get(route('torneo', $torneo))->assertOk()
            ->assertSee('Sos el campeón del torneo')
            ->assertSee('Ganaste las dos partidas')
            ->assertSee('Armar otro torneo')
            ->assertDontSee('Dejar el torneo');

        $this->assertSame($torneo->lugarDeLaPersona(), $torneo->fresh()->campeon);
    }

    public function test_abandonar_la_partida_desde_la_mesa_deja_afuera_del_torneo(): void
    {
        $jugador = Jugador::factory()->create();
        $torneo = $this->torneoCorto($jugador, 8);

        $this->actingAs($jugador)->post(route('torneo.jugar', $torneo));
        $partida = Partida::query()->sole();

        $this->post('/mesa/abandonar', ['partida' => $partida->id])
            ->assertRedirect(route('torneo', $torneo))
            ->assertSessionHas('aviso', 'Abandonaste la partida y quedaste afuera del torneo.');

        // Al abrir las llaves, lo que faltaba se juega solo: dicen dónde quedó la persona y nombran al campeón.
        $llaves = $this->get(route('torneo', $torneo))->assertOk()
            ->assertSee('Quedaste afuera en los cuartos de final')
            ->assertSee('La dejaste antes del final.')
            ->assertSee('Armar otro torneo');

        $torneo->refresh();

        $this->assertFalse($torneo->enCurso());
        $llaves->assertSeeInOrder(['Campeón', $torneo->apodoDe($torneo->campeon)]);

        // Ya no hay nada que jugar ahí.
        $this->post(route('torneo.jugar', $torneo))->assertRedirect(route('torneo', $torneo))->assertSessionHas('aviso', 'Ese torneo ya terminó.');
    }

    public function test_dejar_el_torneo_desde_las_llaves_lo_termina(): void
    {
        $jugador = Jugador::factory()->create();
        $torneo = $this->torneoCorto($jugador);

        $this->actingAs($jugador)->post(route('torneo.abandonar', $torneo))
            ->assertRedirect(route('torneo', $torneo))
            ->assertSessionHas('aviso', 'Dejaste el torneo.');

        $this->assertFalse($torneo->fresh()->enCurso());
        $this->get(route('torneo', $torneo))->assertOk()->assertSee('Quedaste afuera en la semifinal')->assertSee('Pasó sin jugar.');
    }

    public function test_de_una_partida_de_torneo_no_hay_revancha(): void
    {
        $jugador = Jugador::factory()->create();
        $torneo = $this->torneoCorto($jugador);

        $this->actingAs($jugador)->post(route('torneo.jugar', $torneo));
        $partida = Partida::query()->sole();
        $this->cerrarContraElBot($partida, ganaLaPersona: false);

        $this->getJson("/revancha?partida={$partida->id}")->assertOk()->assertJson(['estado' => Revanchas::NO_DISPONIBLE]);
        $this->post('/revancha/pedir', ['partida' => $partida->id]);

        $this->assertSame(1, Partida::query()->count());
    }

    public function test_el_historial_dice_que_la_partida_fue_de_un_torneo_y_contra_que_bot(): void
    {
        $jugador = Jugador::factory()->create();
        $torneo = $this->torneoCorto($jugador);
        $rival = $torneo->apodoDe($this->torneos()->cruceDeLaPersona($torneo)->rivalDe($torneo->lugarDeLaPersona()));

        $this->actingAs($jugador)->post(route('torneo.jugar', $torneo));
        $this->cerrarContraElBot(Partida::query()->sole(), ganaLaPersona: true);

        $this->get('/historial')->assertOk()->assertSee("Contra {$rival}")->assertSee('Torneo, semifinal.')->assertDontSee('Mano a mano');
    }
}
