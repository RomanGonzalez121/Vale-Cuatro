<?php

namespace App\Juego;

use App\Motor\Accion;
use App\Motor\Azar;

/**
 * El bot Ultra difícil: juega como el Difícil y además lee al rival.
 *
 * Tampoco ve las cartas del otro. Lo que suma sale de lo que cualquier jugador
 * atento tiene a la vista: los tantos que se dijeron, lo que no se cantó y cómo
 * vino jugando el rival en las manos anteriores, que la mesa le recuerda antes
 * de cada jugada.
 *
 * Por ahora decide igual que el Difícil: las lecturas se suman de a una y cada
 * una se mide contra él.
 */
final class BotUltraDificil implements Bot, Recuerda
{
    private readonly BotDificil $base;

    /** @var list<array<string, mixed>> */
    private array $manos = [];

    public function __construct(Azar $azar)
    {
        $this->base = new BotDificil($azar);
    }

    public function recordar(array $manos): void
    {
        $this->manos = $manos;
    }

    public function decidir(array $vista): Accion
    {
        return $this->base->decidir($vista);
    }
}
