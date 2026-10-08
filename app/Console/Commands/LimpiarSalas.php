<?php

namespace App\Console\Commands;

use App\Juego\Mesa;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Cierra las salas de partidas entre personas que esperaron rival demasiado tiempo.
 */
#[Signature('salas:limpiar')]
#[Description('Cierra las salas que esperaron rival más de '.Mesa::MINUTOS_DE_SALA.' minutos')]
class LimpiarSalas extends Command
{
    public function handle(Mesa $mesa): int
    {
        $cerradas = $mesa->cerrarSalasVencidas();

        $this->info("Salas cerradas: {$cerradas}.");

        return self::SUCCESS;
    }
}
