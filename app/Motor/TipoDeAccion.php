<?php

namespace App\Motor;

/**
 * Todo lo que un jugador puede hacer en una mano.
 */
enum TipoDeAccion: string
{
    case Jugar = 'jugar';
    case Truco = 'truco';
    case Retruco = 'retruco';
    case ValeCuatro = 'vale_cuatro';
    case Quiero = 'quiero';
    case NoQuiero = 'no_quiero';
    case Mazo = 'mazo';
}
