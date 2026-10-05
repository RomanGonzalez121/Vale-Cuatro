<?php

namespace App\Jobs;

use App\Juego\Mesa;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * El turno del bot en una partida. Juega una sola acción; si después le sigue
 * tocando, la mesa encola otro. Lleva solo el número de la partida: lo que hay
 * que jugar se decide al correr, con la partida como esté en ese momento.
 */
class TurnoDelBot implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $partidaId) {}

    public function handle(Mesa $mesa): void
    {
        $mesa->turnoDelBot($this->partidaId);
    }
}
