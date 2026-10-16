<?php

namespace App\Juego;

/**
 * Los mazos que se ganan (tanda 5 de la segunda vuelta de M18 y M20). La carta ya se arma con piezas
 * (App\View\Components\Carta: marco, índice, símbolos de los palos y de las figuras), así que un mazo no redibuja
 * las cuarenta cartas: elige un juego de cada pieza.
 *
 * - La tinta: la de fábrica, la de noche (la carta en negro, con los tonos de noche) o una sola tinta. Es solo
 *   color: se cambia con las variables de la carta.
 * - Los palos: los de fábrica o los de cada baraja propia (grabados, de afiche, de tiza, de taco...).
 * - Las figuras: las de fábrica, las de medio cuerpo (los retratos: cabeza y torso grandes, en un marco en arco) o
 *   las de cuerpo entero (las de los mazos de fósforo) o las catalanas (anchas, de túnica corta y calzas a rayas).
 * - El marco: el de fábrica o el arco de los retratos, una ventana que no pisa los números de las esquinas. Con el
 *   arco, también las cartas de número acomodan sus palos adentro.
 *
 * - La carta: la de fábrica o la de su mundo. Los mazos de nivel alto no son el de fábrica con una pieza cambiada:
 *   cada uno se arma con su propia carta (resources/views/components/cartas), con su marco, sus números y sus
 *   figuras, y se reconoce de lejos (pedido de Román, 16 de octubre de 2026).
 *
 * Cada uno ve su mazo solo en la mesa de juego y en su Colección: las demás páginas siguen con el de fábrica.
 */
final class Mazos
{
    public const DE_FABRICA = 'fabrica';

    /**
     * @var array<string, array{tinta: string, palos: string, figuras: string, marco: string, carta: string}>
     */
    public const TODOS = [
        'fabrica' => ['tinta' => 'fabrica', 'palos' => 'fabrica', 'figuras' => 'fabrica', 'marco' => 'fabrica', 'carta' => 'fabrica'],
        'noche' => ['tinta' => 'noche', 'palos' => 'fabrica', 'figuras' => 'fabrica', 'marco' => 'fabrica', 'carta' => 'fabrica'],
        'una-tinta' => ['tinta' => 'una', 'palos' => 'fabrica', 'figuras' => 'fabrica', 'marco' => 'fabrica', 'carta' => 'fabrica'],
        'clasico' => ['tinta' => 'fabrica', 'palos' => 'clasico', 'figuras' => 'clasico', 'marco' => 'fabrica', 'carta' => 'clasico'],
        'gatos' => ['tinta' => 'fabrica', 'palos' => 'gatos', 'figuras' => 'gatos', 'marco' => 'fabrica', 'carta' => 'gatos'],
        'azulejo' => ['tinta' => 'fabrica', 'palos' => 'azulejo', 'figuras' => 'azulejo', 'marco' => 'fabrica', 'carta' => 'azulejo'],
        'tango' => ['tinta' => 'fabrica', 'palos' => 'tango', 'figuras' => 'tango', 'marco' => 'fabrica', 'carta' => 'tango'],
        'murga' => ['tinta' => 'fabrica', 'palos' => 'murga', 'figuras' => 'murga', 'marco' => 'fabrica', 'carta' => 'murga'],
        'diario' => ['tinta' => 'fabrica', 'palos' => 'diario', 'figuras' => 'diario', 'marco' => 'fabrica', 'carta' => 'diario'],
        'bordado' => ['tinta' => 'fabrica', 'palos' => 'bordado', 'figuras' => 'bordado', 'marco' => 'fabrica', 'carta' => 'bordado'],
        'caja-de-fosforos' => ['tinta' => 'fabrica', 'palos' => 'etiqueta', 'figuras' => 'etiqueta', 'marco' => 'fabrica', 'carta' => 'caja-de-fosforos'],
        'retratos' => ['tinta' => 'fabrica', 'palos' => 'fabrica', 'figuras' => 'medio', 'marco' => 'arco', 'carta' => 'fabrica'],
        'pizarron' => ['tinta' => 'fabrica', 'palos' => 'tiza', 'figuras' => 'tiza', 'marco' => 'fabrica', 'carta' => 'pizarron'],
        'tipografico' => ['tinta' => 'fabrica', 'palos' => 'taco', 'figuras' => 'clise', 'marco' => 'fabrica', 'carta' => 'tipografico'],
        'billete' => ['tinta' => 'fabrica', 'palos' => 'grabado', 'figuras' => 'billete', 'marco' => 'fabrica', 'carta' => 'billete'],
        'afiche' => ['tinta' => 'fabrica', 'palos' => 'macizo', 'figuras' => 'afiche', 'marco' => 'fabrica', 'carta' => 'afiche'],
        'catalan' => ['tinta' => 'fabrica', 'palos' => 'antiguo', 'figuras' => 'catalan-antigua', 'marco' => 'fabrica', 'carta' => 'catalan'],
        'estampilla' => ['tinta' => 'fabrica', 'palos' => 'estampilla', 'figuras' => 'estampilla', 'marco' => 'fabrica', 'carta' => 'estampilla'],
        'fileteado' => ['tinta' => 'fabrica', 'palos' => 'filete', 'figuras' => 'filete', 'marco' => 'fabrica', 'carta' => 'fileteado'],
        'criollo' => ['tinta' => 'fabrica', 'palos' => 'criollo', 'figuras' => 'criollo', 'marco' => 'fabrica', 'carta' => 'criollo'],
        'tarot' => ['tinta' => 'fabrica', 'palos' => 'tarot', 'figuras' => 'tarot', 'marco' => 'fabrica', 'carta' => 'tarot'],
    ];

    /**
     * Las piezas de ese mazo; uno que no existe es el de fábrica.
     *
     * @return array{tinta: string, palos: string, figuras: string, marco: string, carta: string}
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
