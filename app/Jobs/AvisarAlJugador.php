<?php

namespace App\Jobs;

use App\Avisos\Avisos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Le manda a un jugador un aviso de su partida ("Te toca", "Te cantaron truco", "Tu rival se sentó").
 *
 * Sale de la cola unos segundos después de que pasó lo que avisa. Recién al correr se decide si todavía
 * corresponde: si en ese rato jugó, volvió a la mesa o la partida se cerró, no manda nada. Un aviso que
 * falla no se reintenta: uno de "te toca" que llega tarde es peor que ninguno.
 */
class AvisarAlJugador implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param  array{titulo: string, cuerpo: string, etiqueta: string, url: string}  $aviso
     * @param  int|null  $evento  El último evento de la partida cuando se encoló, o null si el aviso no depende de eso.
     */
    public function __construct(
        public readonly int $jugadorId,
        public readonly array $aviso,
        public readonly int $partidaId,
        public readonly ?int $evento = null,
    ) {}

    public function handle(Avisos $avisos): void
    {
        $avisos->entregar($this->jugadorId, $this->aviso, $this->partidaId, $this->evento);
    }
}
