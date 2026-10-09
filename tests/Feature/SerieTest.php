<?php

namespace Tests\Feature;

use App\Juego\Bot;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Resultado;
use App\Models\Serie;
use App\Motor\Accion;
use App\Motor\TipoDeAccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * La serie al mejor de tres: agrupa partidas entre los mismos dos, reparte la siguiente apenas termina
 * una y se cierra cuando alguien gana dos. Cada partida sigue siendo su propia lista de eventos.
 */
class SerieTest extends TestCase
{
    use PartidasCortas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Los plazos y el turno del bot quedan en la cola sin correr: cada test mueve las piezas a mano.
        Queue::fake();
    }

    public function test_al_terminar_una_partida_de_la_serie_se_reparte_la_siguiente_entre_los_mismos_dos(): void
    {
        [$uno, $dos] = $this->dosJugadores();
        $primera = $this->partidaCorta($uno, $dos, enSerie: true);

        $this->ganar($primera, 0);

        $segunda = $this->laQueSiguioA($primera);

        $this->assertNotNull($segunda);
        $this->assertTrue($segunda->enCurso());
        $this->assertSame($primera->serie_id, $segunda->serie_id);
        // Los mismos dos, cada uno en su asiento.
        $this->assertSame([$uno->id, $dos->id], [$segunda->jugador_id, $segunda->invitado_id]);
        $this->assertTrue($segunda->entre_personas);
        $this->assertSame($primera->puntos, $segunda->puntos);
        // Ya está repartida: su primer evento es el reparto, y no hay nada más.
        $this->assertSame([EventoDePartida::REPARTO], $segunda->eventos()->pluck('tipo')->all());
        // La serie sigue abierta, uno a cero.
        $this->assertFalse($segunda->serie->cerrada());
        $this->assertSame([1, 0], $segunda->serie->marcador());
    }

    public function test_en_cada_partida_de_la_serie_arranca_de_mano_quien_no_lo_fue_en_la_anterior(): void
    {
        [$uno, $dos] = $this->dosJugadores();
        $primera = $this->partidaCorta($uno, $dos, enSerie: true, primerMano: 1);

        $this->ganar($primera, 0);
        $segunda = $this->laQueSiguioA($primera);
        $this->ganar($segunda, 1);
        $tercera = $this->laQueSiguioA($segunda);

        $this->assertSame([1, 0, 1], [$primera->primer_mano, $segunda->primer_mano, $tercera->primer_mano]);
    }

    public function test_con_dos_a_cero_la_serie_se_cierra_y_no_hay_tercera(): void
    {
        [$uno, $dos] = $this->dosJugadores();
        $primera = $this->partidaCorta($uno, $dos, enSerie: true);

        $this->ganar($primera, 1);
        $segunda = $this->laQueSiguioA($primera);
        $this->ganar($segunda, 1);

        $serie = Serie::query()->sole();

        $this->assertTrue($serie->cerrada());
        $this->assertSame(1, $serie->ganador);
        $this->assertSame([0, 2], $serie->marcador());
        $this->assertNull($this->laQueSiguioA($segunda));
        $this->assertSame(2, Partida::query()->count());
        // Ninguno de los dos quedó con una partida abierta.
        $this->assertNull($this->laMesaDeVerdad()->abiertaDe($uno));
        $this->assertNull($this->laMesaDeVerdad()->abiertaDe($dos));
    }

    public function test_con_uno_a_uno_se_juega_la_tercera_y_ahi_se_cierra(): void
    {
        [$uno, $dos] = $this->dosJugadores();
        $primera = $this->partidaCorta($uno, $dos, enSerie: true);

        $this->ganar($primera, 0);
        $segunda = $this->laQueSiguioA($primera);
        $this->ganar($segunda, 1);
        $tercera = $this->laQueSiguioA($segunda);

        $this->assertNotNull($tercera);
        $this->assertFalse($tercera->serie->cerrada());

        $this->ganar($tercera, 0);

        $serie = Serie::query()->sole();

        $this->assertTrue($serie->cerrada());
        $this->assertSame(0, $serie->ganador);
        $this->assertSame([2, 1], $serie->marcador());
        // No hay cuarta.
        $this->assertNull($this->laQueSiguioA($tercera));
        $this->assertSame(3, Partida::query()->count());
    }

    public function test_quien_abandona_una_partida_pierde_la_serie_entera_y_no_se_reparte_otra(): void
    {
        [$uno, $dos] = $this->dosJugadores();
        $primera = $this->partidaCorta($uno, $dos, enSerie: true);

        $this->ganar($primera, 0);
        $segunda = $this->laQueSiguioA($primera);

        // Iba ganando la serie, y se va.
        $this->laMesaDeVerdad()->abandonar($segunda, 0);

        $serie = Serie::query()->sole();

        $this->assertTrue($serie->cerrada());
        $this->assertSame(1, $serie->ganador);
        $this->assertNull($this->laQueSiguioA($segunda));
    }

    public function test_quien_deja_vencer_sus_turnos_tambien_pierde_la_serie(): void
    {
        [$uno, $dos] = $this->dosJugadores();
        $partida = $this->partidaCorta($uno, $dos, enSerie: true);
        $mesa = $this->laMesaDeVerdad();

        // El asiento 0 es mano y no juega nunca. A un punto, el primer vencimiento ya le da la partida al
        // otro como un mazo cualquiera: para que pierda por dejar de jugar tiene que ser una partida larga.
        $partida->puntos = 30;
        $partida->save();

        for ($veces = 0; $veces < 20 && $partida->fresh()->enCurso(); $veces++) {
            $this->travelTo($partida->fresh()->plazo_vence_en->copy()->addSecond());
            $mesa->resolverPlazo($partida->id);
        }

        $this->assertSame(Partida::ABANDONADA, $partida->fresh()->estado);

        $serie = Serie::query()->sole();

        $this->assertTrue($serie->cerrada());
        $this->assertSame(1, $serie->ganador);
        $this->assertSame(1, Partida::query()->count());
    }

    public function test_una_partida_suelta_no_reparte_otra_al_terminar(): void
    {
        [$uno, $dos] = $this->dosJugadores();
        $partida = $this->partidaCorta($uno, $dos);

        $this->ganar($partida, 0);

        $this->assertSame(1, Partida::query()->count());
        $this->assertSame(0, Serie::query()->count());
    }

    public function test_cada_partida_de_la_serie_cuenta_por_separado_para_el_ranking(): void
    {
        [$uno, $dos] = $this->dosJugadores();
        $primera = $this->partidaCorta($uno, $dos, enSerie: true);

        $this->ganar($primera, 0);
        $segunda = $this->laQueSiguioA($primera);
        $this->ganar($segunda, 1);
        $this->ganar($this->laQueSiguioA($segunda), 0);

        // Tres partidas, un renglón por persona en cada una. Ganar la serie no suma nada aparte.
        $this->assertSame(6, Resultado::query()->count());
        $this->assertSame(2, Resultado::query()->where('jugador_id', $uno->id)->where('gano', true)->count());
        $this->assertSame(1, Resultado::query()->where('jugador_id', $dos->id)->where('gano', true)->count());
    }

    public function test_en_la_partida_que_sigue_a_cualquiera_de_los_dos_le_puede_faltar_llegar(): void
    {
        [$uno, $dos] = $this->dosJugadores();
        $primera = $this->partidaCorta($uno, $dos, enSerie: true);
        $mesa = $this->laMesaDeVerdad();

        // En la que nace de una sala, solo a quien la abrió.
        $this->assertTrue($mesa->faltaLlegar($primera, 0));
        $this->assertFalse($mesa->faltaLlegar($primera, 1));

        $this->travelTo(now()->startOfSecond());
        $this->ganar($primera, 0);
        $segunda = $this->laQueSiguioA($primera);

        // La segunda se repartió sola mientras los dos miraban el final de la primera.
        $this->assertTrue($mesa->faltaLlegar($segunda, 0));
        $this->assertTrue($mesa->faltaLlegar($segunda, 1));
        // Es mano el asiento 1, que no llegó: su turno espera lo que se espera una llegada.
        $this->assertSame(1, $segunda->primer_mano);
        $this->assertSame(Mesa::SEGUNDOS_DE_LLEGADA, $mesa->vista($segunda, 0)['restan']);

        $this->assertTrue($mesa->llegar($segunda, 1));
        $this->assertFalse($mesa->faltaLlegar($segunda, 1));
        $this->assertSame(Mesa::SEGUNDOS_DE_TURNO, $mesa->vista($segunda, 0)['restan']);
    }

    public function test_abrir_una_sala_al_mejor_de_tres_crea_la_serie_y_cancelarla_la_borra(): void
    {
        $quienInvita = Jugador::factory()->create();
        $mesa = $this->laMesaDeVerdad();

        $sala = $mesa->crearSala($quienInvita, enSerie: true);

        $this->assertNotNull($sala->serie_id);
        $this->assertTrue($sala->esperando());

        $this->assertTrue($mesa->cancelarSala($sala));

        // Nadie se sentó: no llegó a ser una serie.
        $this->assertSame(0, Serie::query()->count());
        $this->assertNull($sala->fresh()->serie_id);
    }

    public function test_una_sala_al_mejor_de_tres_que_se_vence_no_deja_una_serie_colgada(): void
    {
        $mesa = $this->laMesaDeVerdad();
        $vencida = $mesa->crearSala(Jugador::factory()->create(), enSerie: true);

        $this->travel(Mesa::MINUTOS_DE_SALA + 1)->minutes();

        // Otra, recién abierta, y una serie que ya se está jugando: esas no se tocan.
        $nueva = $mesa->crearSala(Jugador::factory()->create(), enSerie: true);
        [$uno, $dos] = $this->dosJugadores();
        $jugandose = $this->partidaCorta($uno, $dos, enSerie: true);

        $this->assertSame(1, $mesa->cerrarSalasVencidas());

        $this->assertNull($vencida->fresh()->serie_id);
        $this->assertNotNull($nueva->fresh()->serie_id);
        $this->assertNotNull($jugandose->fresh()->serie_id);
        $this->assertSame(2, Serie::query()->count());
    }

    public function test_una_serie_que_se_quedo_sin_partidas_se_va_con_la_limpieza(): void
    {
        // Un invitado juega una serie entera contra el bot y no vuelve: al borrarlo se van sus partidas.
        $invitado = Jugador::factory()->invitado()->create();
        $mesa = $this->laMesaDeVerdad();
        $partida = $mesa->abrir($invitado, Nivel::Facil, enSerie: true);
        $mesa->abandonar($partida);

        $this->assertTrue(Serie::query()->sole()->cerrada());

        // Otra serie cerrada, de alguien que sigue estando: esa queda.
        [$uno, $dos] = $this->dosJugadores();
        $queda = $this->partidaCorta($uno, $dos, enSerie: true);
        $mesa->abandonar($queda, 1);

        $invitado->delete();
        $mesa->cerrarSalasVencidas();

        $this->assertSame([$queda->serie_id], Serie::query()->pluck('id')->all());
    }

    public function test_quien_se_sienta_en_una_sala_al_mejor_de_tres_juega_la_primera_de_la_serie(): void
    {
        [$uno, $dos] = $this->dosJugadores();
        $mesa = $this->laMesaDeVerdad();

        $sala = $mesa->crearSala($uno, enSerie: true);
        $partida = $mesa->sentarse($sala->codigo, $dos);

        $this->assertTrue($partida->enCurso());
        $this->assertSame($sala->serie_id, $partida->serie_id);
        $this->assertSame([0, 0], $partida->serie->marcador());
    }

    public function test_contra_el_bot_la_serie_sigue_contra_el_mismo_nivel_y_se_cierra_a_las_dos_ganadas(): void
    {
        $jugador = Jugador::factory()->create();
        // Un bot que hace lo que el test le diga: irse al mazo, o tirar una carta.
        $bot = new class implements Bot
        {
            public bool $seVa = false;

            public function decidir(array $vista): Accion
            {
                $acciones = collect($vista['acciones']);

                return Accion::desdeArray($this->seVa ? ['tipo' => TipoDeAccion::Mazo->value] : $acciones->firstWhere('tipo', TipoDeAccion::Jugar->value));
            }
        };
        $mesa = new Mesa($bot);

        $primera = $mesa->abrir($jugador, Nivel::Dificil, enSerie: true);
        $primera->puntos = 1;
        $primera->save();

        $this->assertNotNull($primera->serie_id);

        // La primera la gana el bot: el jugador se va al mazo cuando le toca.
        $this->cerrarContraElBot($mesa, $bot, $primera, ganaElJugador: false);

        $segunda = $this->laQueSiguioA($primera);

        $this->assertNotNull($segunda);
        $this->assertFalse($segunda->entre_personas);
        $this->assertSame(Nivel::Dificil, $segunda->nivel_bot);
        $this->assertSame($jugador->id, $segunda->jugador_id);
        $this->assertSame(1 - $primera->primer_mano, $segunda->primer_mano);
        // Con una partida de la serie sin terminar, "Jugar" la retoma: no abre otra.
        $this->assertTrue($mesa->abrir($jugador, Nivel::Facil)->is($segunda));

        // La segunda y la tercera las gana el jugador.
        $this->cerrarContraElBot($mesa, $bot, $segunda, ganaElJugador: true);
        $tercera = $this->laQueSiguioA($segunda);
        $this->cerrarContraElBot($mesa, $bot, $tercera, ganaElJugador: true);

        $serie = Serie::query()->sole();

        $this->assertTrue($serie->cerrada());
        $this->assertSame(Mesa::JUGADOR, $serie->ganador);
        $this->assertSame([2, 1], $serie->marcador());
        $this->assertNull($this->laQueSiguioA($tercera));
    }

    /**
     * Juega una partida a un punto contra el bot de prueba hasta cerrarla, con el resultado que se pida.
     */
    private function cerrarContraElBot(Mesa $mesa, object $bot, Partida $partida, bool $ganaElJugador): void
    {
        $bot->seVa = $ganaElJugador;

        // Si el bot es mano, juega primero: se va (y ahí terminó) o tira una carta.
        $mesa->turnoDelBot($partida->id);

        if (! $partida->fresh()->enCurso()) {
            return;
        }

        if (! $ganaElJugador) {
            $mesa->actuar($partida, Accion::de(TipoDeAccion::Mazo));

            return;
        }

        $carta = collect($mesa->vista($partida)['acciones'])->firstWhere('tipo', TipoDeAccion::Jugar->value);

        $mesa->actuar($partida, Accion::desdeArray($carta));
        $mesa->turnoDelBot($partida->id);
    }

    /**
     * @return array{0: Jugador, 1: Jugador}
     */
    private function dosJugadores(): array
    {
        return [Jugador::factory()->create(), Jugador::factory()->create()];
    }
}
