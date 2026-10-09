<?php

namespace Tests\Feature;

use App\Juego\Nivel;
use App\Juego\Revanchas;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Revancha;
use App\Models\Serie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * La revancha y la serie por las rutas del sitio: quién puede pedir qué, y lo que ve cada uno en sus pantallas.
 */
class RevanchaPorHttpTest extends TestCase
{
    use JugandoPartidas, PartidasCortas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_sin_sesion_no_se_llega_a_ninguna_ruta_de_la_revancha(): void
    {
        $this->getJson('/revancha?partida=1')->assertUnauthorized();

        foreach (['pedir', 'aceptar', 'rechazar', 'cancelar'] as $que) {
            $this->postJson("/revancha/{$que}", ['partida' => 1])->assertUnauthorized();
        }
    }

    public function test_la_revancha_de_una_partida_ajena_no_existe(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        $partida = $this->partidaEntrePersonas($uno, $dos);
        $tercero = Jugador::factory()->create();

        $this->actingAs($tercero)->getJson("/revancha?partida={$partida->id}")->assertNotFound();

        foreach (['pedir', 'aceptar', 'rechazar', 'cancelar'] as $que) {
            $this->actingAs($tercero)->postJson("/revancha/{$que}", ['partida' => $partida->id])->assertNotFound();
        }

        $this->assertSame(0, Revancha::query()->count());
        $this->assertSame(1, Partida::query()->count());
    }

    public function test_hay_que_decir_de_que_partida_se_habla(): void
    {
        $this->actingAs(Jugador::factory()->create())->postJson('/revancha/pedir')->assertUnprocessable();
    }

    public function test_uno_la_pide_el_otro_la_ve_y_al_quererla_los_dos_tienen_partida_nueva(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        $partida = $this->partidaEntrePersonas($uno, $dos);
        $this->travelTo(now()->startOfSecond());

        $this->actingAs($uno)->getJson("/revancha?partida={$partida->id}")->assertOk()->assertExactJson(['estado' => Revanchas::DISPONIBLE, 'restan' => null, 'por' => null]);

        $this->actingAs($uno)->postJson('/revancha/pedir', ['partida' => $partida->id])
            ->assertOk()
            ->assertExactJson(['estado' => Revanchas::PEDIDA, 'restan' => Revanchas::SEGUNDOS_PARA_CONTESTAR, 'por' => null]);

        $this->actingAs($dos)->getJson("/revancha?partida={$partida->id}")->assertOk()->assertJson(['estado' => Revanchas::TE_PIDEN]);

        // Quien la pidió no la puede querer por el otro.
        $this->actingAs($uno)->postJson('/revancha/aceptar', ['partida' => $partida->id])->assertOk()->assertJson(['estado' => Revanchas::PEDIDA]);
        $this->assertSame(1, Partida::query()->count());

        $this->actingAs($dos)->postJson('/revancha/aceptar', ['partida' => $partida->id])->assertOk()->assertJson(['estado' => Revanchas::ACEPTADA]);

        $nueva = $this->laQueSiguioA($partida);

        $this->assertSame([$uno->id, $dos->id], [$nueva->jugador_id, $nueva->invitado_id]);
        // Los dos entran a la mesa de la partida nueva, cada uno con lo suyo.
        $this->actingAs($uno)->getJson("/revancha?partida={$partida->id}")->assertJson(['estado' => Revanchas::ACEPTADA]);
        $this->assertSame($nueva->id, $this->actingAs($uno)->get('/mesa')->assertOk()->viewData('vista')['partida']);
        $this->assertSame($nueva->id, $this->actingAs($dos)->get('/mesa')->assertOk()->viewData('vista')['partida']);
    }

    public function test_no_quererla_o_cancelarla_por_las_rutas_no_crea_nada(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        $partida = $this->partidaEntrePersonas($uno, $dos);
        $otra = $this->partidaEntrePersonas($uno, $dos);

        $this->actingAs($uno)->postJson('/revancha/pedir', ['partida' => $partida->id]);
        $this->actingAs($dos)->postJson('/revancha/rechazar', ['partida' => $partida->id])->assertOk()->assertExactJson(['estado' => Revanchas::RECHAZADA, 'restan' => null, 'por' => 'vos']);

        $this->actingAs($dos)->postJson('/revancha/pedir', ['partida' => $otra->id]);
        $this->actingAs($dos)->postJson('/revancha/cancelar', ['partida' => $otra->id])->assertOk()->assertJson(['estado' => Revanchas::AGOTADA]);

        $this->assertSame(2, Partida::query()->count());
        $this->actingAs($uno)->get('/mesa')->assertRedirect(route('modos'));
    }

    public function test_el_canal_de_una_partida_terminada_sigue_siendo_solo_de_sus_dos_jugadores(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        $partida = $this->partidaEntrePersonas($uno, $dos);
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'clave', 'broadcasting.connections.reverb.secret' => 'secreto', 'broadcasting.connections.reverb.app_id' => 'app']);
        require base_path('routes/channels.php');

        $pedido = ['socket_id' => '1234.5678', 'channel_name' => "private-partida.{$partida->id}"];

        // Por ese canal llega el aviso de la revancha: lo escuchan los dos que la jugaron y nadie más.
        $this->actingAs($uno)->post('/broadcasting/auth', $pedido)->assertOk();
        $this->actingAs($dos)->post('/broadcasting/auth', $pedido)->assertOk();
        $this->actingAs(Jugador::factory()->create())->post('/broadcasting/auth', $pedido)->assertForbidden();
    }

    public function test_contra_el_bot_el_boton_del_final_lleva_a_la_mesa_con_la_revancha_ya_repartida(): void
    {
        $jugador = Jugador::factory()->create();
        [$partida] = $this->partidaContraElBot($jugador, Nivel::UltraDificil);

        $this->actingAs($jugador)->post('/revancha/pedir', ['partida' => $partida->id])->assertRedirect(route('mesa'));

        $nueva = $this->laQueSiguioA($partida);

        $this->assertSame(Nivel::UltraDificil, $nueva->nivel_bot);
        $this->actingAs($jugador)->get('/mesa')->assertOk()->assertSee('Bot ultra difícil')->assertViewHas('serie', null);

        // El mismo toque dos veces vuelve a la misma mesa.
        $this->actingAs($jugador)->post('/revancha/pedir', ['partida' => $partida->id])->assertRedirect(route('mesa'));
        $this->assertSame(2, Partida::query()->count());
    }

    public function test_si_la_revancha_no_se_puede_armar_el_boton_vuelve_a_los_modos_y_lo_dice(): void
    {
        $jugador = Jugador::factory()->create();
        [$enCurso] = $this->partidaEntrePersonasHasta($jugador, Jugador::factory()->create(), 15);

        $this->actingAs($jugador)->post('/revancha/pedir', ['partida' => $enCurso->id])
            ->assertRedirect(route('modos'))
            ->assertSessionHas('aviso', 'No se pudo armar la revancha.');
    }

    public function test_jugar_contra_el_bot_al_mejor_de_tres_abre_una_serie_y_la_mesa_muestra_su_marcador(): void
    {
        $this->post('/jugar', ['nivel' => Nivel::Facil->value, 'serie' => '1'])->assertRedirect(route('mesa'));

        $partida = Partida::query()->sole();

        $this->assertNotNull($partida->serie_id);

        $this->get('/mesa')->assertOk()
            ->assertViewHas('serie', ['vos' => 0, 'rival' => 0, 'necesarias' => 2])
            ->assertSee('Serie al mejor de tres: vos 0, el bot 0.')
            ->assertSee('Jugar la siguiente partida')
            ->assertSee('y con ella la serie');
    }

    public function test_sin_pedir_serie_la_partida_es_suelta_y_la_mesa_no_habla_de_series(): void
    {
        $this->post('/jugar', ['serie' => '0'])->assertRedirect(route('mesa'));

        $this->assertNull(Partida::query()->sole()->serie_id);
        $this->assertSame(0, Serie::query()->count());

        $this->get('/mesa')->assertOk()->assertViewHas('serie', null)->assertDontSee('Serie al mejor de tres')->assertDontSee('Jugar la siguiente partida');
    }

    public function test_el_pedido_de_serie_tiene_que_ser_un_si_o_un_no(): void
    {
        $this->post('/jugar', ['serie' => 'siempre'])->assertSessionHasErrors('serie');
        $this->post('/invitar', ['serie' => 'siempre'])->assertSessionHasErrors('serie');

        $this->assertSame(0, Partida::query()->count());
    }

    public function test_una_sala_al_mejor_de_tres_lo_dice_en_la_sala_y_en_la_invitacion(): void
    {
        $this->post('/invitar', ['serie' => '1']);

        $sala = Partida::query()->sole();

        $this->assertNotNull($sala->serie_id);
        $this->get(route('sala', $sala->codigo))->assertOk()->assertSee('arranca el mejor de tres');

        $this->actingAs(Jugador::factory()->create())->get(route('invitacion', $sala->codigo))->assertOk()->assertSee('Una serie al mejor de tres');
    }

    public function test_una_sala_comun_no_habla_de_series(): void
    {
        $this->post('/invitar');

        $sala = Partida::query()->sole();

        $this->assertNull($sala->serie_id);
        $this->get(route('sala', $sala->codigo))->assertOk()->assertSee('se reparte la primera mano')->assertDontSee('mejor de tres');
        $this->actingAs(Jugador::factory()->create())->get(route('invitacion', $sala->codigo))->assertOk()->assertDontSee('mejor de tres');
    }

    public function test_los_modos_dejan_elegir_el_mejor_de_tres_contra_el_bot_y_con_otra_persona(): void
    {
        $html = $this->get('/modos')->assertOk()->getContent();

        // Una casilla por rival que se juega, y cada botón manda lo que diga la casilla.
        $this->assertSame(2, substr_count($html, 'Al mejor de tres'));
        $this->assertSame(2, substr_count($html, 'name="serie" value="0"'));
    }

    public function test_con_una_partida_sin_terminar_los_modos_no_ofrecen_elegir_la_serie(): void
    {
        $jugador = Jugador::factory()->create();
        $this->actingAs($jugador)->post('/jugar');

        $this->actingAs($jugador)->get('/modos')->assertOk()->assertDontSee('Al mejor de tres');
    }

    public function test_en_la_serie_entre_dos_personas_cada_mesa_ve_el_marcador_desde_su_lado(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(['apodo' => 'El Zurdo']), Jugador::factory()->create(['apodo' => 'La Tana'])];
        $primera = $this->partidaCorta($uno, $dos, enSerie: true);

        $this->ganar($primera, 1);

        // La segunda ya está repartida: la mesa de cada uno la muestra con la serie uno a cero para La Tana.
        $this->actingAs($uno)->get('/mesa')->assertOk()
            ->assertViewHas('serie', ['vos' => 0, 'rival' => 1, 'necesarias' => 2])
            ->assertViewHas('faltaLlegar', true)
            ->assertSee('Serie al mejor de tres: vos 0, La Tana 1.');
        $this->actingAs($dos)->get('/mesa')->assertOk()
            ->assertViewHas('serie', ['vos' => 1, 'rival' => 0, 'necesarias' => 2])
            ->assertViewHas('faltaLlegar', true);
    }

    public function test_al_volver_a_la_mesa_despues_de_la_ultima_se_cuenta_como_quedo_la_serie(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        $primera = $this->partidaCorta($uno, $dos, enSerie: true);

        $this->ganar($primera, 0);
        $this->ganar($this->laQueSiguioA($primera), 0);

        $this->actingAs($uno)->get('/mesa')->assertRedirect(route('modos'))->assertSessionHas('aviso', fn (string $aviso) => str_ends_with($aviso, 'ganaste. La serie quedó 2 a 0: la ganaste.'));
        $this->actingAs($dos)->get('/mesa')->assertRedirect(route('modos'))->assertSessionHas('aviso', fn (string $aviso) => str_ends_with($aviso, 'ganó tu rival. La serie quedó 0 a 2: la ganó tu rival.'));
    }

    public function test_abandonar_una_partida_de_la_serie_avisa_que_se_va_la_serie_entera(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        $this->partidaCorta($uno, $dos, enSerie: true);

        $this->actingAs($dos)->post('/mesa/abandonar')->assertRedirect(route('modos'))->assertSessionHas('aviso', 'Abandonaste la partida y, con ella, la serie.');
        $this->actingAs($uno)->get('/mesa')->assertRedirect(route('modos'))->assertSessionHas('aviso', 'Tu rival abandonó la partida: ganaste la serie.');

        $this->assertSame(0, Serie::query()->sole()->ganador);
    }

    public function test_el_historial_agrupa_las_partidas_de_una_serie_y_marca_la_revancha(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(['apodo' => 'El Zurdo']), Jugador::factory()->create(['apodo' => 'La Tana'])];

        // Una partida suelta y su revancha.
        $suelta = $this->partidaCorta($uno, $dos);
        $this->ganar($suelta, 0);
        $this->app->make(Revanchas::class)->pedir($suelta->fresh(), 0);
        $this->app->make(Revanchas::class)->aceptar($suelta->fresh(), 1);
        $this->ganar($this->laQueSiguioA($suelta), 1);

        // Y después una serie que El Zurdo gana dos a uno.
        $primera = $this->partidaCorta($uno, $dos, enSerie: true);
        $this->ganar($primera, 0);
        $segunda = $this->laQueSiguioA($primera);
        $this->ganar($segunda, 1);
        $this->ganar($this->laQueSiguioA($segunda), 0);

        $html = $this->actingAs($uno)->get('/historial')->assertOk()
            ->assertSee('Al mejor de tres contra La Tana')
            ->assertSee('Ganaste la serie 2 a 1.')
            ->assertSeeInOrder(['3ª de la serie.', '2ª de la serie.', '1ª de la serie.', 'Mano a mano, revancha.', 'Mano a mano.'])
            ->getContent();

        // Un solo renglón de serie para las tres, y las tres llevan la línea del grupo.
        $this->assertSame(1, substr_count($html, 'Al mejor de tres contra'));
        $this->assertSame(3, substr_count($html, 'border-l-texto'));

        // Quien la perdió lo lee desde su lado.
        $this->actingAs($dos)->get('/historial')->assertOk()->assertSee('Al mejor de tres contra El Zurdo')->assertSee('Perdiste la serie 2 a 1.');
    }

    public function test_el_historial_dice_que_la_serie_sigue_mientras_falta_una_partida(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        $primera = $this->partidaCorta($uno, $dos, enSerie: true);

        $this->ganar($primera, 0);

        $this->actingAs($uno)->get('/historial')->assertOk()->assertSee('La serie sigue: va 1 a 0.')->assertSee('Tenés una partida sin terminar.');
    }

    public function test_una_serie_que_se_corto_por_un_abandono_no_muestra_un_marcador_que_no_se_jugo(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        $primera = $this->partidaCorta($uno, $dos, enSerie: true);

        $this->ganar($primera, 1);
        $this->laMesaDeVerdad()->abandonar($this->laQueSiguioA($primera), 1);

        // Iba ganando quien se fue: la serie es del otro, y no se dice "1 a 1".
        $this->actingAs($uno)->get('/historial')->assertOk()->assertSee('Ganaste la serie.')->assertDontSee('Ganaste la serie 1');
        $this->actingAs($dos)->get('/historial')->assertOk()->assertSee('Perdiste la serie.');
    }

    public function test_si_se_va_quien_iba_perdiendo_tampoco_se_muestra_un_dos_a_cero_que_no_se_jugo(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        $primera = $this->partidaCorta($uno, $dos, enSerie: true);

        $this->ganar($primera, 0);
        $this->laMesaDeVerdad()->abandonar($this->laQueSiguioA($primera), 1);

        // La partida abandonada le cuenta como ganada a quien se quedó, pero la serie no se jugó hasta el final.
        $this->actingAs($uno)->get('/historial')->assertOk()->assertSee('Ganaste la serie.')->assertDontSee('Ganaste la serie 2');
        $this->actingAs($dos)->get('/historial')->assertOk()->assertSee('Perdiste la serie.')->assertDontSee('Perdiste la serie 2');
    }

    public function test_un_pedido_de_la_mesa_de_una_partida_que_ya_termino_no_cae_en_la_siguiente_de_la_serie(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        $primera = $this->partidaCorta($uno, $dos, enSerie: true);

        $this->ganar($primera, 0);
        $segunda = $this->laQueSiguioA($primera);

        // Una pestaña que todavía muestra la primera manda una jugada, pide repartir, avisa que llegó o abandona.
        // Dice de qué partida habla, y esa ya no se juega: la segunda no se toca.
        $this->actingAs($dos)->postJson('/mesa/accion', ['tipo' => 'mazo', 'partida' => $primera->id])->assertStatus(409);
        $this->actingAs($dos)->postJson('/mesa/accion', ['tipo' => 'mazo', 'partida' => $primera->id, 'desde' => 1])->assertStatus(409);
        $this->actingAs($dos)->postJson('/mesa/repartir', ['partida' => $primera->id])->assertStatus(409);
        $this->actingAs($dos)->postJson('/mesa/presente', ['partida' => $primera->id, 'desde' => 1])->assertStatus(409);
        $this->actingAs($dos)->postJson('/mesa/plazo', ['partida' => $primera->id])->assertStatus(409);
        $this->actingAs($dos)->getJson("/mesa/estado?partida={$primera->id}")->assertStatus(409);
        $this->actingAs($dos)->post('/mesa/abandonar', ['partida' => $primera->id])->assertRedirect(route('mesa'));

        $this->assertTrue($segunda->fresh()->enCurso());
        $this->assertSame(1, $segunda->eventos()->count());

        // Con el número de la segunda, o sin número, los pedidos entran en la segunda.
        $this->actingAs($dos)->getJson("/mesa/estado?partida={$segunda->id}")->assertOk()->assertJsonPath('vista.partida', $segunda->id);
        $this->actingAs($dos)->getJson('/mesa/estado')->assertOk()->assertJsonPath('vista.partida', $segunda->id);

        // Y la partida de otros no existe para quien no la juega.
        $this->actingAs(Jugador::factory()->create())->postJson('/mesa/accion', ['tipo' => 'mazo', 'partida' => $segunda->id])->assertStatus(409);
    }
}
