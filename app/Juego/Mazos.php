<?php

namespace App\Juego;

/**
 * Los mazos que se ganan (tanda 5 de la segunda vuelta de M18 y M20). La carta ya se arma con piezas
 * (App\View\Components\Carta: marco, índice, símbolos de los palos y de las figuras), así que un mazo no redibuja
 * las cuarenta cartas: elige un juego de cada pieza.
 *
 * - La tinta: la de fábrica, la de noche (la carta en negro, con los tonos de noche) o una sola tinta. Es solo
 *   color: se cambia con las variables de la carta.
 * - Los palos: los de fábrica o los de trazo de fósforo (la línea con cabeza de los íconos).
 * - Las figuras: las de fábrica, las de medio cuerpo (los retratos: cabeza y torso grandes, en un marco en arco) o
 *   las de cuerpo entero (las de los mazos de fósforo).
 * - El marco: el de fábrica o el arco de los retratos, una ventana que no pisa los números de las esquinas. Con el
 *   arco, también las cartas de número acomodan sus palos adentro.
 *
 * Cada uno ve su mazo solo en la mesa de juego y en su Colección: las demás páginas siguen con el de fábrica.
 */
final class Mazos
{
    public const DE_FABRICA = 'fabrica';

    /**
     * @var array<string, array{tinta: string, palos: string, figuras: string, marco: string}>
     */
    public const TODOS = [
        'fabrica' => ['tinta' => 'fabrica', 'palos' => 'fabrica', 'figuras' => 'fabrica', 'marco' => 'fabrica'],
        'noche' => ['tinta' => 'noche', 'palos' => 'fabrica', 'figuras' => 'fabrica', 'marco' => 'fabrica'],
        'una-tinta' => ['tinta' => 'una', 'palos' => 'fabrica', 'figuras' => 'fabrica', 'marco' => 'fabrica'],
        'palos-de-fosforo' => ['tinta' => 'fabrica', 'palos' => 'fosforo', 'figuras' => 'entero', 'marco' => 'fabrica'],
        'retratos' => ['tinta' => 'fabrica', 'palos' => 'fabrica', 'figuras' => 'medio', 'marco' => 'arco'],
        'fosforo-de-noche' => ['tinta' => 'noche', 'palos' => 'fosforo', 'figuras' => 'entero', 'marco' => 'fabrica'],
        'imprenta' => ['tinta' => 'una', 'palos' => 'fabrica', 'figuras' => 'medio', 'marco' => 'arco'],
    ];

    /**
     * Las piezas de ese mazo; uno que no existe es el de fábrica.
     *
     * @return array{tinta: string, palos: string, figuras: string, marco: string}
     */
    public static function de(?string $mazo): array
    {
        return self::TODOS[$mazo ?? self::DE_FABRICA] ?? self::TODOS[self::DE_FABRICA];
    }

    /**
     * Cómo se llama el símbolo de una pieza en el sprite: los de fábrica sin nada, los demás con su juego
     * ("palo-oro" y "palo-oro-fosforo").
     */
    public static function simbolo(string $pieza, string $juego): string
    {
        return $juego === 'fabrica' ? $pieza : "{$pieza}-{$juego}";
    }
}
