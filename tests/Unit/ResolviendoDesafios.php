<?php

namespace Tests\Unit;

use App\Juego\Desafios;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Juego\Objetivos;
use App\Juego\Recuerda;
use App\Motor\Accion;
use App\Motor\Fase;
use App\Motor\Partida as Motor;

/**
 * Juega un desafío de todas las maneras posibles. En un desafío el bot contesta siempre lo mismo a las
 * mismas jugadas, así que se puede recorrer entero: cada decisión de la persona abre una rama.
 *
 * Sirve para comprobar que un desafío tiene solución y que no sale solo, y para sacar de ahí una
 * manera de jugarlo que cumple el objetivo y otra que no.
 */
trait ResolviendoDesafios
{
    /**
     * Cuántas maneras de jugarlo hay, cuántas cumplen el objetivo, y un ejemplo de cada clase: las
     * jugadas de la persona, en orden (las del bot salen solas).
     *
     * @param  array<string, mixed>  $ficha
     * @return array{lineas: int, cumplen: int, cumple: list<Accion>|null, falla: list<Accion>|null}
     */
    private function resolver(array $ficha): array
    {
        $motor = Desafios::motor($ficha);
        $hechos = $motor->hechos();

        foreach (Desafios::jugadas($ficha) as [$asiento, $accion]) {
            $motor = $motor->aplicar($asiento, $accion);
            $hechos = [...$hechos, ...$motor->hechos()];
        }

        // El reparto es el evento 1 y cada jugada ya hecha, uno más: así numera la mesa lo que guarda.
        return $this->explorar($ficha, $motor, 1 + count($ficha['jugadas'] ?? []), $hechos, []);
    }

    /**
     * @param  array<string, mixed>  $ficha
     * @param  list<array<string, mixed>>  $hechos
     * @param  list<Accion>  $camino
     * @return array{lineas: int, cumplen: int, cumple: list<Accion>|null, falla: list<Accion>|null}
     */
    private function explorar(array $ficha, Motor $motor, int $evento, array $hechos, array $camino): array
    {
        // El bot juega mientras le toque a él, con el mismo azar que le da la mesa a esa jugada.
        while ($motor->fase() === Fase::Jugando && $motor->accionesPara(Mesa::JUGADOR) === [] && $motor->accionesPara(Mesa::BOT) !== []) {
            $evento++;
            $bot = Nivel::from($ficha['nivel'])->bot(Desafios::azar($ficha['clave'], $evento));

            if ($bot instanceof Recuerda) {
                $bot->recordar([]);
            }

            $motor = $motor->aplicar(Mesa::BOT, $bot->decidir($motor->vistaPara(Mesa::BOT)));
            $hechos = [...$hechos, ...$motor->hechos()];
        }

        if ($motor->fase() !== Fase::Jugando) {
            $cumple = Objetivos::cumple($ficha['objetivo'], $hechos, $motor);

            return ['lineas' => 1, 'cumplen' => (int) $cumple, 'cumple' => $cumple ? $camino : null, 'falla' => $cumple ? null : $camino];
        }

        $total = ['lineas' => 0, 'cumplen' => 0, 'cumple' => null, 'falla' => null];

        foreach ($motor->accionesPara(Mesa::JUGADOR) as $accion) {
            $sigue = $motor->aplicar(Mesa::JUGADOR, $accion);
            $rama = $this->explorar($ficha, $sigue, $evento + 1, [...$hechos, ...$sigue->hechos()], [...$camino, $accion]);

            $total['lineas'] += $rama['lineas'];
            $total['cumplen'] += $rama['cumplen'];
            // De las que cumplen, la más corta: es la que mejor se lee cuando un test falla.
            $total['cumple'] = $rama['cumple'] !== null && ($total['cumple'] === null || count($rama['cumple']) < count($total['cumple'])) ? $rama['cumple'] : $total['cumple'];
            $total['falla'] ??= $rama['falla'];
        }

        return $total;
    }
}
