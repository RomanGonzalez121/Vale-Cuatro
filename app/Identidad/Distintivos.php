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
        // Los números del truco, con el trazo de los fósforos: cada uno arranca en su cabeza.
        // El 7: los sietes bravos.
        'siete' => ['M7.4 5H18.5L10.5 20.8', [[4.8, 5, 2.3]]],
        // El 4: vale cuatro. La raya del medio termina en la cabeza.
        'cuatro' => ['M14.5 20.8V4.4L5 15.6H17.6', [[19.9, 15.6, 2.3]]],
        // El 30: la partida.
        'treinta' => ['M4.6 4.8H10V12.2H5.6M10 12.2V19.6H2.6M14.4 7.4V19.6H21.4V4.8H16.8', [[2.6, 4.8, 2], [14.4, 4.8, 2]]],
        // El 33: el mejor envido.
        'treinta-y-tres' => ['M4.6 4.8H10V12.2H5.6M10 12.2V19.6H2.6M16.4 4.8H21.8V12.2H17.4M21.8 12.2V19.6H14.4', [[2.6, 4.8, 2], [14.4, 4.8, 2]]],
        // Los cuatro palos: son los íconos de los palos, y cada uno elige cuál lleva.
        'palo-oro' => Iconos::TODOS['oro'],
        'palo-copa' => Iconos::TODOS['copa'],
        'palo-espada' => Iconos::TODOS['espada'],
        'palo-basto' => Iconos::TODOS['basto'],
    ];
}
