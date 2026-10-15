<?php

namespace App\Console\Commands;

use App\Juego\Camino;
use App\Juego\Mesa;
use App\Juego\Titulos;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Rehace desde las partidas cerradas los fósforos de cada jugador, de donde sale su nivel. Sirve para las
 * partidas que se jugaron antes de que existieran los niveles, y cada vez que cambie qué anota cada una.
 * También rehace las cuentas de los títulos (envidos, trucos y flores), que salen de las mismas partidas.
 */
#[Signature('fosforos:recalcular')]
#[Description('Vuelve a contar los fósforos (y con ellos el nivel) y las cuentas de los títulos de cada jugador desde las partidas cerradas')]
class RecalcularFosforos extends Command
{
    public function handle(Camino $camino, Titulos $titulos, Mesa $mesa): int
    {
        $anotaron = $camino->recalcular($mesa);
        $titulos->recalcular($mesa);

        $this->info("Partidas que anotaron fósforos: {$anotaron}.");

        return self::SUCCESS;
    }
}
