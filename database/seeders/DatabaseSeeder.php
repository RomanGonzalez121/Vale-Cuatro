<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Las cuentas para probar a mano (nunca en producción) y los jugadores de ejemplo del ranking,
     * marcados como bots, con las partidas simuladas de las que salen sus números.
     */
    public function run(): void
    {
        $this->call([CuentasDePruebaSeeder::class, JugadoresDeEjemploSeeder::class]);
    }
}
