<?php

namespace App\Avisos;

use Illuminate\Support\Facades\Cache;

/**
 * Si un jugador tiene la mesa (o la sala) a la vista en este momento. A quien la está mirando no se le
 * manda un aviso: ya lo está viendo.
 *
 * Lo dice la propia página: avisa cuando queda a la vista y lo repite cada tanto mientras siga así, y
 * avisa cuando se tapa o se cierra. Si se cerró sin llegar a avisar (se cortó la conexión, se apagó el
 * celular), la marca se vence sola.
 */
final class Presencia
{
    /** Cuánto dura la marca si nadie la renueva. La página la renueva antes de que se venza. */
    public const SEGUNDOS = 45;

    public function marcar(int $jugadorId, bool $aLaVista): void
    {
        if ($aLaVista) {
            Cache::put($this->clave($jugadorId), true, self::SEGUNDOS);
        } else {
            Cache::forget($this->clave($jugadorId));
        }
    }

    public function aLaVista(int $jugadorId): bool
    {
        return Cache::has($this->clave($jugadorId));
    }

    private function clave(int $jugadorId): string
    {
        return "avisos:a-la-vista:{$jugadorId}";
    }
}
