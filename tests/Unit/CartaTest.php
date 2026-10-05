<?php

namespace Tests\Unit;

use App\Motor\Mazo;
use App\View\Components\Carta;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CartaTest extends TestCase
{
    public function test_el_mazo_tiene_cuarenta_cartas_distintas(): void
    {
        $identificadores = array_map(fn (array $carta) => "{$carta[1]}-{$carta[0]}", Carta::mazo());

        $this->assertCount(40, $identificadores);
        $this->assertCount(40, array_unique($identificadores));
    }

    public function test_el_mazo_que_se_dibuja_es_el_mismo_que_usa_el_motor(): void
    {
        $dibujadas = array_map(fn (array $carta) => (new Carta(...$carta))->identificador(), Carta::mazo());
        $delMotor = array_map(fn (\App\Motor\Carta $carta) => $carta->id(), Mazo::completo());

        $this->assertSame($delMotor, $dibujadas);
    }

    public function test_no_existen_el_ocho_ni_el_nueve(): void
    {
        foreach ([8, 9] as $numero) {
            try {
                new Carta('oro', $numero);
                $this->fail("El {$numero} no debería existir.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_un_palo_que_no_existe_se_rechaza(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Carta('trebol', 1);
    }

    public function test_cada_numero_dibuja_tantos_palos_como_vale(): void
    {
        foreach ([1, 2, 3, 4, 5, 6, 7] as $numero) {
            $this->assertCount($numero, (new Carta('copa', $numero))->pintas());
        }
    }

    public function test_las_figuras_llevan_su_dibujo_y_un_solo_palo(): void
    {
        $figuras = [10 => 'sota', 11 => 'caballo', 12 => 'rey'];

        foreach ($figuras as $numero => $figura) {
            $carta = new Carta('espada', $numero);

            $this->assertSame($figura, $carta->figura());
            $this->assertCount(1, $carta->pintas());
        }

        $this->assertNull((new Carta('espada', 7))->figura());
    }

    public function test_la_pinta_del_marco_identifica_al_palo(): void
    {
        // El marco tiene dos lados enteros más los tramos de arriba y de abajo.
        // Cada corte parte un tramo en dos: oro 0 cortes, copa 1, espada 2, basto 3.
        $cortes = ['oro' => 0, 'copa' => 1, 'espada' => 2, 'basto' => 3];

        foreach ($cortes as $palo => $cantidad) {
            $tramos = substr_count((new Carta($palo, 1))->marco(), 'M');

            $this->assertSame(2 + 2 * ($cantidad + 1), $tramos, "La pinta de {$palo} no coincide.");
        }
    }
}
