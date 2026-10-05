<?php

namespace App\Motor;

/**
 * Todo lo que un jugador puede hacer en una mano.
 */
enum TipoDeAccion: string
{
    case Jugar = 'jugar';
    case Envido = 'envido';
    case RealEnvido = 'real_envido';
    case FaltaEnvido = 'falta_envido';
    case Flor = 'flor';
    case Contraflor = 'contraflor';
    case ContraflorAlResto = 'contraflor_al_resto';
    case Truco = 'truco';
    case Retruco = 'retruco';
    case ValeCuatro = 'vale_cuatro';
    case Quiero = 'quiero';
    case NoQuiero = 'no_quiero';
    case Mazo = 'mazo';
}
