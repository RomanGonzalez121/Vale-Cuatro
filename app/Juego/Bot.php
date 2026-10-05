<?php

namespace App\Juego;

use App\Motor\Accion;

/**
 * Un rival que juega solo.
 *
 * Decide con la vista de su asiento, que es lo mismo que recibe un jugador:
 * nunca ve las cartas del otro. Tiene que devolver una de las acciones que la
 * vista trae como válidas.
 */
interface Bot
{
    /**
     * @param  array<string, mixed>  $vista  Lo que entrega Partida::vistaPara() para el asiento del bot.
     */
    public function decidir(array $vista): Accion;
}
