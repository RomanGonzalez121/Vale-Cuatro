<?php

namespace Tests\Unit;

use App\Juego\Nivel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Que los niveles del gallito sean de verdad escalones: una máquina sola le gana a dos del nivel de abajo
 * más seguido que la tercera parte de las veces, que es lo que le tocaría por azar.
 *
 * Son 900 partidas y tardan unos minutos, así que no corre con el resto: se lanza a mano con
 * `vendor/bin/phpunit --group medicion`. Con las mismas semillas da siempre lo mismo.
 *
 * Antes de tocar BotDePareja, CuentasDePareja o LecturaDePareja, correrlo (junto con el de las parejas).
 */
#[Group('medicion')]
class MedicionDelGallitoTest extends TestCase
{
    use EnfrentandoGallitos;

    private const PARTIDAS = 300;

    private const DESDE_LA_SEMILLA = 700;

    public function test_cada_nivel_le_gana_a_los_de_abajo_mas_que_por_azar(): void
    {
        // Entre el Ultra y el Difícil no hay escalón en el gallito (150 de 450, lo que daría el azar): Román
        // decidió dejarlo así el 22 de octubre de 2026. Contra el Intermedio, los dos se separan bien.
        $cruces = [
            [Nivel::Dificil, Nivel::Intermedio, 130],
            [Nivel::UltraDificil, Nivel::Intermedio, 130],
        ];

        foreach ($cruces as [$uno, $otros, $piso]) {
            $ganados = $this->ganadosPorUno($uno, $otros, self::PARTIDAS, self::DESDE_LA_SEMILLA);

            $this->assertGreaterThanOrEqual(
                $piso,
                $ganados,
                "La máquina de nivel {$uno->nombre()} ganó {$ganados} de ".self::PARTIDAS." contra dos de {$otros->nombre()}.",
            );
        }
    }
}
