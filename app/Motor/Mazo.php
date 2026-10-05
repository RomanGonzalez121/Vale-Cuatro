<?php

namespace App\Motor;

/**
 * La baraja española de 40 cartas: sin 8 ni 9.
 */
final class Mazo
{
    /**
     * Las 40 cartas, ordenadas por palo y número.
     *
     * @return list<Carta>
     */
    public static function completo(): array
    {
        $cartas = [];

        foreach (Palo::cases() as $palo) {
            foreach (Carta::NUMEROS as $numero) {
                $cartas[] = new Carta($palo, $numero);
            }
        }

        return $cartas;
    }

    /**
     * El mazo mezclado con una semilla: la misma semilla da siempre el mismo orden.
     *
     * @return list<Carta>
     */
    public static function mezclado(int $semilla): array
    {
        return self::mezcladoCon(Azar::deSemilla($semilla));
    }

    /**
     * @return list<Carta>
     */
    public static function mezcladoCon(Azar $azar): array
    {
        $cartas = self::completo();

        // Fisher-Yates: cada carta cambia de lugar con otra elegida al azar entre las que faltan.
        for ($i = count($cartas) - 1; $i > 0; $i--) {
            $j = $azar->entero(0, $i);
            [$cartas[$i], $cartas[$j]] = [$cartas[$j], $cartas[$i]];
        }

        return $cartas;
    }
}
