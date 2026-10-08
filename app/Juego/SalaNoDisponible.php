<?php

namespace App\Juego;

use RuntimeException;

/**
 * No se puede entrar a una sala. El mensaje está escrito para mostrárselo a la persona.
 */
final class SalaNoDisponible extends RuntimeException {}
