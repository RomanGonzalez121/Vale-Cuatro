<?php

namespace App\Jobs;

use App\Juego\Mesa;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Resuelve lo que una partida entre personas estaba esperando: que alguien juegue (si no lo hizo, se
 * va al mazo) o que se reparta la mano siguiente. Sale con la demora del plazo, y lleva el número del
 * último evento que había: si pasó algo desde entonces, no hace nada.
 */
class ResolverPlazo implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $partidaId, public readonly int $evento) {}

    public function handle(Mesa $mesa): void
    {
        $mesa->resolverPlazo($this->partidaId, $this->evento);
    }
}
