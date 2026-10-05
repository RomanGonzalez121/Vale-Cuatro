<?php

namespace Tests\Motor;

use App\Motor\Carta;
use App\Motor\Mazo;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class JerarquiaTest extends TestCase
{
    /**
     * La jerarquía del reglamento, de mayor a menor. Las cartas de un mismo escalón empatan.
     */
    private const ESCALONES = [
        ['1-espada'],
        ['1-basto'],
        ['7-espada'],
        ['7-oro'],
        ['3-espada', '3-basto', '3-oro', '3-copa'],
        ['2-espada', '2-basto', '2-oro', '2-copa'],
        ['1-copa', '1-oro'],
        ['12-espada', '12-basto', '12-oro', '12-copa'],
        ['11-espada', '11-basto', '11-oro', '11-copa'],
        ['10-espada', '10-basto', '10-oro', '10-copa'],
        ['7-copa', '7-basto'],
        ['6-espada', '6-basto', '6-oro', '6-copa'],
        ['5-espada', '5-basto', '5-oro', '5-copa'],
        ['4-espada', '4-basto', '4-oro', '4-copa'],
    ];

    public function test_la_tabla_cubre_las_cuarenta_cartas(): void
    {
        $enLaTabla = array_merge(...self::ESCALONES);
        $delMazo = array_map(fn (Carta $carta) => $carta->id(), Mazo::completo());

        sort($enLaTabla);
        sort($delMazo);

        $this->assertSame($delMazo, $enLaTabla);
    }

    public function test_cada_escalon_le_gana_a_todos_los_de_abajo(): void
    {
        foreach (self::ESCALONES as $i => $altas) {
            foreach (array_slice(self::ESCALONES, $i + 1) as $bajas) {
                foreach ($altas as $alta) {
                    foreach ($bajas as $baja) {
                        $this->assertTrue(Carta::de($alta)->leGanaA(Carta::de($baja)), "{$alta} tiene que ganarle a {$baja}.");
                        $this->assertFalse(Carta::de($baja)->leGanaA(Carta::de($alta)), "{$baja} no puede ganarle a {$alta}.");
                    }
                }
            }
        }
    }

    public function test_las_cartas_de_un_mismo_escalon_empatan(): void
    {
        foreach (self::ESCALONES as $escalon) {
            foreach ($escalon as $una) {
                foreach ($escalon as $otra) {
                    $this->assertTrue(Carta::de($una)->empataCon(Carta::de($otra)), "{$una} y {$otra} tienen que empatar.");
                    $this->assertFalse(Carta::de($una)->leGanaA(Carta::de($otra)));
                }
            }
        }
    }

    public function test_los_anchos_falsos_empatan_entre_si_y_pierden_con_un_dos(): void
    {
        $this->assertTrue(Carta::de('1-copa')->empataCon(Carta::de('1-oro')));
        $this->assertTrue(Carta::de('2-copa')->leGanaA(Carta::de('1-oro')));
    }

    public function test_los_sietes_falsos_empatan_entre_si_y_pierden_con_un_diez(): void
    {
        $this->assertTrue(Carta::de('7-copa')->empataCon(Carta::de('7-basto')));
        $this->assertTrue(Carta::de('10-oro')->leGanaA(Carta::de('7-basto')));
    }

    public function test_las_figuras_valen_cero_en_el_envido(): void
    {
        foreach ([10, 11, 12] as $figura) {
            $this->assertSame(0, Carta::de("{$figura}-oro")->valorDeEnvido());
        }

        foreach ([1, 2, 3, 4, 5, 6, 7] as $numero) {
            $this->assertSame($numero, Carta::de("{$numero}-oro")->valorDeEnvido());
        }
    }

    public function test_la_carta_se_arma_desde_su_identificador_y_vuelve_al_mismo(): void
    {
        foreach (Mazo::completo() as $carta) {
            $this->assertTrue(Carta::de($carta->id())->es($carta));
        }
    }

    public function test_un_identificador_que_no_es_una_carta_se_rechaza(): void
    {
        foreach (['8-oro', '9-copa', '1-trebol', 'as de espada', '7espada', ''] as $id) {
            try {
                Carta::de($id);
                $this->fail("[{$id}] no debería ser una carta.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
