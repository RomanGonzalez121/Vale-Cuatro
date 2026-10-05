<?php

namespace App\Juego;

use App\Motor\Azar;

/**
 * Los niveles del bot. Sumar uno es agregar un caso acá y su clase.
 */
enum Nivel: int
{
    case Facil = 1;
    case Intermedio = 2;
    case Dificil = 3;

    /**
     * El nivel con el que se entra sin elegir: el botón de la portada.
     */
    public static function porDefecto(): self
    {
        return self::Intermedio;
    }

    public function nombre(): string
    {
        return match ($this) {
            self::Facil => 'Fácil',
            self::Intermedio => 'Intermedio',
            self::Dificil => 'Difícil',
        };
    }

    /**
     * El bot de este nivel. El azar llega de afuera: con semilla en los tests, seguro en una partida real.
     */
    public function bot(Azar $azar): Bot
    {
        return match ($this) {
            self::Facil => new BotFacil($azar),
            self::Intermedio => new BotIntermedio,
            self::Dificil => new BotDificil($azar),
        };
    }
}
