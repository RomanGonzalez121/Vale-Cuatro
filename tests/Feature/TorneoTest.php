<?php

namespace Tests\Feature;

use App\Jobs\AvanzarTorneo;
use App\Juego\Estadisticas;
use App\Juego\JugadoresDeEjemplo;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Juego\TorneoNoDisponible;
use App\Juego\Torneos;
use App\Models\CruceDeTorneo;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Resultado;
use App\Models\Torneo;
use App\Motor\Carta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * El torneo relámpago contra bots: una persona y tres o siete bots, a eliminación directa.
 */
class TorneoTest extends TestCase
{
    use RefreshDatabase, TorneosDePrueba;

    protected function setUp(): void
    {
        parent::setUp();

        // Nada corre solo: el bot juega cuando el test lo mueve, y las llaves avanzan cuando el test lo pide.
        Queue::fake();
    }

    public function test_las_llaves_no_repiten_jugador(): void
    {
        foreach (Torneos::LUGARES as $lugares) {
            $torneo = $this->torneos()->crear(Jugador::factory()->create(), $lugares, 11);
            $primera = $torneo->cruces()->where('ronda', 1)->get();

            // Cada lugar aparece una sola vez en la primera ronda, y están todos.
            $sorteados = $primera->flatMap(fn (CruceDeTorneo $cruce) => [$cruce->uno, $cruce->dos])->sort()->values()->all();

            $this->assertSame(range(0, $lugares - 1), $sorteados);
            $this->assertCount($lugares / 2, $primera);

            // Hay una sola persona, y ningún bot está dos veces.
            $bots = array_filter(array_column($torneo->inscriptos, 'apodo'));

            $this->assertCount($lugares - 1, $bots);
            $this->assertCount($lugares - 1, array_unique($bots));
            $this->assertNull($torneo->inscriptos[$torneo->lugarDeLaPersona()]['apodo']);
        }
    }

    public function test_los_bots_son_los_jugadores_de_ejemplo_del_ranking_cada_uno_con_su_nivel(): void
    {
        $torneo = $this->torneos()->crear(Jugador::factory()->create(), 8, 3);
        $deEjemplo = JugadoresDeEjemplo::lista();

        foreach ($torneo->inscriptos as $lugar => $inscripto) {
            if ($lugar === $torneo->lugarDeLaPersona()) {
                $this->assertNull($torneo->nivelDe($lugar));

                continue;
            }

            $this->assertArrayHasKey($inscripto['apodo'], $deEjemplo);
            $this->assertSame($deEjemplo[$inscripto['apodo']], $torneo->nivelDe($lugar));
        }
    }

    public function test_la_carta_de_cada_bot_dice_su_nivel_y_la_de_la_persona_es_el_cuatro_de_copas(): void
    {
        $cartas = JugadoresDeEjemplo::cartas();
        $niveles = JugadoresDeEjemplo::lista();

        // Cada jugador de ejemplo tiene su carta, ninguna se repite y ninguno tiene la de la persona.
        $this->assertSame(array_keys($niveles), array_keys($cartas));
        $this->assertCount(count($cartas), array_unique($cartas));
        $this->assertNotContains(Torneos::CARTA_DE_LA_PERSONA, $cartas);

        // Cuanto más difícil el bot, más alta su carta en el truco: ninguno de un nivel le gana con la carta a uno de un nivel más alto.
        foreach ($cartas as $apodo => $carta) {
            foreach ($cartas as $otro => $otra) {
                if ($niveles[$apodo]->value > $niveles[$otro]->value) {
                    $this->assertTrue(Carta::de($carta)->leGanaA(Carta::de($otra)), "La carta de {$apodo} tiene que ganarle a la de {$otro}.");
                }
            }
        }

        // La persona empieza de abajo: su carta no le gana a la de ningún bot.
        foreach ($cartas as $carta) {
            $this->assertFalse(Carta::de(Torneos::CARTA_DE_LA_PERSONA)->leGanaA(Carta::de($carta)));
        }

        // Y en un torneo, cada lugar tiene la suya.
        $torneo = $this->torneos()->crear(Jugador::factory()->create(), 8, 3);

        foreach ($torneo->inscriptos as $lugar => $inscripto) {
            $esperada = $lugar === $torneo->lugarDeLaPersona() ? Torneos::CARTA_DE_LA_PERSONA : $cartas[$inscripto['apodo']];
            [$palo, $numero] = $torneo->cartaDe($lugar);

            $this->assertSame($esperada, "{$numero}-{$palo}");
        }
    }

    public function test_si_la_persona_tiene_el_apodo_de_un_bot_ese_bot_no_entra(): void
    {
        $jugador = Jugador::factory()->create(['apodo' => 'Don Anselmo']);

        foreach ([1, 2, 3, 4, 5] as $semilla) {
            $torneo = (new Torneos(new Mesa))->crear($jugador, 8, $semilla);

            $this->assertNotContains('Don Anselmo', array_column($torneo->inscriptos, 'apodo'));

            $torneo->delete();
        }
    }

    public function test_un_torneo_solo_puede_ser_de_cuatro_o_de_ocho(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->torneos()->crear(Jugador::factory()->create(), 6);
    }

    public function test_una_persona_juega_un_torneo_a_la_vez(): void
    {
        $jugador = Jugador::factory()->create();

        $uno = $this->torneos()->crear($jugador, 4);
        $otro = $this->torneos()->crear($jugador, 8);

        $this->assertTrue($uno->is($otro));
        $this->assertSame(4, $otro->lugares);
        $this->assertSame(1, Torneo::query()->count());
    }

    public function test_la_partida_de_la_persona_va_a_quince_contra_el_bot_que_le_toco(): void
    {
        $jugador = Jugador::factory()->create();
        $torneo = $this->torneos()->crear($jugador, 4, 21);
        $cruce = $this->torneos()->cruceDeLaPersona($torneo);
        $rival = $cruce->rivalDe($torneo->lugarDeLaPersona());

        $partida = $this->torneos()->jugar($torneo);

        $this->assertTrue($partida->enCurso());
        $this->assertSame(Torneos::PUNTOS, $partida->puntos);
        $this->assertSame($torneo->id, $partida->torneo_id);
        $this->assertSame($jugador->id, $partida->jugador_id);
        $this->assertFalse($partida->entre_personas);
        $this->assertSame($torneo->nivelDe($rival), $partida->nivel_bot);
        $this->assertSame($partida->id, $cruce->fresh()->partida_id);
        // Ya está repartida, y es la partida en curso del jugador.
        $this->assertSame(1, $partida->eventos()->count());
        $this->assertTrue($this->mesaDePrueba()->enCursoDe($jugador)->is($partida));

        // Volver a pedirla no abre otra.
        $this->assertTrue($this->torneos()->jugar($torneo)->is($partida));
        $this->assertSame(1, Partida::query()->count());
    }

    public function test_no_se_juega_la_del_torneo_con_otra_partida_sin_terminar(): void
    {
        $jugador = Jugador::factory()->create();
        $torneo = $this->torneos()->crear($jugador, 4);
        $this->mesaDePrueba()->abrir($jugador, Nivel::Facil);

        try {
            $this->torneos()->jugar($torneo);
            $this->fail('Con otra partida abierta no se podía empezar la del torneo.');
        } catch (TorneoNoDisponible) {
            $this->assertSame(1, Partida::query()->count());
            $this->assertNull($this->torneos()->cruceDeLaPersona($torneo)->partida_id);
        }
    }

    public function test_mientras_la_persona_juega_su_partida_las_demas_de_la_ronda_no_se_resuelven(): void
    {
        $torneo = $this->torneoCorto(Jugador::factory()->create(), 8);

        $this->torneos()->jugar($torneo);
        $this->torneos()->avanzar($torneo->id);

        $this->assertSame(0, $torneo->cruces()->whereNotNull('ganador')->count());
        $this->assertTrue($torneo->fresh()->enCurso());
    }

    public function test_al_cerrarse_la_partida_de_la_persona_las_llaves_se_ponen_al_dia_desde_la_cola(): void
    {
        $torneo = $this->torneoCorto(Jugador::factory()->create());
        $partida = $this->torneos()->jugar($torneo);

        $this->cerrarContraElBot($partida, ganaLaPersona: true);

        Queue::assertPushed(AvanzarTorneo::class, fn (AvanzarTorneo $trabajo) => $trabajo->torneoId === $torneo->id);
    }

    public function test_quien_gana_su_partida_pasa_de_ronda_contra_el_ganador_del_otro_cruce(): void
    {
        $torneo = $this->torneoCorto(Jugador::factory()->create());
        $persona = $torneo->lugarDeLaPersona();

        $partida = $this->jugarLaRonda($torneo, gana: true);

        $this->assertSame(Mesa::JUGADOR, $partida->ganador);

        [$suyo, $otro] = $torneo->cruces()->where('ronda', 1)->get()->partition(fn (CruceDeTorneo $cruce) => $cruce->loJuega($persona))->map->first();
        $final = $torneo->cruces()->where('ronda', 2)->sole();

        $this->assertSame($persona, $suyo->ganador);
        $this->assertFalse($suyo->por_abandono);
        // La otra semifinal se jugó entre bots, a los puntos del torneo, y tiene su tanteo.
        $this->assertNotNull($otro->ganador);
        $this->assertGreaterThanOrEqual(1, max($otro->puntos_uno, $otro->puntos_dos));
        // En la final están los dos ganadores, cada uno del lado de las llaves del que viene.
        $this->assertSame([$torneo->cruces()->where('ronda', 1)->where('orden', 0)->sole()->ganador, $torneo->cruces()->where('ronda', 1)->where('orden', 1)->sole()->ganador], [$final->uno, $final->dos]);
        $this->assertTrue($final->loJuega($persona));
        $this->assertNull($final->ganador);
        $this->assertTrue($torneo->fresh()->enCurso());
        $this->assertTrue($this->torneos()->cruceDeLaPersona($torneo)->is($final));
    }

    public function test_quien_gana_todas_sus_partidas_es_el_campeon(): void
    {
        $torneo = $this->torneoCorto(Jugador::factory()->create(), 8);

        $this->jugarLaRonda($torneo, gana: true);
        $this->jugarLaRonda($torneo, gana: true);

        $this->assertTrue($torneo->fresh()->enCurso());

        $this->jugarLaRonda($torneo, gana: true);

        $torneo->refresh();

        $this->assertFalse($torneo->enCurso());
        $this->assertSame($torneo->lugarDeLaPersona(), $torneo->campeon);
        $this->assertNotNull($torneo->terminado_en);
        // Tres partidas suyas, cada una en su cruce.
        $this->assertSame(3, Partida::query()->where('torneo_id', $torneo->id)->count());
        $this->assertSame(3, $torneo->cruces()->whereNotNull('partida_id')->count());
    }

    public function test_quien_pierde_su_partida_queda_afuera_y_el_torneo_se_termina_solo(): void
    {
        $torneo = $this->torneoCorto(Jugador::factory()->create(), 8);
        $persona = $torneo->lugarDeLaPersona();

        $partida = $this->jugarLaRonda($torneo, gana: false);

        $this->assertSame(Partida::TERMINADA, $partida->estado);
        $this->assertSame(Mesa::BOT, $partida->ganador);

        $torneo->refresh();

        // Lo que faltaba se jugó de corrido entre bots: hay campeón, y no es la persona.
        $this->assertFalse($torneo->enCurso());
        $this->assertNotNull($torneo->campeon);
        $this->assertNotSame($persona, $torneo->campeon);
        $this->assertNull($this->torneos()->cruceDeLaPersona($torneo));
        $this->assertSame(0, $torneo->cruces()->where('ronda', '>', 1)->get()->filter(fn (CruceDeTorneo $cruce) => $cruce->loJuega($persona))->count());
    }

    public function test_quien_abandona_su_partida_queda_eliminado(): void
    {
        $jugador = Jugador::factory()->create();
        $torneo = $this->torneoCorto($jugador);
        $persona = $torneo->lugarDeLaPersona();
        $partida = $this->torneos()->jugar($torneo);

        $this->mesaDePrueba()->abandonar($partida);
        $this->torneos()->avanzar($torneo->id);

        $suyo = $torneo->cruces()->where('partida_id', $partida->id)->sole();

        $this->assertSame($suyo->rivalDe($persona), $suyo->ganador);
        $this->assertTrue($suyo->por_abandono);
        $this->assertFalse($torneo->fresh()->enCurso());
        $this->assertNotSame($persona, $torneo->fresh()->campeon);

        // Ya no tiene nada por jugar ahí.
        $this->expectException(TorneoNoDisponible::class);
        $this->torneos()->jugar($torneo);
    }

    public function test_dejar_el_torneo_en_medio_de_una_partida_es_abandonarla(): void
    {
        $torneo = $this->torneoCorto(Jugador::factory()->create());
        $partida = $this->torneos()->jugar($torneo);

        $this->torneos()->abandonar($torneo);

        $this->assertSame(Partida::ABANDONADA, $partida->fresh()->estado);
        $this->assertSame(Mesa::BOT, $partida->fresh()->ganador);
        $this->assertFalse($torneo->fresh()->enCurso());
    }

    public function test_dejar_el_torneo_entre_dos_rondas_hace_pasar_al_rival_sin_jugar(): void
    {
        $torneo = $this->torneoCorto(Jugador::factory()->create());
        $persona = $torneo->lugarDeLaPersona();

        $this->jugarLaRonda($torneo, gana: true);
        $this->torneos()->abandonar($torneo);

        $final = $torneo->cruces()->where('ronda', 2)->sole();
        $torneo->refresh();

        $this->assertSame($final->rivalDe($persona), $final->ganador);
        $this->assertTrue($final->por_abandono);
        $this->assertNull($final->partida_id);
        $this->assertFalse($torneo->enCurso());
        $this->assertSame($final->ganador, $torneo->campeon);

        // Dejarlo de nuevo no cambia nada.
        $this->torneos()->abandonar($torneo);
        $this->assertSame($final->ganador, $torneo->fresh()->campeon);
    }

    public function test_si_la_administracion_cierra_su_partida_por_quieta_pasa_el_bot(): void
    {
        $torneo = $this->torneoCorto(Jugador::factory()->create());
        $persona = $torneo->lugarDeLaPersona();
        $partida = $this->torneos()->jugar($torneo);

        $this->travel(Mesa::HORAS_QUIETA_CONTRA_EL_BOT + 1)->hours();
        $this->assertTrue($this->mesaDePrueba()->cerrarQuieta($partida));
        $this->torneos()->avanzar($torneo->id);

        $suyo = $torneo->cruces()->where('partida_id', $partida->id)->sole();

        $this->assertSame($suyo->rivalDe($persona), $suyo->ganador);
        $this->assertTrue($suyo->por_abandono);
        $this->assertFalse($torneo->fresh()->enCurso());
    }

    public function test_un_torneo_de_ocho_termina_en_siete_partidas_con_un_solo_campeon(): void
    {
        foreach ([4 => 3, 8 => 7] as $lugares => $partidas) {
            // A quince, como un torneo de verdad: la persona pierde la primera y el resto se juega entre bots.
            $torneo = $this->torneos()->crear(Jugador::factory()->create(), $lugares, 40 + $lugares);
            $this->mesaDePrueba()->abandonar($this->torneos()->jugar($torneo));
            $this->torneos()->avanzar($torneo->id);

            $torneo->refresh();
            $cruces = $torneo->cruces()->get();

            $this->assertCount($partidas, $cruces);
            $this->assertTrue($cruces->every(fn (CruceDeTorneo $cruce) => $cruce->resuelto() && $cruce->loJuega($cruce->ganador)));
            $this->assertFalse($torneo->enCurso());

            // El campeón es quien ganó la final, y es el único que ganó todas las que jugó.
            $final = $cruces->last();
            $invictos = collect(range(0, $lugares - 1))->filter(
                fn (int $lugar) => $cruces->filter(fn (CruceDeTorneo $cruce) => $cruce->loJuega($lugar))->every(fn (CruceDeTorneo $cruce) => $cruce->ganador === $lugar),
            );

            $this->assertSame($torneo->rondas(), $final->ronda);
            $this->assertSame($final->ganador, $torneo->campeon);
            $this->assertSame([$torneo->campeon], $invictos->values()->all());

            // Las partidas entre bots se jugaron a los puntos del torneo: alguien llegó a quince.
            foreach ($cruces->whereNull('partida_id') as $cruce) {
                $this->assertGreaterThanOrEqual(Torneos::PUNTOS, max($cruce->puntos_uno, $cruce->puntos_dos));
                $this->assertLessThan(Torneos::PUNTOS, min($cruce->puntos_uno, $cruce->puntos_dos));
            }
        }
    }

    public function test_cada_ronda_empareja_a_los_ganadores_de_la_anterior(): void
    {
        $torneo = $this->torneos()->crear(Jugador::factory()->create(), 8, 5);
        $this->torneos()->abandonar($torneo);

        $rondas = $torneo->cruces()->get()->groupBy('ronda');

        $this->assertSame([4, 2, 1], $rondas->map->count()->values()->all());

        foreach ([2, 3] as $ronda) {
            foreach ($rondas[$ronda] as $cruce) {
                $anteriores = $rondas[$ronda - 1]->keyBy('orden');

                $this->assertSame($anteriores[$cruce->orden * 2]->ganador, $cruce->uno);
                $this->assertSame($anteriores[$cruce->orden * 2 + 1]->ganador, $cruce->dos);
            }
        }
    }

    public function test_la_misma_semilla_da_el_mismo_sorteo_y_los_mismos_resultados_entre_bots(): void
    {
        $resultados = [];

        foreach ([9, 9, 10] as $semilla) {
            $torneo = $this->torneos()->crear(Jugador::factory()->create(), 8, $semilla);
            // La persona deja el torneo sin jugar: todo lo demás son partidas entre bots.
            $this->torneos()->abandonar($torneo);

            $resultados[] = [
                $torneo->fresh()->inscriptos,
                $torneo->fresh()->campeon,
                $torneo->cruces()->get()->map(fn (CruceDeTorneo $cruce) => [$cruce->uno, $cruce->dos, $cruce->ganador, $cruce->puntos_uno, $cruce->puntos_dos])->all(),
            ];
        }

        $this->assertSame($resultados[0], $resultados[1]);
        $this->assertNotSame($resultados[0], $resultados[2]);
    }

    public function test_avanzar_de_mas_no_cambia_nada(): void
    {
        $torneo = $this->torneoCorto(Jugador::factory()->create(), 8);
        $this->jugarLaRonda($torneo, gana: true);

        $antes = $torneo->cruces()->get()->toArray();

        $this->torneos()->avanzar($torneo->id);
        $this->torneos()->avanzar($torneo->id);

        $this->assertSame($antes, $torneo->cruces()->get()->toArray());
    }

    public function test_para_el_ranking_cuenta_la_partida_de_la_persona_y_no_las_de_bots(): void
    {
        $jugador = Jugador::factory()->create();
        $torneo = $this->torneos()->crear($jugador, 8, 14);
        $partida = $this->torneos()->jugar($torneo);

        $this->mesaDePrueba()->abandonar($partida);
        $this->torneos()->avanzar($torneo->id);

        // Se jugaron siete partidas y solo una es de una persona: las de bots no dejan nada en el ranking.
        $esperados = (new Estadisticas)->cuenta($partida->fresh()) ? 1 : 0;

        $this->assertSame($esperados, Resultado::query()->count());
        $this->assertSame(0, Resultado::query()->where('jugador_id', '!=', $jugador->id)->count());
        $this->assertSame(1, Partida::query()->count());
    }

    public function test_al_borrarse_el_jugador_se_va_su_torneo(): void
    {
        $jugador = Jugador::factory()->create();
        $torneo = $this->torneoCorto($jugador);
        $this->jugarLaRonda($torneo, gana: true);

        $jugador->delete();

        $this->assertSame(0, Torneo::query()->count());
        $this->assertSame(0, CruceDeTorneo::query()->count());
        $this->assertSame(0, Partida::query()->count());
    }
}
