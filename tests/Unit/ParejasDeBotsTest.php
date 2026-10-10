<?php

namespace Tests\Unit;

use App\Juego\BotDePareja;
use App\Juego\Nivel;
use App\Juego\Senas;
use App\Motor\Accion;
use App\Motor\Azar;
use App\Motor\Carta;
use App\Motor\Partida;
use App\Motor\TipoDeAccion;
use PHPUnit\Framework\TestCase;

/**
 * Los bots del truco de a cuatro y las señas con las que se entienden.
 */
class ParejasDeBotsTest extends TestCase
{
    use EnfrentandoParejas;

    public function test_ningun_nivel_hace_algo_que_no_vale_en_partidas_enteras_de_a_cuatro(): void
    {
        // Cada cruce juega partidas enteras, y partidaDeACuatro() comprueba cada decisión contra el motor.
        foreach (Nivel::cases() as $una) {
            foreach (Nivel::cases() as $otra) {
                $ganadas = $this->ganadasPorLaPareja($una, $otra, 4, 900 + $una->value * 10 + $otra->value);

                $this->assertGreaterThanOrEqual(0, $ganadas);
            }
        }
    }

    public function test_una_pareja_que_juega_con_criterio_le_gana_a_una_que_tira_cualquier_cosa(): void
    {
        // Medido con 100 partidas por cruce: 81, 80 y 77. Acá se juegan 30, con un piso holgado.
        foreach ([Nivel::Intermedio, Nivel::Dificil, Nivel::UltraDificil] as $nivel) {
            $ganadas = $this->ganadasPorLaPareja($nivel, Nivel::Facil, 30, 500);

            $this->assertGreaterThanOrEqual(19, $ganadas, "La pareja de nivel {$nivel->nombre()} ganó {$ganadas} de 30 contra la del Fácil.");
        }
    }

    public function test_cada_carta_que_manda_tiene_su_sena_y_nadie_hace_una_de_lo_que_no_tiene(): void
    {
        $casos = [
            [['1-espada', '3-oro', '4-copa'], [Senas::ANCHO_DE_ESPADA, Senas::UN_TRES]],
            [['1-basto', '7-espada', '7-oro'], [Senas::ANCHO_DE_BASTO, Senas::SIETE_DE_ESPADA, Senas::SIETE_DE_ORO]],
            // Dos tres son una sola seña, y el ancho de copa es un ancho falso.
            [['3-espada', '3-basto', '1-copa'], [Senas::UN_TRES, Senas::ANCHO_FALSO]],
            [['2-oro', '1-oro', '10-basto'], [Senas::UN_DOS, Senas::ANCHO_FALSO]],
            // Sin ninguna carta con seña: "no tengo nada".
            [['12-espada', '5-basto', '6-copa'], [Senas::NADA]],
            // El siete de copa no es el de espada ni el de oro. Con 33 de tanto, además, "tengo buen envido".
            [['7-copa', '6-copa', '11-oro'], [Senas::NADA, Senas::BUEN_ENVIDO]],
            [['7-oro', '6-oro', '4-basto'], [Senas::SIETE_DE_ORO, Senas::BUEN_ENVIDO]],
        ];

        foreach ($casos as [$cartas, $senas]) {
            $this->assertSame($senas, Senas::de(array_map(Carta::de(...), $cartas)), implode(', ', $cartas));
        }

        // Son nueve, cada una con su gesto, y ninguna nombra el palo de una carta que la seña no dice.
        $this->assertCount(9, Senas::todas());
        $this->assertSame(14, Senas::fuerzaDe([Senas::UN_TRES, Senas::ANCHO_DE_ESPADA]));
        $this->assertSame(0, Senas::fuerzaDe([Senas::NADA, Senas::BUEN_ENVIDO]));
    }

    public function test_el_bot_no_le_pisa_la_carta_a_su_companero(): void
    {
        // Abre el 0 con el ancho de espada y el 1 no lo supera. Al 2, su compañero, le toca con un 3 en la mano.
        $partida = $this->enJuego([
            ['1-espada', '4-copa', '5-copa'],
            ['2-oro', '6-basto', '5-basto'],
            ['3-espada', '12-copa', '4-oro'],
            ['2-basto', '6-oro', '10-oro'],
        ], '0 1-espada', '1 6-basto');

        foreach ([Nivel::Intermedio, Nivel::Dificil, Nivel::UltraDificil] as $nivel) {
            $accion = (new BotDePareja($nivel, Azar::deSemilla(1)))->decidir($partida->vistaPara(2));

            $this->assertSame(TipoDeAccion::Jugar, $accion->tipo);
            $this->assertSame('4-oro', $accion->carta->id(), "El nivel {$nivel->nombre()} tiró una carta buena sobre el ancho de su compañero.");
        }
    }

    public function test_el_bot_sale_bajo_si_su_companero_le_senalo_una_brava(): void
    {
        $partida = $this->enJuego([
            ['12-espada', '11-copa', '4-oro'],
            ['2-oro', '6-basto', '5-basto'],
            ['1-espada', '4-copa', '5-copa'],
            ['2-basto', '6-oro', '10-oro'],
        ]);

        $bot = new BotDePareja(Nivel::Intermedio, Azar::deSemilla(1));

        // Sin señas, abre con su carta más alta.
        $this->assertSame('12-espada', $bot->decidir($partida->vistaPara(0))->carta->id());

        // Si su compañero le dijo que tiene el ancho de espada, sale con la más baja: la baza la gana él.
        $bot->recibirSenas([Senas::ANCHO_DE_ESPADA]);
        $this->assertSame('4-oro', $bot->decidir($partida->vistaPara(0))->carta->id());
    }

    public function test_con_la_sena_de_su_companero_quiere_un_truco_que_solo_no_querria(): void
    {
        // El 1 canta truco. El 0 tiene cartas flojas: solo, no lo quiere.
        $partida = $this->enJuego([
            ['12-espada', '11-copa', '4-oro'],
            ['1-basto', '6-basto', '5-basto'],
            ['7-espada', '4-copa', '5-copa'],
            ['2-basto', '6-oro', '10-oro'],
        ], '0 4-oro', '1 truco');

        $bot = new BotDePareja(Nivel::Intermedio, Azar::deSemilla(1));
        $this->assertSame(TipoDeAccion::NoQuiero, $bot->decidir($partida->vistaPara(0))->tipo);

        // Con la seña del siete de espada de su compañero, la pareja tiene una brava: quiere.
        $bot->recibirSenas([Senas::SIETE_DE_ESPADA]);
        $this->assertSame(TipoDeAccion::Quiero, $bot->decidir($partida->vistaPara(0))->tipo);
    }

    /**
     * Una mano armada de cuatro asientos, con el asiento 0 de mano, después de esas jugadas.
     *
     * @param  list<list<string>>  $manos
     */
    private function enJuego(array $manos, string ...$jugadas): Partida
    {
        $partida = Partida::armada(array_map(fn (array $mano) => array_map(Carta::de(...), $mano), $manos));

        foreach ($jugadas as $jugada) {
            [$asiento, $que] = explode(' ', $jugada, 2);
            $accion = str_contains($que, '-')
                ? Accion::jugar(Carta::de($que))
                : Accion::de(TipoDeAccion::from($que));

            $partida = $partida->aplicar((int) $asiento, $accion);
        }

        return $partida;
    }
}
