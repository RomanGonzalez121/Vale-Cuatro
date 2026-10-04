<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Los jugadores de ejemplo del ranking, marcados como bots, llegan con M8.
     */
    public function run(): void
    {
        $this->call(CuentasDePruebaSeeder::class);
    }
}
