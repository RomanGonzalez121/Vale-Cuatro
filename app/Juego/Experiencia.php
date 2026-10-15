<?php

namespace App\Juego;

use App\Models\Jugador;

/**
 * Cuánto dice un jugador que sabe de truco. Lo declara él en su perfil y solo ajusta la experiencia:
 * contra qué bot entra sin elegir y qué ayudas ve. No toca las reglas ni lo que recibe el rival,
 * y no tiene nada que ver con la categoría, que se gana jugando.
 */
enum Experiencia: int
{
    case Aprendiendo = 1;
    case Seguido = 2;
    case DeAnios = 3;

    /**
     * El bot contra el que entra ese jugador cuando no elige nivel. Sin nada declarado, el de siempre.
     */
    public static function nivelPara(?Jugador $jugador): Nivel
    {
        return $jugador?->experiencia?->nivel() ?? Nivel::porDefecto();
    }

    public function nombre(): string
    {
        return match ($this) {
            self::Aprendiendo => 'Estoy aprendiendo',
            self::Seguido => 'Juego seguido',
            self::DeAnios => 'Juego hace años',
        };
    }

    /**
     * Lo que cambia al declararlo, dicho en una frase para quien elige.
     */
    public function detalle(): string
    {
        return match ($this) {
            self::Aprendiendo => 'Entrás contra la máquina fácil y ves tu tanto de envido ya sumado junto a tus cartas.',
            self::Seguido => 'Entrás contra la máquina intermedia, sin ayudas.',
            self::DeAnios => 'Entrás contra la máquina difícil, sin ayudas.',
        };
    }

    public function nivel(): Nivel
    {
        return match ($this) {
            self::Aprendiendo => Nivel::Facil,
            self::Seguido => Nivel::Intermedio,
            self::DeAnios => Nivel::Dificil,
        };
    }

    /**
     * Si la mesa le muestra su tanto de envido ya sumado.
     */
    public function veSuTanto(): bool
    {
        return $this === self::Aprendiendo;
    }
}
