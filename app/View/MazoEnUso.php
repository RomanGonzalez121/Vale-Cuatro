<?php

namespace App\View;

use App\Juego\Mazos;
use Illuminate\Http\Request;

/**
 * El mazo con que se dibujan las cartas de la página, y los juegos de piezas que hacen falta en su sprite.
 *
 * La mesa de juego dice cuál es el mazo de quien mira (usar()); las demás páginas dibujan con el de fábrica. Cada
 * carta anota los juegos de palos y de figuras que usa, y el sprite del layout, que se dibuja después del contenido,
 * trae solo esos: a cada página van las piezas de un mazo, no las de todos. Vive en el pedido, como los dorsos.
 */
final class MazoEnUso
{
    private const MAZO = 'mazo-de-la-pagina';

    private const JUEGOS = 'juegos-del-mazo';

    public static function usar(?string $mazo): void
    {
        self::pedido()?->attributes->set(self::MAZO, $mazo ?? Mazos::DE_FABRICA);
    }

    public static function actual(): string
    {
        return self::pedido()?->attributes->get(self::MAZO, Mazos::DE_FABRICA) ?? Mazos::DE_FABRICA;
    }

    /**
     * Anota que la página dibuja una carta de ese mazo: el sprite tiene que traer sus palos y sus figuras.
     */
    public static function anotar(string $mazo): void
    {
        $pedido = self::pedido();

        if ($pedido === null) {
            return;
        }

        $piezas = Mazos::de($mazo);
        $juegos = $pedido->attributes->get(self::JUEGOS, []);
        $juegos["palos:{$piezas['palos']}"] = true;
        $juegos["figuras:{$piezas['figuras']}"] = true;
        $pedido->attributes->set(self::JUEGOS, $juegos);
    }

    /**
     * Si la página necesita ese juego de piezas ("palos" o "figuras"). Los de fábrica van siempre: los usan
     * también los dorsos y los íconos.
     */
    public static function hace(string $pieza, string $juego): bool
    {
        return $juego === 'fabrica' || isset(self::pedido()?->attributes->get(self::JUEGOS, [])["{$pieza}:{$juego}"]);
    }

    /**
     * El pedido en curso. Fuera de Laravel (los tests unitarios de la carta) no hay: se dibuja con el de fábrica.
     */
    private static function pedido(): ?Request
    {
        return function_exists('app') && app()->bound('request') ? app('request') : null;
    }
}
