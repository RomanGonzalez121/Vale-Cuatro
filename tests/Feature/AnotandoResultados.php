<?php

namespace Tests\Feature;

use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Resultado;

/**
 * Para los tests de la tabla del ranking: deja anotada una serie de resultados sin jugar las partidas.
 * Que lo anotado coincide con lo que se jugó lo prueba EstadisticasTest; acá importa el orden de la tabla.
 */
trait AnotandoResultados
{
    /**
     * Anota una serie de partidas de un jugador, en orden: "GGP" son dos ganadas y después una perdida.
     * Los envidos van todos en la primera partida de la serie. Cada partida se cierra un segundo
     * después de la anterior: la racha se lee por esa fecha.
     */
    private function anotarle(Jugador $jugador, string $serie, int $envidosJugados = 0, int $envidosGanados = 0): void
    {
        static $segundos = 0;

        foreach (str_split($serie) as $numero => $letra) {
            $partida = Partida::create(['jugador_id' => $jugador->id, 'primer_mano' => 0, 'puntos' => 30]);

            Resultado::create([
                'partida_id' => $partida->id,
                'jugador_id' => $jugador->id,
                'gano' => $letra === 'G',
                'envidos_jugados' => $numero === 0 ? $envidosJugados : 0,
                'envidos_ganados' => $numero === 0 ? $envidosGanados : 0,
                'terminada_en' => now()->addSeconds(++$segundos),
            ]);
        }
    }
}
