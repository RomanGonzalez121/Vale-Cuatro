<?php

namespace Tests\Unit;

use App\Juego\Nivel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Que los niveles del truco de a cuatro sean de verdad escalones: cada pareja le gana a la del nivel de abajo.
 *
 * Son 900 partidas y tardan unos seis minutos, así que no corre con el resto: se lanza a mano con
 * `vendor/bin/phpunit --group medicion`. Con las mismas semillas da siempre lo mismo.
 *
 * Medido el 10 de octubre de 2026, con 300 partidas por cruce desde la semilla 500: la pareja de Difícil
 * le ganó a la de Intermedio 184 (61 %), la de Ultra a la de Difícil 176 (59 %) y la de Ultra a la de
 * Intermedio 188 (63 %). Con 300 partidas el error es de unos 5 puntos: los pisos quedan por debajo de
 * lo medido y por encima del empate.
 *
 * Antes de tocar BotDePareja, CuentasDePareja o LecturaDePareja, correrlo.
 */
#[Group('medicion')]
class MedicionDeLasParejasTest extends TestCase
{
    use EnfrentandoParejas;

    private const PARTIDAS = 300;

    private const DESDE_LA_SEMILLA = 500;

    public function test_cada_pareja_le_gana_a_la_del_nivel_de_abajo(): void
    {
        $cruces = [
            [Nivel::Dificil, Nivel::Intermedio, 168],
            [Nivel::UltraDificil, Nivel::Dificil, 162],
            [Nivel::UltraDificil, Nivel::Intermedio, 168],
        ];

        foreach ($cruces as [$una, $otra, $piso]) {
            $ganadas = $this->ganadasPorLaPareja($una, $otra, self::PARTIDAS, self::DESDE_LA_SEMILLA);

            $this->assertGreaterThanOrEqual(
                $piso,
                $ganadas,
                "La pareja de nivel {$una->nombre()} ganó {$ganadas} de ".self::PARTIDAS." contra la de {$otra->nombre()}.",
            );
        }
    }
}
