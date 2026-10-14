<?php

namespace App\Juego;

/**
 * La categoría de un jugador: se gana jugando y no da ninguna ventaja en la mesa.
 *
 * Sale de las partidas ganadas que cuentan para el ranking, que a su vez salen de los eventos de
 * cada partida. No se guarda: se calcula cada vez que hace falta.
 */
enum Categoria: string
{
    case Novato = 'novato';
    case Medio = 'medio';
    case Avanzado = 'avanzado';

    /**
     * La categoría que corresponde a esa cantidad de partidas ganadas.
     */
    public static function con(int $ganadas): self
    {
        return match (true) {
            $ganadas >= self::Avanzado->desde() => self::Avanzado,
            $ganadas >= self::Medio->desde() => self::Medio,
            default => self::Novato,
        };
    }

    /**
     * Desde cuántas partidas ganadas se tiene.
     */
    public function desde(): int
    {
        return match ($this) {
            self::Novato => 0,
            self::Medio => 10,
            self::Avanzado => 40,
        };
    }

    public function nombre(): string
    {
        return match ($this) {
            self::Novato => 'Novato',
            self::Medio => 'Medio',
            self::Avanzado => 'Avanzado',
        };
    }

    /**
     * La que sigue, o null si ya es la más alta.
     */
    public function siguiente(): ?self
    {
        return match ($this) {
            self::Novato => self::Medio,
            self::Medio => self::Avanzado,
            self::Avanzado => null,
        };
    }
}
