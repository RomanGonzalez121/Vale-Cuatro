<?php

namespace App\Juego;

/**
 * La categoría de un jugador: se gana jugando y no da ninguna ventaja en la mesa.
 *
 * Es un tramo del camino de niveles (App\Juego\Camino): novato del 1 al 9, medio del 10 al 24 y
 * avanzado desde el 25. Decidido por Román el 14 de octubre de 2026.
 */
enum Categoria: string
{
    case Novato = 'novato';
    case Medio = 'medio';
    case Avanzado = 'avanzado';

    /**
     * La categoría que corresponde a ese nivel.
     */
    public static function delNivel(int $nivel): self
    {
        return match (true) {
            $nivel >= self::Avanzado->desde() => self::Avanzado,
            $nivel >= self::Medio->desde() => self::Medio,
            default => self::Novato,
        };
    }

    /**
     * Desde qué nivel se tiene.
     */
    public function desde(): int
    {
        return match ($this) {
            self::Novato => 1,
            self::Medio => 10,
            self::Avanzado => 25,
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
