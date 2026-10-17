<?php

namespace App\Motor;

use InvalidArgumentException;

/**
 * Los asientos y los dos equipos.
 *
 * De a dos y de a cuatro los equipos van intercalados: los asientos pares son un
 * equipo y los impares el otro, así que los compañeros quedan enfrentados. El mano
 * a mano es la misma mesa con dos asientos, uno por equipo.
 *
 * De a tres es el gallito: el gallo (equipo 0) juega solo contra los otros dos
 * (equipo 1, la pareja). El gallo cambia en cada mano, así que la mesa de tres
 * sabe quién es el gallo de la mano en juego.
 */
final readonly class Mesa
{
    public function __construct(public int $asientos = 2, public int $gallo = 0)
    {
        if (! in_array($asientos, [2, 3, 4], true)) {
            throw new InvalidArgumentException('La mesa es de dos, de tres o de cuatro asientos.');
        }

        if (! $this->existe($gallo)) {
            throw new InvalidArgumentException('El gallo tiene que ser un asiento de la mesa.');
        }
    }

    public function esGallito(): bool
    {
        return $this->asientos === 3;
    }

    /**
     * La misma mesa con otro gallo. De a dos y de a cuatro no cambia nada.
     */
    public function conGallo(int $asiento): self
    {
        return $this->esGallito() ? new self($this->asientos, $asiento) : $this;
    }

    public function existe(int $asiento): bool
    {
        return $asiento >= 0 && $asiento < $this->asientos;
    }

    public function equipoDe(int $asiento): int
    {
        if ($this->esGallito()) {
            return $asiento === $this->gallo ? 0 : 1;
        }

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
        return array_values(array_filter(range(0, $this->asientos - 1), fn (int $asiento) => $this->equipoDe($asiento) === $equipo));
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
