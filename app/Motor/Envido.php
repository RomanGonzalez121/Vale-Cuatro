<?php

namespace App\Motor;

/**
 * Las cadenas de envido: qué se puede cantar después de qué y cuánto vale cada una.
 *
 * La cadena es la lista de cantos en el orden en que se dijeron, por ejemplo
 * ['envido', 'envido', 'real_envido'].
 */
final class Envido
{
    public const ENVIDO = 'envido';

    public const REAL = 'real_envido';

    public const FALTA = 'falta_envido';

    private const ALTURA = [self::ENVIDO => 1, self::REAL => 2, self::FALTA => 3];

    private const PUNTOS = [self::ENVIDO => 2, self::REAL => 3];

    /**
     * Siempre se sube: se pueden saltear pasos, no volver atrás. El envido es el único que se repite, y una sola vez.
     *
     * @param  list<string>  $cadena
     */
    public static function puedeSeguir(array $cadena, string $canto): bool
    {
        if ($cadena === []) {
            return true;
        }

        if ($canto === self::ENVIDO) {
            return $cadena === [self::ENVIDO];
        }

        return self::ALTURA[$canto] > self::ALTURA[$cadena[count($cadena) - 1]];
    }

    /**
     * Lo que suma quien gana el envido querido.
     *
     * @param  list<string>  $cadena
     * @param  array{0: int, 1: int}  $tanteo
     */
    public static function querido(array $cadena, array $tanteo, int $puntosParaGanar): int
    {
        // La falta reemplaza lo acumulado, no se suma.
        if (in_array(self::FALTA, $cadena, true)) {
            return self::falta($tanteo, $puntosParaGanar);
        }

        return self::suma($cadena);
    }

    /**
     * Lo que suma quien cantó último si no se lo quieren: los cantos anteriores, o 1 si era el primero.
     *
     * @param  list<string>  $cadena
     */
    public static function noQuerido(array $cadena): int
    {
        return max(1, self::suma(array_slice($cadena, 0, -1)));
    }

    /**
     * La falta: lo que le falta al puntero (el que va ganando) para cerrar la partida.
     *
     * @param  array{0: int, 1: int}  $tanteo
     */
    public static function falta(array $tanteo, int $puntosParaGanar): int
    {
        return $puntosParaGanar - max($tanteo);
    }

    /**
     * @param  list<string>  $cadena
     */
    private static function suma(array $cadena): int
    {
        return array_sum(array_map(fn (string $canto) => self::PUNTOS[$canto], $cadena));
    }
}
