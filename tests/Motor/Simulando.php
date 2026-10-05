<?php

namespace Tests\Motor;

use App\Motor\Azar;
use App\Motor\Fase;
use App\Motor\Mazo;
use App\Motor\Partida;

/**
 * Juega partidas enteras eligiendo al azar entre las acciones que el motor declara válidas.
 * Con la misma semilla la partida sale siempre igual, así que un fallo se puede repetir.
 */
trait Simulando
{
    /**
     * @param  callable(Partida, Partida): void|null  $enCadaPaso  Recibe la partida después de cada paso y la anterior.
     */
    private function simular(int $semilla, int $asientos = 2, int $puntos = 30, ?callable $enCadaPaso = null): Partida
    {
        $azar = Azar::deSemilla($semilla);
        $partida = Partida::nueva($asientos, $azar->entero(0, $asientos - 1), $puntos);
        $pasos = 0;

        while ($partida->fase() !== Fase::Terminada) {
            $anterior = $partida;

            $partida = $partida->fase() === Fase::PorRepartir
                ? $partida->repartir(Mazo::mezcladoCon($azar))
                : $this->pasoAlAzar($partida, $azar, "Semilla {$semilla}");

            if ($enCadaPaso !== null) {
                $enCadaPaso($partida, $anterior);
            }

            $this->assertLessThan(20000, ++$pasos, "Semilla {$semilla}: la partida no termina.");
        }

        return $partida;
    }

    /**
     * Elige al azar uno de los asientos que pueden actuar y una de sus acciones válidas.
     */
    private function pasoAlAzar(Partida $partida, Azar $azar, string $rotulo): Partida
    {
        $quienes = array_values(array_filter(
            range(0, $partida->mesa()->asientos - 1),
            fn (int $asiento) => $partida->accionesPara($asiento) !== [],
        ));

        $this->assertNotEmpty($quienes, "{$rotulo}: la mano está en juego y nadie puede hacer nada.");

        $asiento = $quienes[$azar->entero(0, count($quienes) - 1)];
        $acciones = $partida->accionesPara($asiento);

        return $partida->aplicar($asiento, $acciones[$azar->entero(0, count($acciones) - 1)]);
    }
}
