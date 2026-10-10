<?php

namespace App\Juego;

use RuntimeException;

/**
 * Lo que se pidió de un torneo no se puede hacer ahora. El mensaje dice por qué, para mostrárselo a la persona.
 */
final class TorneoNoDisponible extends RuntimeException {}
