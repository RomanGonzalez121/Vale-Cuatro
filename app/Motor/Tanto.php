<?php

namespace App\Motor;

/**
 * Los tantos del envido y de la flor, calculados con las tres cartas repartidas.
 */
final class Tanto
{
    /**
     * Dos cartas del mismo palo suman sus valores más 20. Sin dos del mismo palo, vale la más alta.
     * Con tres del mismo palo (una flor callada) se juega con las dos mejores.
     *
     * @param  list<Carta>  $cartas
     */
    public static function deEnvido(array $cartas): int
    {
        $respaldo = self::cartasDelEnvido($cartas);
        $suma = array_sum(array_map(fn (Carta $carta) => $carta->valorDeEnvido(), $respaldo));

        return count($respaldo) === 2 ? $suma + 20 : $suma;
    }

    /**
     * Las cartas que respaldan el tanto: las que muestra quien gana el envido.
     *
     * @param  list<Carta>  $cartas
     * @return list<Carta>
     */
    public static function cartasDelEnvido(array $cartas): array
    {
        $mejor = [];
        $mejorTanto = -1;

        foreach ($cartas as $i => $una) {
            if ($una->valorDeEnvido() > $mejorTanto) {
                [$mejor, $mejorTanto] = [[$una], $una->valorDeEnvido()];
            }

            foreach (array_slice($cartas, $i + 1) as $otra) {
                $tanto = 20 + $una->valorDeEnvido() + $otra->valorDeEnvido();

                if ($una->palo === $otra->palo && $tanto > $mejorTanto) {
                    [$mejor, $mejorTanto] = [[$una, $otra], $tanto];
                }
            }
        }

        return $mejor;
    }

    /**
     * Flor: tres cartas del mismo palo.
     *
     * @param  list<Carta>  $cartas
     */
    public static function tieneFlor(array $cartas): bool
    {
        return count($cartas) === 3 && $cartas[0]->palo === $cartas[1]->palo && $cartas[1]->palo === $cartas[2]->palo;
    }

    /**
     * El tanto de la flor: la suma de las tres más 20.
     *
     * @param  list<Carta>  $cartas
     */
    public static function deFlor(array $cartas): int
    {
        return 20 + array_sum(array_map(fn (Carta $carta) => $carta->valorDeEnvido(), $cartas));
    }
}
