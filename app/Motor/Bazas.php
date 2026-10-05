<?php

namespace App\Motor;

/**
 * Quién gana una baza y quién gana la mano, con las pardas del reglamento.
 */
final class Bazas
{
    /**
     * El asiento que gana la baza, o null si es parda.
     *
     * Si la carta más alta la comparten dos compañeros no hay parda: gana su
     * equipo, y la baza se la lleva el que la jugó primero.
     *
     * @param  list<array{0: int, 1: Carta}>  $jugadas  Asiento y carta, en el orden en que se jugaron.
     */
    public static function ganadorDeBaza(array $jugadas, Mesa $mesa): ?int
    {
        $masAlta = max(array_map(fn (array $jugada) => $jugada[1]->jerarquia(), $jugadas));
        $punteros = array_values(array_filter($jugadas, fn (array $jugada) => $jugada[1]->jerarquia() === $masAlta));
        $equipos = array_unique(array_map(fn (array $jugada) => $mesa->equipoDe($jugada[0]), $punteros));

        return count($equipos) === 1 ? $punteros[0][0] : null;
    }

    /**
     * El equipo que gana la mano con las bazas jugadas hasta ahora, o null si hay que seguir.
     *
     * @param  list<int|null>  $resultados  El equipo que ganó cada baza; null si fue parda.
     */
    public static function ganadorDeMano(array $resultados, int $equipoMano): ?int
    {
        $primera = $resultados[0] ?? null;
        $segunda = $resultados[1] ?? null;
        $tercera = $resultados[2] ?? null;

        if (count($resultados) < 2) {
            return null;
        }

        if (count($resultados) === 2) {
            if ($primera !== null && $segunda !== null) {
                return $primera === $segunda ? $primera : null;
            }

            // Parda la primera: gana quien gana la segunda. Parda la segunda: quien ganó la primera.
            // Pardas las dos: se define en la tercera.
            return $primera ?? $segunda;
        }

        // Se llega a la tercera con una y una o con dos pardas: quien la gana, gana la mano.
        if ($tercera !== null) {
            return $tercera;
        }

        // Parda la tercera: quien ganó la primera. Pardas las tres: el mano.
        return $primera ?? $equipoMano;
    }
}
