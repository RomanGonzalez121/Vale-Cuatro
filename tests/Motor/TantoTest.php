<?php

namespace Tests\Motor;

use App\Motor\Carta;
use App\Motor\Tanto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TantoTest extends TestCase
{
    /**
     * @param  list<string>  $cartas
     * @param  list<string>  $respaldo
     */
    #[DataProvider('tantos')]
    public function test_tanto_del_envido(array $cartas, int $tanto, array $respaldo): void
    {
        $mano = $this->cartas($cartas);

        $this->assertSame($tanto, Tanto::deEnvido($mano));
        $this->assertEqualsCanonicalizing($respaldo, array_map(fn (Carta $carta) => $carta->id(), Tanto::cartasDelEnvido($mano)));
    }

    public static function tantos(): array
    {
        return [
            'dos del mismo palo suman más 20' => [['7-oro', '6-oro', '1-espada'], 33, ['7-oro', '6-oro']],
            'una figura vale 0' => [['12-copa', '5-copa', '3-basto'], 25, ['12-copa', '5-copa']],
            'dos figuras del mismo palo dan 20' => [['10-oro', '11-oro', '7-espada'], 20, ['10-oro', '11-oro']],
            'sin dos del mismo palo vale la más alta' => [['3-espada', '11-basto', '4-copa'], 4, ['4-copa']],
            'tres figuras de palos distintos dan 0' => [['10-espada', '11-basto', '12-copa'], 0, ['10-espada']],
            'con tres del mismo palo se juega con las dos mejores' => [['7-espada', '6-espada', '2-espada'], 33, ['7-espada', '6-espada']],
            'el mejor par no siempre es el de las dos primeras' => [['1-oro', '12-oro', '7-oro'], 28, ['1-oro', '7-oro']],
        ];
    }

    public function test_flor_son_tres_cartas_del_mismo_palo(): void
    {
        $this->assertTrue(Tanto::tieneFlor($this->cartas(['7-espada', '6-espada', '2-espada'])));
        $this->assertFalse(Tanto::tieneFlor($this->cartas(['7-espada', '6-espada', '2-basto'])));
    }

    public function test_el_tanto_de_la_flor_suma_las_tres_mas_veinte(): void
    {
        $this->assertSame(35, Tanto::deFlor($this->cartas(['7-espada', '6-espada', '2-espada'])));
        $this->assertSame(27, Tanto::deFlor($this->cartas(['7-copa', '12-copa', '10-copa'])));
        $this->assertSame(20, Tanto::deFlor($this->cartas(['10-oro', '11-oro', '12-oro'])));
    }

    /**
     * @param  list<string>  $ids
     * @return list<Carta>
     */
    private function cartas(array $ids): array
    {
        return array_map(Carta::de(...), $ids);
    }
}
