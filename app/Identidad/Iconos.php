<?php

namespace App\Identidad;

use InvalidArgumentException;

/**
 * Los íconos propios, sobre una grilla de 24 px. Todos salen del mismo trazo que
 * los fósforos: línea recta de 2 px que termina en un punto lleno (la cabeza).
 *
 * Cada ícono es [trazos, cabezas], y cada cabeza es [cx, cy, radio]. Están acá,
 * y no en la vista, porque los dibujan dos piezas: el ícono suelto y la carta de modo.
 */
final class Iconos
{
    public const TODOS = [
        'oro' => ['M9 3.5H15L20.5 9V15L15 20.5H9L3.5 15V9Z', [[12, 12, 2.4]]],
        'copa' => ['M5.5 4.5H18.5M7 4.5 9.5 12H14.5L17 4.5M12 12V16M8 20.5H16', [[12, 17.8, 2.2]]],
        'espada' => ['M12 2.5V15.5M7.5 15.5H16.5M12 15.5V18.5', [[12, 20.6, 2.2]]],
        'basto' => ['M6 19 15.5 8M8.7 12.4 12.3 15.6', [[17.1, 6.2, 2.6]]],
        'envido' => ['M3.5 6H10.5V18H3.5ZM13.5 6H20.5V18H13.5Z', [[7, 12, 2], [17, 12, 2]]],
        'truco' => ['M13.5 2.5 7 13H12L9.6 18.6', [[8.7, 20.8, 2.2]]],
        'mazo' => ['M5 14.5H19V20.5H5ZM12 2.5V8.5', [[12, 10.8, 2.2]]],
        'mano' => ['M12 17.5V11.5M13.4 17.9 16.2 12.6M10.6 17.9 7.8 12.6M14.5 18.8 19.5 15.5M9.5 18.8 4.5 15.5', [[12, 9.1, 2], [17.4, 10.4, 2], [6.6, 10.4, 2], [21.4, 14.2, 2], [2.6, 14.2, 2]]],
        'repartir' => ['M9 3.5H15V10.5H9ZM8 13 5 17.4M16 13 19 17.4M12 13.5V17.5', [[3.7, 19.3, 2], [20.3, 19.3, 2], [12, 19.8, 2]]],
        'quiero' => ['M4.5 12.5 9.5 17.5 17.6 8.4', [[19.2, 6.6, 2.2]]],
        'no-quiero' => ['M6 18 16.5 7.5M18 18 7.5 7.5', [[18.2, 5.8, 2.2], [5.8, 5.8, 2.2]]],
        'tiempo' => ['M9 3.5H15L20.5 9V15L15 20.5H9L3.5 15V9ZM12 12V7.5M12 12 15.5 14', [[12, 12, 1.9]]],
        // El ritmo de la mesa: dos puntas hacia adelante, como el "adelantar" de cualquier reproductor.
        'ritmo' => ['M4.5 6.5 8.6 10.6M4.5 17.5 8.6 13.4M13 6.5 17.1 10.6M13 17.5 17.1 13.4', [[10.4, 12, 2.2], [18.9, 12, 2.2]]],
        'bot' => ['M5.5 9.5H18.5V19.5H5.5ZM12 9.5V6.2', [[12, 4, 2.2], [9.5, 14.5, 1.4], [14.5, 14.5, 1.4]]],
        'invitar' => ['M8 21V11M17 8V16M13 12H21', [[8, 7.6, 2.6]]],
        'ranking' => ['M6 20.5V15M12 20.5V9M18 20.5V12.5', [[6, 12.7, 2.2], [12, 6.7, 2.2], [18, 10.2, 2.2]]],
        'repetir' => ['M18.5 11.5V18.5H5.5V5.5H13', [[15.4, 5.5, 2.2]]],
        'jugador' => ['M5.5 20.5 8 13.5H16L18.5 20.5', [[12, 7.6, 2.8]]],
        'salir' => ['M14 3.5H19.5V20.5H14M10 20 8 11.5M8.8 15 5.5 20M8.2 12.6 12.2 13.8', [[7.4, 8.6, 2.4]]],
        'sonido' => ['M4.5 9.5H8L12.5 5.5V18.5L8 14.5H4.5ZM15.5 12H18.3M15.5 9 17.2 7.3M15.5 15 17.2 16.7', [[20.4, 12, 1.8], [18.6, 5.9, 1.8], [18.6, 18.1, 1.8]]],
        'flor' => ['M12 20.5V8M12 20.5 6.9 10.4M12 20.5 17.1 10.4', [[12, 5.6, 2.2], [5.8, 8.2, 2.2], [18.2, 8.2, 2.2]]],
        // Los juegos: dos fósforos (uno por jugador), cuatro en la mesa, las llaves del torneo, un fósforo de bandera y los escalones.
        'mano-a-mano' => ['M8 20.5V8.4M16 20.5V8.4', [[8, 5.9, 2.4], [16, 5.9, 2.4]]],
        'de-a-cuatro' => ['M12 6.6V17.4M6.6 12H17.4', [[12, 4.3, 2.2], [12, 19.7, 2.2], [4.3, 12, 2.2], [19.7, 12, 2.2]]],
        'torneo' => ['M3.5 5H10.5V19H3.5M10.5 12H16.2', [[18.6, 12, 2.4]]],
        'desafio' => ['M6.5 21V5.8M6.5 7.5H18.5L15.5 11L18.5 14.5H6.5', [[6.5, 3.6, 2.2]]],
        'escalera' => ['M3.5 19.5H8.5V14.5H13.5V9.5H18.5V7', [[18.5, 4.7, 2.2]]],
    ];

    /**
     * @return array{0: string, 1: list<array{0: float|int, 1: float|int, 2: float|int}>}
     */
    public static function de(string $nombre): array
    {
        return self::TODOS[$nombre] ?? throw new InvalidArgumentException("El ícono [{$nombre}] no existe.");
    }
}
