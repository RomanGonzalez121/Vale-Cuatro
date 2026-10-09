<?php

namespace Database\Seeders;

use App\Juego\JugadoresDeEjemplo;
use App\Juego\Mesa;
use Illuminate\Database\Seeder;

/**
 * Los jugadores de ejemplo del ranking, con sus partidas simuladas.
 *
 * A diferencia de las cuentas de prueba, estos sí van en producción: son parte del sitio, y el sitio
 * dice que son simulados. Es lo mismo que hace `php artisan ranking:ejemplo`, y tampoco repite lo hecho.
 */
class JugadoresDeEjemploSeeder extends Seeder
{
    public function run(JugadoresDeEjemplo $ejemplo, Mesa $mesa): void
    {
        $jugadas = $ejemplo->sembrar($mesa);

        $this->command?->info("Partidas simuladas entre los jugadores de ejemplo: {$jugadas}.");
    }
}
