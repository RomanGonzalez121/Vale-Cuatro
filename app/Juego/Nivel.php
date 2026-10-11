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
    case UltraDificil = 4;

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
            self::UltraDificil => 'Ultra difícil',
        };
    }

    /**
     * El nombre en una palabra, para la fila donde se elige: los cuatro tienen que medir parecido.
     */
    public function corto(): string
    {
        return match ($this) {
            self::Facil => 'Fácil',
            self::Intermedio => 'Medio',
            self::Dificil => 'Difícil',
            self::UltraDificil => 'Ultra',
        };
    }

    /**
     * Cómo juega, dicho en una línea para quien elige contra quién jugar.
     */
    public function detalle(): string
    {
        return match ($this) {
            self::Facil => 'Tira cualquier cosa. Para aprender.',
            self::Intermedio => 'Juega de frente: si canta, tiene.',
            self::Dificil => 'Saca cuentas y cada tanto miente.',
            self::UltraDificil => 'Te lee: se acuerda de cómo jugás.',
        };
    }

    /**
     * Lo mismo, dicho de los tres bots del truco de a cuatro: el compañero y los dos rivales son del mismo nivel.
     */
    public function detalleDeACuatro(): string
    {
        return match ($this) {
            self::Facil => 'Tiran cualquier cosa. Para aprender.',
            self::Intermedio => 'Juegan de frente: si cantan, tienen.',
            self::Dificil => 'Sacan cuentas y cada tanto mienten.',
            self::UltraDificil => 'Leen la mesa y arriesgan según el tanteo.',
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
            self::UltraDificil => new BotUltraDificil($azar),
        };
    }

    /**
     * El bot de este nivel para el truco de a cuatro, donde se juega en pareja y con señas.
     * El Fácil es el mismo: tira cualquier cosa, sea de a dos o de a cuatro.
     */
    public function botDePareja(Azar $azar): Bot
    {
        return $this === self::Facil ? new BotFacil($azar) : new BotDePareja($this, $azar);
    }
}
