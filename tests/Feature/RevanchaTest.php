<?php

namespace Tests\Feature;

use App\Events\RevanchaActualizada;
use App\Juego\Nivel;
use App\Juego\Revanchas;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Revancha;
use App\Models\Serie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * La revancha: uno la pide, el otro contesta, y solo un "quiero" crea una partida nueva.
 */
class RevanchaTest extends TestCase
{
    use JugandoPartidas, PartidasCortas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Los plazos y el turno del bot quedan en la cola sin correr.
        Queue::fake();
    }

    public function test_una_revancha_aceptada_crea_una_partida_nueva_con_los_mismos_dos_jugadores(): void
    {
        [$uno, $dos, $partida] = $this->partidaTerminada();

        $this->assertSame(Revanchas::PEDIDA, $this->revanchas()->pedir($partida, 0)['estado']);
        $this->assertSame(Revanchas::ACEPTADA, $this->revanchas()->aceptar($partida, 1)['estado']);

        $nueva = $this->laQueSiguioA($partida);

        $this->assertNotNull($nueva);
        $this->assertTrue($nueva->enCurso());
        $this->assertTrue($nueva->entre_personas);
        // Los mismos dos, cada uno en el asiento que tenía.
        $this->assertSame([$uno->id, $dos->id], [$nueva->jugador_id, $nueva->invitado_id]);
        $this->assertSame($partida->puntos, $nueva->puntos);
        // Ya repartida, y con su propia lista de eventos: la vieja no se tocó.
        $this->assertSame([EventoDePartida::REPARTO], $nueva->eventos()->pluck('tipo')->all());
        $this->assertSame(Partida::TERMINADA, $partida->fresh()->estado);
        // Los dos la ven aceptada: sus mesas pasan a la partida nueva.
        $this->assertSame(Revanchas::ACEPTADA, $this->revanchas()->estado($partida, 0)['estado']);
        $this->assertSame(Revanchas::ACEPTADA, $this->revanchas()->estado($partida, 1)['estado']);
    }

    public function test_en_la_revancha_arranca_de_mano_quien_no_lo_fue_en_la_anterior(): void
    {
        [, , $partida] = $this->partidaTerminada();

        $this->revanchas()->pedir($partida, 1);
        $this->revanchas()->aceptar($partida, 0);

        $this->assertSame(1 - $partida->primer_mano, $this->laQueSiguioA($partida)->primer_mano);
    }

    public function test_rechazarla_no_crea_nada_y_ya_no_se_puede_volver_a_pedir(): void
    {
        [, , $partida] = $this->partidaTerminada();

        $this->revanchas()->pedir($partida, 0);

        $this->assertSame(['estado' => Revanchas::RECHAZADA, 'restan' => null, 'por' => 'vos'], $this->revanchas()->rechazar($partida, 1));
        $this->assertSame(['estado' => Revanchas::RECHAZADA, 'restan' => null, 'por' => 'rival'], $this->revanchas()->estado($partida, 0));

        // Ninguno de los dos puede insistir.
        $this->assertSame(Revanchas::RECHAZADA, $this->revanchas()->pedir($partida, 0)['estado']);
        $this->assertSame(Revanchas::RECHAZADA, $this->revanchas()->pedir($partida, 1)['estado']);
        $this->assertSame(Revanchas::RECHAZADA, $this->revanchas()->aceptar($partida, 1)['estado']);

        $this->assertSame(1, Partida::query()->count());
        $this->assertSame(1, Revancha::query()->count());
    }

    public function test_si_quien_la_pidio_la_cancela_o_se_va_no_se_crea_nada(): void
    {
        [, , $partida] = $this->partidaTerminada();

        $this->revanchas()->pedir($partida, 0);

        $this->assertSame(Revanchas::TE_PIDEN, $this->revanchas()->estado($partida, 1)['estado']);
        $this->assertSame(['estado' => Revanchas::AGOTADA, 'restan' => null, 'por' => 'vos'], $this->revanchas()->cancelar($partida, 0));

        // El otro ya no la tiene a la vista, y un "quiero" que llega tarde no crea nada.
        $this->assertSame(Revanchas::DISPONIBLE, $this->revanchas()->estado($partida, 1)['estado']);
        $this->assertSame(Revanchas::DISPONIBLE, $this->revanchas()->aceptar($partida, 1)['estado']);
        $this->assertSame(1, Partida::query()->count());
    }

    public function test_si_nadie_contesta_en_un_minuto_el_pedido_se_vence_solo(): void
    {
        $this->travelTo(now()->startOfSecond());
        [, , $partida] = $this->partidaTerminada();

        $this->assertSame(Revanchas::SEGUNDOS_PARA_CONTESTAR, $this->revanchas()->pedir($partida, 0)['restan']);

        $this->travel(20)->seconds();
        $this->assertSame(['estado' => Revanchas::TE_PIDEN, 'restan' => Revanchas::SEGUNDOS_PARA_CONTESTAR - 20, 'por' => null], $this->revanchas()->estado($partida, 1));

        $this->travel(Revanchas::SEGUNDOS_PARA_CONTESTAR)->seconds();

        // Quien la pidió se entera de que no le contestaron y no puede volver a pedirla.
        $this->assertSame(['estado' => Revanchas::AGOTADA, 'restan' => null, 'por' => 'rival'], $this->revanchas()->estado($partida, 0));
        $this->assertSame(Revanchas::AGOTADA, $this->revanchas()->pedir($partida, 0)['estado']);
        // Aceptar un pedido vencido no crea nada.
        $this->assertSame(Revanchas::DISPONIBLE, $this->revanchas()->aceptar($partida, 1)['estado']);
        $this->assertSame(1, Partida::query()->count());

        // El otro, que no la había pedido, todavía puede.
        $this->assertSame(Revanchas::PEDIDA, $this->revanchas()->pedir($partida, 1)['estado']);
        $this->assertSame(Revanchas::TE_PIDEN, $this->revanchas()->estado($partida, 0)['estado']);
    }

    public function test_si_los_dos_la_piden_la_segunda_vale_como_aceptar(): void
    {
        [, , $partida] = $this->partidaTerminada();

        $this->revanchas()->pedir($partida, 0);

        $this->assertSame(Revanchas::ACEPTADA, $this->revanchas()->pedir($partida, 1)['estado']);
        $this->assertSame(2, Partida::query()->count());
    }

    public function test_aceptar_dos_veces_no_crea_dos_partidas(): void
    {
        [, , $partida] = $this->partidaTerminada();

        $this->revanchas()->pedir($partida, 0);
        $this->revanchas()->aceptar($partida, 1);
        $this->revanchas()->aceptar($partida, 1);
        $this->revanchas()->pedir($partida, 0);

        $this->assertSame(2, Partida::query()->count());
    }

    public function test_nadie_acepta_ni_rechaza_su_propio_pedido(): void
    {
        [, , $partida] = $this->partidaTerminada();

        $this->revanchas()->pedir($partida, 0);

        $this->assertSame(Revanchas::PEDIDA, $this->revanchas()->aceptar($partida, 0)['estado']);
        $this->assertSame(Revanchas::PEDIDA, $this->revanchas()->rechazar($partida, 0)['estado']);
        // Y quien la recibió no la puede cancelar: lo suyo es contestar.
        $this->assertSame(Revanchas::TE_PIDEN, $this->revanchas()->cancelar($partida, 1)['estado']);
        $this->assertSame(1, Partida::query()->count());
    }

    public function test_de_una_partida_abandonada_o_sin_terminar_no_hay_revancha(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        [$enCurso] = $this->partidaEntrePersonasHasta($uno, $dos, 15);

        $this->assertSame(Revanchas::NO_DISPONIBLE, $this->revanchas()->pedir($enCurso, 0)['estado']);

        $this->laMesa()->abandonar($enCurso, 1);

        $this->assertSame(Revanchas::NO_DISPONIBLE, $this->revanchas()->pedir($enCurso->fresh(), 0)['estado']);
        $this->assertSame(0, Revancha::query()->count());
        $this->assertSame(1, Partida::query()->count());
    }

    public function test_si_alguno_ya_esta_en_otra_partida_no_hay_revancha(): void
    {
        [, $dos, $partida] = $this->partidaTerminada();

        $this->revanchas()->pedir($partida, 0);

        // Mientras el pedido espera, el otro se pone a jugar contra el bot.
        $otra = $this->laMesa()->abrir($dos);

        // El "quiero" no crea nada, y el pedido deja de estar a la vista.
        $this->assertSame(Revanchas::NO_DISPONIBLE, $this->revanchas()->aceptar($partida, 1)['estado']);
        $this->assertNull($this->laQueSiguioA($partida));
        // A quien la pidió no se le dice que la canceló ni que no le contestaron: no pasó ninguna de las dos.
        $this->assertSame(['estado' => Revanchas::AGOTADA, 'restan' => null, 'por' => null], $this->revanchas()->estado($partida, 0));

        // Tampoco se puede pedir desde el otro lado.
        $this->assertSame(Revanchas::NO_DISPONIBLE, $this->revanchas()->pedir($partida, 1)['estado']);
        $this->assertTrue($otra->fresh()->enCurso());
    }

    public function test_mientras_la_serie_sigue_no_hay_revancha_y_al_cerrarse_la_revancha_es_otra_serie(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        $primera = $this->partidaCorta($uno, $dos, enSerie: true);

        $this->ganar($primera, 0);

        // Va uno a cero: la siguiente ya está repartida, no hay nada que pedir.
        $this->assertSame(Revanchas::NO_DISPONIBLE, $this->revanchas()->pedir($primera->fresh(), 1)['estado']);

        $segunda = $this->laQueSiguioA($primera);
        $this->ganar($segunda, 0);

        // Dos a cero: la serie se cerró. La revancha es otra serie igual, con el mano alternado.
        $this->revanchas()->pedir($segunda->fresh(), 1);
        $this->assertSame(Revanchas::ACEPTADA, $this->revanchas()->aceptar($segunda->fresh(), 0)['estado']);

        $nueva = $this->laQueSiguioA($segunda);

        $this->assertSame(2, Serie::query()->count());
        $this->assertNotNull($nueva->serie_id);
        $this->assertNotSame($segunda->serie_id, $nueva->serie_id);
        $this->assertFalse($nueva->serie->cerrada());
        $this->assertSame([0, 0], $nueva->serie->marcador());
        $this->assertSame(1 - $segunda->primer_mano, $nueva->primer_mano);
    }

    public function test_contra_el_bot_la_revancha_se_crea_al_pedirla_contra_el_mismo_nivel(): void
    {
        $jugador = Jugador::factory()->create();
        [$partida] = $this->partidaContraElBot($jugador, Nivel::Dificil);

        $this->assertSame(Revanchas::ACEPTADA, $this->revanchas()->pedir($partida, 0)['estado']);

        $nueva = $this->laQueSiguioA($partida);

        $this->assertTrue($nueva->enCurso());
        $this->assertFalse($nueva->entre_personas);
        $this->assertSame(Nivel::Dificil, $nueva->nivel_bot);
        $this->assertSame($jugador->id, $nueva->jugador_id);
        $this->assertNull($nueva->invitado_id);
        $this->assertSame(1 - $partida->primer_mano, $nueva->primer_mano);
        // Contra el bot no queda ningún pedido: no hay a quién preguntarle.
        $this->assertSame(0, Revancha::query()->count());

        // Pedirla de nuevo no crea otra.
        $this->revanchas()->pedir($partida, 0);
        $this->assertSame(2, Partida::query()->count());
    }

    public function test_cada_cambio_le_avisa_al_otro_sin_decirle_nada(): void
    {
        [, , $partida] = $this->partidaTerminada();
        Event::fake([RevanchaActualizada::class]);

        $this->revanchas()->pedir($partida, 0);
        // Preguntar, o un pedido que no cambia nada, no avisa.
        $this->revanchas()->estado($partida, 1);
        $this->revanchas()->pedir($partida, 0);

        Event::assertDispatchedTimes(RevanchaActualizada::class, 1);

        $this->revanchas()->rechazar($partida, 1);

        Event::assertDispatchedTimes(RevanchaActualizada::class, 2);
        Event::assertDispatched(RevanchaActualizada::class, function (RevanchaActualizada $aviso) use ($partida) {
            // Va por el canal privado de la partida y no lleva datos.
            return $aviso->partida === $partida->id
                && $aviso->broadcastOn()[0]->name === "private-partida.{$partida->id}"
                && $aviso->broadcastWith() === [];
        });
    }

    private function revanchas(): Revanchas
    {
        return $this->app->make(Revanchas::class);
    }

    /**
     * Dos jugadores y una partida entre ellos que llegó al final.
     *
     * @return array{0: Jugador, 1: Jugador, 2: Partida}
     */
    private function partidaTerminada(): array
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];

        return [$uno, $dos, $this->partidaEntrePersonas($uno, $dos)];
    }
}
