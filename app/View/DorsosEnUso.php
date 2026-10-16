<?php

namespace App\View;

use App\Juego\Premios;

/**
 * Los dorsos que dibuja la página: a cada página van solo las plantillas de esos, no las de todos.
 *
 * Cada <x-dorso> anota el suyo al dibujarse. El mazo del layout se dibuja después que el contenido de la página
 * (Blade arma el contenido de un componente antes que el componente), así que cuando llega ya sabe cuáles van.
 * El dorso de quien mira va siempre: la mesa lo copia desde JavaScript para las cartas del rival.
 *
 * La lista vive en el pedido, como el dorso de quien mira: cada página empieza la suya.
 */
final class DorsosEnUso
{
    private const CLAVE = 'dorsos-en-uso';

    public function usar(string $dorso): void
    {
        $pedido = request();
        $pedido->attributes->set(self::CLAVE, [...$pedido->attributes->get(self::CLAVE, []), $dorso => true]);
    }

    public function hace(string $dorso): bool
    {
        return isset(request()->attributes->get(self::CLAVE, [])[$dorso]) || $dorso === Premios::dorsoPropio();
    }
}
