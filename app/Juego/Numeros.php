<?php

namespace App\Juego;

use App\Models\Jugador;
use App\Models\Resultado;
use App\Models\Torneo;

/**
 * Los números de un jugador para su perfil, como la planilla del club: partidas, ganadas, rachas,
 * envidos, torneos, escalera y desafíos.
 *
 * Partidas, ganadas, rachas y envidos salen de lo que anotó el ranking (las mismas partidas que cuentan
 * ahí), así un número del perfil nunca contradice a la tabla. Torneos, escalera y desafíos salen de lo
 * suyo. Todo se lee de lo ya guardado: abrir el perfil no vuelve a jugar ninguna partida.
 */
final class Numeros
{
    /**
     * @return array{partidas: int, ganadas: int, porcentaje: int|null, racha: int, mejorRacha: int, envidos: int|null, torneos: int, escalones: int, escalera: int, desafios: int, desafiosEnTotal: int}
     */
    public function de(Jugador $jugador): array
    {
        $resultados = Resultado::query()
            ->where('jugador_id', $jugador->getKey())
            ->orderBy('terminada_en')
            ->orderBy('id')
            ->get(['gano', 'envidos_jugados', 'envidos_ganados']);

        $ganadas = $resultados->where('gano', true)->count();
        [$racha, $mejor] = $this->rachas($resultados->pluck('gano')->all());
        $jugados = (int) $resultados->sum('envidos_jugados');
        $superados = (new Progresos)->superados($jugador);

        return [
            'partidas' => $resultados->count(),
            'ganadas' => $ganadas,
            'porcentaje' => $resultados->isEmpty() ? null : (int) round(100 * $ganadas / $resultados->count()),
            'racha' => $racha,
            'mejorRacha' => $mejor,
            'envidos' => $jugados === 0 ? null : (int) round(100 * $resultados->sum('envidos_ganados') / $jugados),
            'torneos' => $this->torneosGanados($jugador),
            'escalones' => count(array_filter($superados, fn (string $clave) => str_starts_with($clave, 'escalon:'))),
            'escalera' => Escalera::cuantos(),
            'desafios' => count(array_filter($superados, fn (string $clave) => str_starts_with($clave, 'desafio:'))),
            'desafiosEnTotal' => count(Desafios::sueltos()),
        ];
    }

    /**
     * La racha de ahora (las ganadas después de la última perdida) y la más larga que tuvo.
     *
     * @param  list<bool>  $ganadas  En el orden en que se cerraron.
     * @return array{0: int, 1: int}
     */
    private function rachas(array $ganadas): array
    {
        $ahora = 0;
        $mejor = 0;

        foreach ($ganadas as $gano) {
            $ahora = $gano ? $ahora + 1 : 0;
            $mejor = max($mejor, $ahora);
        }

        return [$ahora, $mejor];
    }

    private function torneosGanados(Jugador $jugador): int
    {
        return Torneo::query()
            ->where('jugador_id', $jugador->getKey())
            ->whereNotNull('campeon')
            ->get()
            ->filter(fn (Torneo $torneo) => $torneo->campeon === $torneo->lugarDeLaPersona())
            ->count();
    }
}
