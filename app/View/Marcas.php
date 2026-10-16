<?php

namespace App\View;

use App\Juego\Logros;
use App\Juego\Mesa;
use App\Juego\Premios;

/**
 * Las dos marcas del menú (elegidas por Román el 14 de octubre de 2026), con el puntito que ya tiene el
 * subrayado de los links: en "Jugar" cuando hay una partida sin terminar, y en el apodo cuando subió de
 * nivel y destrabó algo, o ganó un sello de la libreta, que todavía no vio en su perfil.
 *
 * El menú de arriba y la barra de abajo las preguntan en la misma página: se averiguan una vez por pedido.
 */
final class Marcas
{
    /**
     * Si quien mira tiene una partida sin terminar (o una sala que espera rival): "Jugar" lleva a ella.
     */
    public static function partidaSinTerminar(): bool
    {
        return self::una('partida', fn ($jugador) => app(Mesa::class)->abiertaDe($jugador) !== null);
    }

    /**
     * Si quien mira destrabó un premio que todavía no vio.
     */
    public static function premioNuevo(): bool
    {
        return self::una('premio', fn ($jugador) => (new Premios)->hayNovedad($jugador) || (new Logros)->hayNuevos($jugador));
    }

    private static function una(string $cual, callable $averiguar): bool
    {
        $pedido = request();
        $clave = "marca-{$cual}";

        if (! $pedido->attributes->has($clave)) {
            $jugador = $pedido->user();
            $pedido->attributes->set($clave, $jugador !== null && $averiguar($jugador));
        }

        return $pedido->attributes->get($clave);
    }
}
