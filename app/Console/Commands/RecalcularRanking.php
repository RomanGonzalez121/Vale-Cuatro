<?php

namespace App\Console\Commands;

use App\Juego\Estadisticas;
use App\Juego\Mesa;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Rehace desde los eventos lo que cada partida le cuenta al ranking. Sirve para las partidas que se
 * jugaron antes de que existiera el ranking, y cada vez que cambie qué se cuenta.
 */
#[Signature('ranking:recalcular')]
#[Description('Vuelve a calcular el ranking desde los eventos de todas las partidas cerradas')]
class RecalcularRanking extends Command
{
    public function handle(Estadisticas $estadisticas, Mesa $mesa): int
    {
        $contadas = $estadisticas->recalcular($mesa);

        $this->info("Partidas que cuentan para el ranking: {$contadas}.");

        return self::SUCCESS;
    }
}
