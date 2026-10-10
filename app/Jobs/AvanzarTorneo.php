<?php

namespace App\Jobs;

use App\Juego\Torneos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pone al día las llaves de un torneo cuando se cierra una de sus partidas: anota el resultado, juega
 * las partidas entre bots de la ronda y arma la siguiente. Lleva solo el número del torneo: qué falta
 * resolver se decide al correr. Si llega repetido no hace nada.
 */
class AvanzarTorneo implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $torneoId) {}

    public function handle(Torneos $torneos): void
    {
        $torneos->avanzar($this->torneoId);
    }
}
