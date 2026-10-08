<?php

namespace Tests\Unit;

use App\Juego\BotUltraDificil;
use App\Juego\Perfil;
use App\Motor\Azar;
use App\Motor\Mazo;
use App\Motor\Partida;
use App\Motor\TipoDeAccion;
use PHPUnit\Framework\TestCase;
use Tests\Motor\Jugando;

/**
 * Lo que el Ultra difícil aprende del rival con las manos que recuerda. El bot está en el asiento 1.
 */
class PerfilTest extends TestCase
{
    use Jugando;

    private const BOT = 1;

    /** El rival tiene 33 de copa; el bot, los dos anchos. */
    private const CON_TANTO = [['7-copa', '6-copa', '4-basto'], ['1-espada', '1-basto', '4-oro']];

    public function test_sin_manos_vistas_supone_lo_mismo_que_el_dificil(): void
    {
        $rival = Perfil::de([]);

        $this->assertSame(0.15, $rival->mienteElEnvido);
        $this->assertSame(0.25, $rival->seCallaTeniendo);
        $this->assertSame(0.4, $rival->seVa);
        $this->assertEqualsWithDelta(1.0, $rival->creibleElTruco(), 0.000001);
    }

    public function test_el_que_canta_envido_sin_tanto_queda_marcado(): void
    {
        // El rival es mano, canta envido con 6 y, querido, tiene que decir su tanto.
        $mintio = $this->cerrada($this->armada([['4-copa', '5-basto', '6-espada'], ['7-oro', '6-oro', '4-basto']]), '0 envido', '1 quiero', '0 mazo');
        // Acá lo canta con 33.
        $tenia = $this->cerrada($this->armada(self::CON_TANTO), '0 envido', '1 quiero', '0 mazo');

        $this->assertEqualsWithDelta((4 * 0.15 + 3) / 7, Perfil::de([$mintio, $mintio, $mintio])->mienteElEnvido, 0.000001);
        $this->assertEqualsWithDelta((4 * 0.15) / 7, Perfil::de([$tenia, $tenia, $tenia])->mienteElEnvido, 0.000001);
    }

    public function test_si_no_se_llego_a_saber_su_tanto_la_mano_no_cuenta(): void
    {
        // Cantó envido y el bot no quiso: nadie dijo su tanto y el rival no mostró sus cartas.
        $noQuerido = $this->cerrada($this->armada(self::CON_TANTO), '0 envido', '1 no_quiero', '0 mazo');

        $this->assertSame(0.15, Perfil::de([$noQuerido, $noQuerido])->mienteElEnvido);
    }

    public function test_el_que_juega_sus_tres_cartas_con_tanto_y_sin_cantar_se_lo_callo(): void
    {
        $callado = $this->cerrada(
            $this->armada(self::CON_TANTO),
            '0 7-copa', '1 4-oro', '0 6-copa', '1 1-basto', '1 1-espada', '0 4-basto',
        );

        $this->assertEqualsWithDelta((4 * 0.25 + 2) / 6, Perfil::de([$callado, $callado])->seCallaTeniendo, 0.000001);
    }

    public function test_el_que_canta_truco_y_pierde_la_mano_es_menos_creible(): void
    {
        $perdio = $this->cerrada(
            $this->armada(self::CON_TANTO),
            '0 truco', '1 quiero', '0 7-copa', '1 4-oro', '0 6-copa', '1 1-basto', '1 1-espada', '0 4-basto',
        );
        // Con los anchos del lado del rival, el truco lo gana él.
        $gano = $this->cerrada(
            $this->armada([['1-espada', '1-basto', '4-oro'], ['7-copa', '6-copa', '4-basto']]),
            '0 truco', '1 quiero', '0 1-espada', '1 4-basto', '0 1-basto', '1 6-copa',
        );

        $this->assertLessThan(1.0, Perfil::de([$perdio, $perdio])->creibleElTruco());
        $this->assertGreaterThan(1.0, Perfil::de([$gano, $gano, $gano])->creibleElTruco());
        $this->assertGreaterThanOrEqual(0.5, Perfil::de(array_fill(0, 20, $perdio))->creibleElTruco(), 'Nunca le cree menos de la mitad.');
    }

    public function test_cuenta_cuantas_veces_el_rival_no_quiso_lo_que_le_canto_el_bot(): void
    {
        $seFue = $this->cerrada($this->armada(self::CON_TANTO, mano: self::BOT), '1 truco', '0 no_quiero');
        $quiso = $this->cerrada($this->armada(self::CON_TANTO, mano: self::BOT), '1 truco', '0 quiero', '1 mazo');

        $this->assertEqualsWithDelta((4 * 0.4 + 3) / 7, Perfil::de([$seFue, $seFue, $seFue])->seVa, 0.000001);
        $this->assertEqualsWithDelta((4 * 0.4) / 7, Perfil::de([$quiso, $quiso, $quiso])->seVa, 0.000001);
    }

    public function test_un_envido_anulado_por_una_flor_no_cuenta_como_que_el_rival_se_fue(): void
    {
        // El bot canta envido y el rival le contesta con flor: el envido queda anulado, nadie lo quiso ni lo rechazó.
        $anulado = $this->cerrada(
            $this->armada([['7-copa', '6-copa', '4-copa'], ['3-copa', '2-espada', '5-basto']], mano: self::BOT),
            '1 envido', '0 flor', '1 mazo',
        );

        $this->assertSame('anulado', $anulado['envido']['estado']);
        $this->assertEqualsWithDelta(0.4, Perfil::de([$anulado, $anulado, $anulado])->seVa, 0.000001, 'No vio nada: queda lo que se supone.');
    }

    public function test_el_rival_que_contesta_el_truco_yendose_al_mazo_cuenta_como_que_se_fue(): void
    {
        $alMazo = $this->cerrada($this->armada(self::CON_TANTO, mano: self::BOT), '1 truco', '0 mazo');

        $this->assertEqualsWithDelta((4 * 0.4 + 3) / 7, Perfil::de([$alMazo, $alMazo, $alMazo])->seVa, 0.000001);
    }

    public function test_contra_el_que_se_va_miente_mas_seguido_que_contra_el_que_quiere_todo(): void
    {
        $seFue = $this->cerrada($this->armada(self::CON_TANTO, mano: self::BOT), '1 truco', '0 no_quiero');
        $quiso = $this->cerrada($this->armada(self::CON_TANTO, mano: self::BOT), '1 truco', '0 quiero', '1 mazo');

        // Sin tanto y con cartas flojas: si canta algo, está mintiendo.
        $floja = $this->armada([['3-copa', '2-espada', '12-basto'], ['4-oro', '5-copa', '6-basto']], mano: self::BOT);

        $contraElQueSeVa = $this->cuantasVecesCanta($floja, array_fill(0, 4, $seFue));
        $contraElQueQuiere = $this->cuantasVecesCanta($floja, array_fill(0, 6, $quiso));

        $this->assertGreaterThan(2 * $contraElQueQuiere, $contraElQueSeVa);
        $this->assertGreaterThan(0, $contraElQueQuiere, 'Contra el que quiere todo miente poco, pero no deja de mentir.');
    }

    public function test_perdiendo_por_mucho_canta_mas_que_ganando_por_mucho(): void
    {
        $azar = Azar::deSemilla(21);
        $cantos = ['ganando' => 0, 'perdiendo' => 0];

        foreach (range(1, 250) as $numero) {
            $mazo = Mazo::mezcladoCon($azar);
            $manos = [array_slice($mazo, 0, 3), array_slice($mazo, 3, 3)];

            // El tanteo va por asiento: primero el rival, después el bot.
            foreach (['ganando' => [8, 16], 'perdiendo' => [16, 8]] as $como => $tanteo) {
                $partida = Partida::armada($manos, self::BOT, $tanteo);
                $accion = (new BotUltraDificil(Azar::deSemilla($numero)))->decidir($partida->vistaPara(self::BOT));

                $cantos[$como] += (int) ($accion->tipo !== TipoDeAccion::Jugar);
            }
        }

        $this->assertGreaterThan($cantos['ganando'], $cantos['perdiendo']);
    }

    /**
     * En cuántas de 300 jugadas con distinto azar el bot canta en vez de tirar una carta.
     *
     * @param  list<array<string, mixed>>  $recuerdos
     */
    private function cuantasVecesCanta(Partida $partida, array $recuerdos): int
    {
        $veces = 0;

        foreach (range(1, 300) as $semilla) {
            $bot = new BotUltraDificil(Azar::deSemilla($semilla));
            $bot->recordar($recuerdos);

            $veces += (int) ($bot->decidir($partida->vistaPara(self::BOT))->tipo !== TipoDeAccion::Jugar);
        }

        return $veces;
    }

    /**
     * Lo que el bot vio de una mano ya cerrada: lo mismo que la mesa le recuerda.
     *
     * @return array<string, mixed>
     */
    private function cerrada(Partida $partida, string ...$jugadas): array
    {
        $vista = $this->jugar($partida, ...$jugadas)->vistaPara(self::BOT);

        $this->assertNotNull($vista['cierre'], 'La mano del ejemplo no llegó a cerrarse.');

        return $vista;
    }
}
