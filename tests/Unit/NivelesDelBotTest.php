<?php

namespace Tests\Unit;

use App\Juego\Nivel;
use App\Motor\Accion;
use App\Motor\Azar;
use App\Motor\Carta;
use App\Motor\Mazo;
use App\Motor\Partida;
use App\Motor\TipoDeAccion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Lo que M4 les pide a los tres niveles: que jueguen siempre algo válido, que el de arriba
 * le gane al de abajo y que ninguno decida con cartas que un jugador no vería.
 */
class NivelesDelBotTest extends TestCase
{
    use Enfrentando;

    private const RIVAL = 0;

    private const BOT = 1;

    /**
     * Cada partida revisa todas las decisiones de los dos bots: si alguna no figura entre las
     * acciones válidas, falla ahí. Se cruzan todos los niveles, también cada uno contra sí mismo:
     * son cincuenta partidas enteras, y las de la escalera de abajo revisan lo mismo en otras setenta.
     */
    #[DataProvider('cruces')]
    public function test_ningun_nivel_produce_una_accion_invalida_en_una_simulacion_larga(Nivel $uno, Nivel $otro): void
    {
        $ganadas = $this->ganadasPor($uno, $otro, partidas: 5, desdeLaSemilla: 500);

        $this->assertGreaterThanOrEqual(0, $ganadas);
    }

    /**
     * Medido con estas mismas semillas: Difícil le gana 23 de 24 a Fácil, Intermedio 21 de 24 a Fácil y
     * Difícil 23 de 24 a Intermedio. En una tanda de 400 dan 92 %, 74 % y 82 %. Los pisos dejan margen
     * para poder ajustar a los bots sin que el test se rompa por una partida. Como las partidas salen de
     * una semilla, el resultado es siempre el mismo: la tanda es corta para que el test no tarde.
     */
    #[DataProvider('escalera')]
    public function test_el_nivel_de_arriba_le_gana_al_de_abajo(Nivel $arriba, Nivel $abajo, int $piso): void
    {
        $ganadas = $this->ganadasPor($arriba, $abajo, partidas: 24);

        $this->assertGreaterThanOrEqual($piso, $ganadas, "{$arriba->nombre()} le ganó {$ganadas} de 24 a {$abajo->nombre()}.");
    }

    /**
     * Dos mesas iguales en todo lo que el bot puede ver y distintas en las cartas que el rival
     * no mostró. Si un nivel decidiera con esas cartas, en alguna de las dos decidiría otra cosa.
     */
    #[DataProvider('niveles')]
    public function test_con_las_mismas_cartas_y_la_misma_semilla_decide_igual_aunque_cambien_las_cartas_ocultas_del_rival(Nivel $nivel): void
    {
        $comparadas = 0;

        foreach (range(1, 20) as $semilla) {
            $mazo = Mazo::mezclado($semilla);
            $delBot = array_slice($mazo, 0, 3);

            // Las dos manos del rival comparten una sola carta: la que va a tirar.
            $unas = [$mazo[3], $mazo[4], $mazo[5]];
            $otras = [$mazo[3], $mazo[6], $mazo[7]];

            $situaciones = [
                'sale el bot' => [self::BOT, []],
                'el rival tiró una carta' => [self::RIVAL, [Accion::jugar($mazo[3])]],
                'el rival cantó envido' => [self::RIVAL, [Accion::de(TipoDeAccion::Envido)]],
                'el rival cantó truco' => [self::RIVAL, [Accion::de(TipoDeAccion::Truco)]],
                'el rival tiró una carta y el bot contestó' => [self::RIVAL, [Accion::jugar($mazo[3]), null]],
            ];

            foreach ($situaciones as $nombre => [$mano, $jugadas]) {
                $conUnas = $this->llegarA($nivel, $semilla, [$unas, $delBot], $mano, $jugadas);
                $conOtras = $this->llegarA($nivel, $semilla, [$otras, $delBot], $mano, $jugadas);

                // Alguna de las dos puede haberse cerrado antes (por ejemplo, con un mazo): ahí no hay nada que decidir.
                if ($conUnas->accionesPara(self::BOT) === [] || $conOtras->accionesPara(self::BOT) === []) {
                    continue;
                }

                $this->assertSame($conUnas->vistaPara(self::BOT), $conOtras->vistaPara(self::BOT), "Semilla {$semilla}, {$nombre}: el bot ve algo distinto.");
                $this->assertEquals(
                    $nivel->bot(Azar::deSemilla($semilla))->decidir($conUnas->vistaPara(self::BOT)),
                    $nivel->bot(Azar::deSemilla($semilla))->decidir($conOtras->vistaPara(self::BOT)),
                    "Semilla {$semilla}, {$nombre}: decide distinto según las cartas ocultas del rival.",
                );

                $comparadas++;
            }
        }

        $this->assertGreaterThan(75, $comparadas);
    }

    public static function cruces(): array
    {
        $cruces = [];

        foreach (Nivel::cases() as $uno) {
            foreach (Nivel::cases() as $otro) {
                if ($uno->value <= $otro->value) {
                    $cruces["{$uno->nombre()} contra {$otro->nombre()}"] = [$uno, $otro];
                }
            }
        }

        return $cruces;
    }

    public static function escalera(): array
    {
        return [
            'Difícil contra Fácil' => [Nivel::Dificil, Nivel::Facil, 19],
            'Intermedio contra Fácil' => [Nivel::Intermedio, Nivel::Facil, 14],
            'Difícil contra Intermedio' => [Nivel::Dificil, Nivel::Intermedio, 16],
        ];
    }

    public static function niveles(): array
    {
        return array_combine(
            array_map(fn (Nivel $nivel) => $nivel->nombre(), Nivel::cases()),
            array_map(fn (Nivel $nivel) => [$nivel], Nivel::cases()),
        );
    }

    /**
     * Arma la mano y aplica las jugadas del rival. Un null es una jugada del bot, que la decide él.
     *
     * @param  array{0: list<Carta>, 1: list<Carta>}  $manos
     * @param  list<Accion|null>  $jugadas
     */
    private function llegarA(Nivel $nivel, int $semilla, array $manos, int $mano, array $jugadas): Partida
    {
        $partida = Partida::armada($manos, $mano);

        foreach ($jugadas as $jugada) {
            if ($partida->accionesPara($jugada === null ? self::BOT : self::RIVAL) === []) {
                break;
            }

            $partida = $jugada === null
                ? $partida->aplicar(self::BOT, $nivel->bot(Azar::deSemilla($semilla + 1000))->decidir($partida->vistaPara(self::BOT)))
                : $partida->aplicar(self::RIVAL, $jugada);
        }

        return $partida;
    }
}
