<?php

namespace App\Console\Commands;

use App\Juego\Camino;
use App\Juego\Mesa;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Rehace desde las partidas cerradas los fósforos de cada jugador, de donde sale su nivel. Sirve para las
 * partidas que se jugaron antes de que existieran los niveles, y cada vez que cambie qué anota cada una.
 */
#[Signature('fosforos:recalcular')]
#[Description('Vuelve a contar los fósforos (y con ellos el nivel) de cada jugador desde las partidas cerradas')]
class RecalcularFosforos extends Command
{
    public function handle(Camino $camino, Mesa $mesa): int
    {
        $anotaron = $camino->recalcular($mesa);

        $this->info("Partidas que anotaron fósforos: {$anotaron}.");

        return self::SUCCESS;
    }
}
