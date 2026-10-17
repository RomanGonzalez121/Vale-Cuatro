<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Los planos de los barrios de la gira (app/Juego/planos-de-gira.php): cada barrio tiene los dos, el acostado y el
 * parado; el recorrido termina en la puerta del bar del jefe; y cada edificio que se pide sabe dibujarse.
 */
class PlanosDeLaGiraTest extends TestCase
{
    /** @return array<string, array<string, array<string, mixed>>> */
    private function planos(): array
    {
        return require __DIR__.'/../../app/Juego/planos-de-gira.php';
    }

    public function test_cada_barrio_tiene_sus_dos_planos(): void
    {
        $fichas = require __DIR__.'/../../app/Juego/fichas-de-gira.php';

        foreach ($fichas as $barrio) {
            foreach (['acostado', 'parado'] as $forma) {
                $this->assertArrayHasKey($forma, $this->planos()[$barrio['clave']] ?? [], "A {$barrio['clave']} le falta el plano {$forma}.");
            }
        }
    }

    public function test_cada_recorrido_termina_en_la_puerta_del_bar(): void
    {
        foreach ($this->planos() as $clave => $formas) {
            foreach ($formas as $forma => $plano) {
                $this->assertGreaterThanOrEqual(3, count($plano['camino']), "{$clave} ({$forma}): el recorrido es muy corto.");
                [$x, $y] = end($plano['camino']);
                [$bx, $by, $bw, $bh] = $plano['bar'];

                $this->assertTrue($x > $bx && $x < $bx + $bw, "{$clave} ({$forma}): el recorrido no llega a la puerta del bar.");
                $this->assertEqualsWithDelta($by + $bh, $y, 25, "{$clave} ({$forma}): el recorrido termina lejos de la puerta del bar.");
            }
        }
    }

    public function test_cada_edificio_sabe_dibujarse(): void
    {
        $vista = file_get_contents(__DIR__.'/../../resources/views/components/gira/edificio.blade.php');

        foreach ($this->planos() as $clave => $formas) {
            foreach ($formas as $plano) {
                foreach ($plano['edificios'] ?? [] as $edificio) {
                    $this->assertStringContainsString("\$tipo === '{$edificio[0]}'", $vista, "{$clave}: no se sabe dibujar «{$edificio[0]}».");
                }
            }
        }
    }

    public function test_los_recorridos_no_son_todos_iguales(): void
    {
        $formas = array_map(fn (array $formas) => json_encode($formas['acostado']['camino']), $this->planos());

        $this->assertSame(count($formas), count(array_unique($formas)), 'Dos barrios repiten el mismo recorrido.');
    }
}
