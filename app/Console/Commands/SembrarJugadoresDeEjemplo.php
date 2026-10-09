<?php

namespace App\Console\Commands;

use App\Juego\JugadoresDeEjemplo;
use App\Juego\Mesa;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Crea los jugadores de ejemplo del ranking y les hace jugar sus partidas simuladas.
 * Sin la opción no repite lo que ya está hecho: se puede correr en cada publicación.
 */
#[Signature('ranking:ejemplo {--de-nuevo : Borra a los jugadores de ejemplo y sus partidas, y los hace jugar otra vez}')]
#[Description('Crea los jugadores de ejemplo del ranking y simula sus partidas')]
class SembrarJugadoresDeEjemplo extends Command
{
    public function handle(JugadoresDeEjemplo $ejemplo, Mesa $mesa): int
    {
        if ($this->option('de-nuevo')) {
            $ejemplo->borrar();
        }

        $jugadas = $ejemplo->sembrar($mesa);

        $this->info($jugadas === 0
            ? 'Los jugadores de ejemplo ya tenían sus partidas: no se jugó ninguna.'
            : "Partidas simuladas entre los jugadores de ejemplo: {$jugadas}.");

        return self::SUCCESS;
    }
}
