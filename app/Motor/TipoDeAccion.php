<?php

namespace App\Motor;

/**
 * Todo lo que un jugador puede hacer en una mano.
 */
enum TipoDeAccion: string
{
    case Jugar = 'jugar';
    case Mazo = 'mazo';
}
