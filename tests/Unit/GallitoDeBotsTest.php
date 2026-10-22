<?php

namespace Tests\Unit;

use App\Juego\BotDePareja;
use App\Juego\LecturaDePareja;
use App\Juego\Nivel;
use App\Motor\Accion;
use App\Motor\Azar;
use App\Motor\Carta;
use App\Motor\Partida;
use App\Motor\TipoDeAccion;
use PHPUnit\Framework\TestCase;

/**
 * Las máquinas del gallito: juegan para su lado de cada mano (el gallo solo, la pareja para los dos).
 */
class GallitoDeBotsTest extends TestCase
{
    use EnfrentandoGallitos;

    public function test_ningun_nivel_hace_algo_que_no_vale_en_gallitos_enteros(): void
    {
        foreach (Nivel::cases() as $nivel) {
            foreach (range(1, 4) as $semilla) {
                $bots = array_map(fn (int $asiento) => $nivel->botDePareja(Azar::deSemilla($semilla * 7 + $asiento)), [0, 1, 2]);
                $ganador = $this->partidaDeGallito($bots, $semilla);

                $this->assertContains($ganador, [0, 1, 2]);
            }
        }
    }

    public function test_la_lectura_sabe_quien_es_el_gallo_y_quien_su_companero(): void
    {
        $partida = Partida::armada(array_map(fn (array $ids) => array_map(Carta::de(...), $ids), [
            ['4-copa', '5-oro', '6-basto'],
            ['3-basto', '6-copa', '7-basto'],
            ['12-oro', '11-basto', '10-copa'],
        ]), mano: 1, tanteo: [10, 4, 12]);

        $gallo = new LecturaDePareja($partida->vistaPara(1));
        $deLaPareja = new LecturaDePareja($partida->vistaPara(2));

        $this->assertNull($gallo->companero);
        $this->assertSame(0, $gallo->equipo);
        $this->assertSame(0, $deLaPareja->companero);
        $this->assertTrue($deLaPareja->esDeMiEquipo(0));
        $this->assertFalse($deLaPareja->esDeMiEquipo(1));
        $this->assertSame([12, 10], [$deLaPareja->misPuntos(), $deLaPareja->susPuntos()], 'Cada uno cuenta sus puntos y los del que va más arriba.');
    }

    public function test_en_la_pareja_no_le_pisa_la_carta_al_companero(): void
    {
        // El gallo (asiento 0) sale con un 4; el asiento 1 la gana con el ancho de espada. El 2, de la
        // pareja, tiene un tres: no lo gasta y tira la más baja.
        $partida = Partida::armada(array_map(fn (array $ids) => array_map(Carta::de(...), $ids), [
            ['4-copa', '5-oro', '6-basto'],
            ['1-espada', '6-copa', '7-basto'],
            ['3-oro', '11-basto', '10-copa'],
        ]), mano: 0);
        $partida = $partida->aplicar(0, Accion::jugar(Carta::de('4-copa')))
            ->aplicar(1, Accion::jugar(Carta::de('1-espada')));

        $accion = (new BotDePareja(Nivel::Intermedio, Azar::deSemilla(1)))->decidir($partida->vistaPara(2));

        $this->assertSame(TipoDeAccion::Jugar, $accion->tipo);
        $this->assertSame('10-copa', $accion->carta->id());
    }
}
