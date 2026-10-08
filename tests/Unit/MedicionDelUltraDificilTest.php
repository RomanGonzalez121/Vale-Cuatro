<?php

namespace Tests\Unit;

use App\Juego\Nivel;
use App\Motor\Azar;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * La condición de M4 para el nivel 4: le gana al nivel 3 por el margen que fijó Román.
 *
 * Tarda unos 10 minutos, así que no corre con el resto: se lanza a mano con
 * `vendor/bin/phpunit --group medicion`. Con las mismas semillas da siempre lo mismo.
 */
#[Group('medicion')]
class MedicionDelUltraDificilTest extends TestCase
{
    use Enfrentando;

    /** El margen que fijó Román el 8 de octubre de 2026: le tiene que ganar al Difícil al menos el 52 %. */
    private const MARGEN = 0.52;

    private const REPARTOS = 400;

    private const DESDE_LA_SEMILLA = 7000;

    /**
     * Partidas en espejo: cada reparto se juega dos veces, con los asientos cambiados, así la suerte
     * de las cartas se cancela y alcanzan 800 partidas. Medido el 8 de octubre de 2026 con el código final:
     * 416 de 800 (52,0 %), justo en el piso y con un error de un punto. Otras 2.000 partidas sin espejo
     * y con otras semillas dieron 53,3 %.
     */
    public function test_el_ultra_dificil_le_gana_al_dificil_por_el_margen_fijado(): void
    {
        $ganadas = 0;

        for ($semilla = self::DESDE_LA_SEMILLA; $semilla < self::DESDE_LA_SEMILLA + self::REPARTOS; $semilla++) {
            foreach ([0, 1] as $asiento) {
                $bots = [];
                $bots[$asiento] = Nivel::UltraDificil->bot(Azar::deSemilla($semilla * 7 + 1));
                $bots[1 - $asiento] = Nivel::Dificil->bot(Azar::deSemilla($semilla * 7 + 2));

                $ganadas += (int) ($this->partidaEntre($bots, $semilla) === $asiento);
            }
        }

        $partidas = 2 * self::REPARTOS;

        // Pasando o no, el número queda a la vista: el test solo exige el piso.
        fwrite(STDERR, sprintf("\nUltra difícil contra Difícil: %d de %d (%.1f %%), el margen es %.0f %%.\n", $ganadas, $partidas, 100 * $ganadas / $partidas, 100 * self::MARGEN));

        $this->assertGreaterThanOrEqual(
            (int) ceil(self::MARGEN * $partidas),
            $ganadas,
            sprintf('El Ultra difícil le ganó %d de %d al Difícil (%.1f %%) y el margen es %.0f %%.', $ganadas, $partidas, 100 * $ganadas / $partidas, 100 * self::MARGEN),
        );
    }
}
