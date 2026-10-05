<?php

namespace App\Motor;

/**
 * En qué momento está la partida.
 */
enum Fase: string
{
    /** Entre dos manos: falta repartir. */
    case PorRepartir = 'por_repartir';

    /** Hay una mano en juego. */
    case Jugando = 'jugando';

    /** Alguien llegó a los puntos de la partida. */
    case Terminada = 'terminada';
}
