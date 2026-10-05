<?php

namespace App\Motor;

use DomainException;

/**
 * Lo que tira el motor cuando alguien intenta algo que el reglamento no permite.
 * El mensaje dice por qué, en palabras que se le pueden mostrar al jugador.
 */
final class AccionInvalida extends DomainException {}
