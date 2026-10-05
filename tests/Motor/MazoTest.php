<?php

namespace Tests\Motor;

use App\Motor\Azar;
use App\Motor\Carta;
use App\Motor\Mazo;
use PHPUnit\Framework\TestCase;

class MazoTest extends TestCase
{
    public function test_el_mazo_tiene_cuarenta_cartas_distintas(): void
    {
        $ids = $this->ids(Mazo::completo());

        $this->assertCount(40, $ids);
        $this->assertCount(40, array_unique($ids));
    }

    public function test_la_misma_semilla_da_siempre_el_mismo_orden(): void
    {
        foreach ([0, 1, 7, 2026, PHP_INT_MAX] as $semilla) {
            $this->assertSame($this->ids(Mazo::mezclado($semilla)), $this->ids(Mazo::mezclado($semilla)));
        }
    }

    public function test_semillas_distintas_dan_ordenes_distintos(): void
    {
        $this->assertNotSame($this->ids(Mazo::mezclado(1)), $this->ids(Mazo::mezclado(2)));
    }

    public function test_mezclar_no_pierde_ni_repite_cartas(): void
    {
        $completo = $this->ids(Mazo::completo());
        sort($completo);

        foreach (range(1, 50) as $semilla) {
            $mezclado = $this->ids(Mazo::mezclado($semilla));
            sort($mezclado);

            $this->assertSame($completo, $mezclado);
        }
    }

    /**
     * Si esto cambia, cambiaron todos los repartos guardados: desafíos y repeticiones dejarían de coincidir.
     * También comprueba que PHP 8.3 (integración continua) y la versión local reparten igual.
     */
    public function test_el_reparto_de_una_semilla_conocida_no_cambia(): void
    {
        $this->assertSame(
            ['3-basto', '7-espada', '12-basto', '3-oro', '11-espada', '3-espada'],
            array_slice($this->ids(Mazo::mezclado(2026)), 0, 6),
        );
    }

    public function test_el_azar_respeta_los_limites_y_llega_a_los_dos_extremos(): void
    {
        $azar = Azar::deSemilla(3);
        $salieron = [];

        foreach (range(1, 400) as $_) {
            $salieron[$azar->entero(1, 6)] = true;
        }

        ksort($salieron);

        $this->assertSame([1, 2, 3, 4, 5, 6], array_keys($salieron));
    }

    public function test_el_azar_seguro_tambien_mezcla_un_mazo_completo(): void
    {
        $mezclado = $this->ids(Mazo::mezcladoCon(Azar::seguro()));

        $this->assertCount(40, array_unique($mezclado));
    }

    /**
     * @param  list<Carta>  $cartas
     * @return list<string>
     */
    private function ids(array $cartas): array
    {
        return array_map(fn (Carta $carta) => $carta->id(), $cartas);
    }
}
