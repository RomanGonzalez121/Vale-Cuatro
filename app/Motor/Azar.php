<?php

namespace App\Motor;

use InvalidArgumentException;
use Random\Engine;
use Random\Engine\Mt19937;
use Random\Engine\Secure;

/**
 * La única fuente de azar del motor.
 *
 * Con una semilla da siempre los mismos números (tests, desafíos, repetición).
 * Sin semilla usa el azar seguro del sistema, que es el que corresponde a una
 * partida real: una semilla de 32 bits se podría adivinar probando todas.
 */
final class Azar
{
    private function __construct(private Engine $generador) {}

    public static function deSemilla(int $semilla): self
    {
        return new self(new Mt19937($semilla));
    }

    public static function seguro(): self
    {
        return new self(new Secure);
    }

    /**
     * Un entero entre $minimo y $maximo, los dos incluidos.
     *
     * La cuenta está escrita acá, y no delegada en PHP, para que la misma
     * semilla dé el mismo resultado en cualquier versión.
     */
    public function entero(int $minimo, int $maximo): int
    {
        if ($maximo < $minimo) {
            throw new InvalidArgumentException('El máximo no puede ser menor que el mínimo.');
        }

        $rango = $maximo - $minimo + 1;
        $tope = intdiv(0x100000000, $rango) * $rango;

        // Se descartan los números del final para que todos los resultados salgan con la misma probabilidad.
        do {
            $numero = unpack('V', substr($this->generador->generate(), 0, 4))[1];
        } while ($numero >= $tope);

        return $minimo + $numero % $rango;
    }
}
