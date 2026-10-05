<?php

namespace Tests\Unit;

use App\Juego\BotFacil;
use App\Motor\Accion;
use App\Motor\Azar;
use App\Motor\Partida;
use App\Motor\TipoDeAccion;
use PHPUnit\Framework\TestCase;
use Tests\Motor\Jugando;

/**
 * El bot Fácil: juega al azar con límites. Está en el asiento 1.
 */
class BotFacilTest extends TestCase
{
    use Jugando;

    private const BOT = 1;

    public function test_la_mayoria_de_las_veces_juega_una_carta_y_nunca_se_va_al_mazo(): void
    {
        // Es mano y no jugó: puede cantar envido, truco o irse al mazo.
        $partida = $this->armada([['1-espada', '4-copa', '5-copa'], ['7-oro', '6-basto', '3-espada']], mano: self::BOT);
        $cartas = 0;

        foreach (range(1, 400) as $semilla) {
            $accion = $this->decide($partida, $semilla);

            $this->assertNotSame(TipoDeAccion::Mazo, $accion->tipo);
            $cartas += (int) ($accion->tipo === TipoDeAccion::Jugar);
        }

        // Siete de cada diez: entre 240 y 320 de 400 deja margen de sobra.
        $this->assertGreaterThan(240, $cartas);
        $this->assertLessThan(320, $cartas);
    }

    public function test_cuando_le_cantan_contesta_cualquier_cosa_menos_el_mazo(): void
    {
        $partida = $this->jugar(
            $this->armada([['1-espada', '4-copa', '5-copa'], ['7-oro', '6-basto', '3-espada']]),
            '0 truco',
        );
        $respuestas = [];

        foreach (range(1, 200) as $semilla) {
            $respuestas[$this->decide($partida, $semilla)->tipo->value] = true;
        }

        $this->assertArrayNotHasKey('mazo', $respuestas);
        $this->assertArrayNotHasKey('jugar', $respuestas, 'Con un canto pendiente no se juega una carta.');

        foreach (['quiero', 'no_quiero', 'retruco', 'envido'] as $respuesta) {
            $this->assertArrayHasKey($respuesta, $respuestas, "En 200 intentos nunca contestó [{$respuesta}].");
        }
    }

    public function test_con_flor_la_canta_siempre(): void
    {
        $partida = $this->armada([['1-espada', '4-copa', '5-copa'], ['7-oro', '6-oro', '2-oro']], mano: self::BOT);

        foreach (range(1, 50) as $semilla) {
            $this->assertSame(TipoDeAccion::Flor, $this->decide($partida, $semilla)->tipo);
        }
    }

    public function test_la_misma_semilla_decide_siempre_lo_mismo(): void
    {
        $partida = $this->armada([['1-espada', '4-copa', '5-copa'], ['7-oro', '6-basto', '3-espada']], mano: self::BOT);

        foreach (range(1, 20) as $semilla) {
            $this->assertEquals($this->decide($partida, $semilla), $this->decide($partida, $semilla));
        }
    }

    private function decide(Partida $partida, int $semilla): Accion
    {
        return (new BotFacil(Azar::deSemilla($semilla)))->decidir($partida->vistaPara(self::BOT));
    }
}
