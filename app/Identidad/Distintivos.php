<?php

namespace App\Identidad;

/**
 * Los distintivos: el sello chico que va al lado del apodo. Se dibujan con el trazo de los íconos propios
 * (App\Identidad\Iconos): rectas de 2 px sobre una grilla de 24, que terminan en una cabeza llena, en una
 * sola tinta. Lo que se repite es el trazo, no el tema: no son todos fósforos.
 *
 * Cada distintivo es [trazos, cabezas], y cada cabeza es [cx, cy, radio]. Quién tiene cuál está en
 * App\Juego\Premios::POR_NIVEL.
 */
final class Distintivos
{
    public const TODOS = [
        'un-fosforo' => ['M6.5 20.5 15.9 8.3', [[17.5, 6.2, 2.4]]],
        // El isotipo: el grupo de cuatro del tanteador.
        'cuadrado' => ['M3.6 16.9V7M7.4 3.6H17.2M20.4 7.4V17.2M16.6 20.4H6.8', [[3.6, 4, 2.1], [20, 3.6, 2.1], [20.4, 20, 2.1], [4, 20.4, 2.1]]],
        'grupo-de-cinco' => ['M3.6 16.9V7M7.4 3.6H17.2M20.4 7.4V17.2M16.6 20.4H6.8M8 16 14.6 9.4', [[3.6, 4, 2.1], [20, 3.6, 2.1], [20.4, 20, 2.1], [4, 20.4, 2.1], [16.4, 7.6, 2]]],
        // Malas y buenas: el 1 y el 5, cada uno un trazo que termina en su cabeza.
        'quince' => ['M5 20.5V7.2M10.5 20.5H19V12.3H11.5V4.5H16.3', [[5, 4.6, 2.2], [18.7, 4.5, 2.2]]],
        // Más parados que el "No quiero", y uno pasa por debajo del otro (el corte lo dice): no se confunden.
        'cruzados' => ['M7.5 21 14.9 5.8M16.5 21 13.6 15M11.2 10.2 9.1 5.8', [[16, 3.6, 2.2], [8, 3.6, 2.2]]],
    ];
}
