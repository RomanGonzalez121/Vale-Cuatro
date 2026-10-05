<?php

namespace App\Motor;

use InvalidArgumentException;

/**
 * Los asientos y los dos equipos.
 *
 * Los equipos van intercalados: los asientos pares son un equipo y los impares
 * el otro, así que los compañeros quedan enfrentados. El mano a mano es la
 * misma mesa con dos asientos, uno por equipo.
 */
final readonly class Mesa
{
    public function __construct(public int $asientos = 2)
    {
        if (! in_array($asientos, [2, 4], true)) {
            throw new InvalidArgumentException('La mesa es de dos o de cuatro asientos.');
        }
    }

    public function existe(int $asiento): bool
    {
        return $asiento >= 0 && $asiento < $this->asientos;
    }

    public function equipoDe(int $asiento): int
    {
        return $asiento % 2;
    }

    public function rivalDe(int $equipo): int
    {
        return 1 - $equipo;
    }

    /**
     * @return list<int>
     */
    public function asientosDe(int $equipo): array
    {
        return array_values(array_filter(range(0, $this->asientos - 1), fn (int $asiento) => $asiento % 2 === $equipo));
    }

    /**
     * Todos los asientos en orden de juego, empezando por el que se indica.
     *
     * @return list<int>
     */
    public function rondaDesde(int $asiento): array
    {
        return array_map(fn (int $paso) => ($asiento + $paso) % $this->asientos, range(0, $this->asientos - 1));
    }
}
