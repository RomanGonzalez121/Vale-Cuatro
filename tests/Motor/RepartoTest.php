<?php

namespace Tests\Motor;

use App\Motor\Accion;
use App\Motor\AccionInvalida;
use App\Motor\Carta;
use App\Motor\Fase;
use App\Motor\Mazo;
use App\Motor\Partida;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RepartoTest extends TestCase
{
    use Jugando;

    public function test_una_partida_nueva_espera_el_reparto(): void
    {
        $partida = Partida::nueva();

        $this->assertSame(Fase::PorRepartir, $partida->fase());
        $this->assertSame([0, 0], $partida->tanteo());
        $this->assertSame([], $partida->accionesPara(0));
    }

    public function test_se_reparten_tres_cartas_a_cada_uno_de_a_una_y_empezando_por_el_mano(): void
    {
        $mazo = Mazo::mezclado(2026);
        $estado = Partida::nueva(mano: 1)->repartir($mazo)->aArray();

        // El mano es el asiento 1: recibe la primera, la tercera y la quinta.
        $this->assertSame([$mazo[0]->id(), $mazo[2]->id(), $mazo[4]->id()], $estado['cartas'][1]);
        $this->assertSame([$mazo[1]->id(), $mazo[3]->id(), $mazo[5]->id()], $estado['cartas'][0]);
        $this->assertSame(Fase::Jugando->value, $estado['fase']);
    }

    public function test_de_a_cuatro_se_reparte_en_ronda_desde_el_mano(): void
    {
        $mazo = Mazo::mezclado(7);
        $estado = Partida::nueva(asientos: 4, mano: 2)->repartir($mazo)->aArray();

        // Orden de la ronda: 2, 3, 0, 1.
        $this->assertSame([$mazo[0]->id(), $mazo[4]->id(), $mazo[8]->id()], $estado['cartas'][2]);
        $this->assertSame([$mazo[1]->id(), $mazo[5]->id(), $mazo[9]->id()], $estado['cartas'][3]);
        $this->assertSame([$mazo[2]->id(), $mazo[6]->id(), $mazo[10]->id()], $estado['cartas'][0]);
        $this->assertSame([$mazo[3]->id(), $mazo[7]->id(), $mazo[11]->id()], $estado['cartas'][1]);
    }

    public function test_la_misma_semilla_da_siempre_el_mismo_reparto(): void
    {
        $una = Partida::nueva()->repartir(Mazo::mezclado(99))->aArray();
        $otra = Partida::nueva()->repartir(Mazo::mezclado(99))->aArray();

        $this->assertSame($una, $otra);
        $this->assertNotSame($una['cartas'], Partida::nueva()->repartir(Mazo::mezclado(100))->aArray()['cartas']);
    }

    public function test_sale_el_mano(): void
    {
        $this->assertSame(0, Partida::nueva(mano: 0)->repartir(Mazo::mezclado(1))->turno());
        $this->assertSame(1, Partida::nueva(mano: 1)->repartir(Mazo::mezclado(1))->turno());
    }

    public function test_una_mano_armada_se_juega_igual_que_una_repartida(): void
    {
        foreach ([3, 11, 2026] as $semilla) {
            $repartida = Partida::nueva(mano: 1)->repartir(Mazo::mezclado($semilla));
            $armada = $this->armada($repartida->aArray()['cartas'], mano: 1);

            $this->assertSame($repartida->aArray(), $armada->aArray());

            // Las dos juegan la misma mano hasta el final: siempre la primera acción disponible.
            while ($repartida->fase() === Fase::Jugando) {
                $asiento = $repartida->turno();
                $accion = $repartida->accionesPara($asiento)[0];

                $repartida = $repartida->aplicar($asiento, $accion);
                $armada = $armada->aplicar($asiento, $accion);

                $this->assertSame($repartida->aArray(), $armada->aArray());
            }
        }
    }

    public function test_una_situacion_armada_arranca_con_el_tanteo_y_el_mano_que_se_le_dan(): void
    {
        $partida = $this->armada([['1-espada', '2-oro', '4-copa'], ['7-oro', '3-copa', '5-basto']], mano: 1, tanteo: [12, 27]);

        $this->assertSame([12, 27], $partida->tanteo());
        $this->assertSame(1, $partida->mano());
        $this->assertSame(1, $partida->turno());
        $this->assertSame(['7-oro', '3-copa', '5-basto'], $partida->aArray()['cartas'][1]);
    }

    public function test_no_se_arma_una_mano_con_una_carta_repetida(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('repetido');

        $this->armada([['1-espada', '2-oro', '4-copa'], ['1-espada', '3-copa', '5-basto']]);
    }

    public function test_no_se_arma_una_mano_con_cartas_de_mas_o_de_menos(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->armada([['1-espada', '2-oro'], ['7-oro', '3-copa', '5-basto']]);
    }

    public function test_la_mesa_es_de_dos_o_de_cuatro(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Partida::nueva(asientos: 3);
    }

    public function test_no_se_reparte_con_una_mano_en_juego(): void
    {
        $partida = Partida::nueva()->repartir(Mazo::mezclado(1));

        $this->expectException(AccionInvalida::class);
        $this->expectExceptionMessage('todavía se está jugando');

        $partida->repartir(Mazo::mezclado(2));
    }

    public function test_repartir_no_cambia_la_partida_anterior(): void
    {
        $antes = Partida::nueva();
        $antes->repartir(Mazo::mezclado(1));

        $this->assertSame(Fase::PorRepartir, $antes->fase());
    }

    public function test_aplicar_una_accion_no_cambia_la_partida_anterior(): void
    {
        $antes = $this->armada([['1-espada', '2-oro', '4-copa'], ['7-oro', '3-copa', '5-basto']]);
        $foto = $antes->aArray();

        $antes->aplicar(0, Accion::jugar(Carta::de('1-espada')));

        $this->assertSame($foto, $antes->aArray());
    }
}
