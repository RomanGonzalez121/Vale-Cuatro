<?php

namespace App\Juego;

/**
 * Un bot que juega en pareja y atiende las señas de su compañero.
 *
 * Antes de decidir se le pasan las señas que su compañero le hizo en la mano en juego. Es todo lo que
 * sabe de las cartas del otro: lo que cualquier jugador sabría de su compañero mirándolo.
 */
interface RecibeSenas
{
    /**
     * @param  list<string>  $senas  Las señas de su compañero en esta mano (ver Senas).
     */
    public function recibirSenas(array $senas): void;
}
