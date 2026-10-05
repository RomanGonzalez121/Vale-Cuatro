<?php

namespace Tests\Unit;

use App\Juego\Bot;
use App\Juego\Nivel;
use App\Motor\Accion;
use App\Motor\Azar;
use App\Motor\Fase;
use App\Motor\Mazo;
use App\Motor\Partida;

/**
 * Sienta a dos bots a jugar partidas enteras sobre el motor, sin base de datos.
 * Con la misma semilla la partida sale siempre igual, así que un fallo se puede repetir.
 */
trait Enfrentando
{
    /**
     * Juega una partida y devuelve el asiento que ganó. Cada bot ve solo la vista de su asiento,
     * y lo que decide tiene que estar entre las acciones que el motor le declara válidas.
     *
     * @param  array{0: Bot, 1: Bot}  $bots  El bot de cada asiento.
     */
    private function partidaEntre(array $bots, int $semilla): int
    {
        $azar = Azar::deSemilla($semilla);
        $partida = Partida::nueva(2, $azar->entero(0, 1));
        $pasos = 0;

        while ($partida->fase() !== Fase::Terminada) {
            $this->assertLessThan(5000, ++$pasos, "Semilla {$semilla}: la partida no termina.");

            if ($partida->fase() === Fase::PorRepartir) {
                $partida = $partida->repartir(Mazo::mezcladoCon($azar));

                continue;
            }

            $asiento = $partida->accionesPara(0) !== [] ? 0 : 1;
            $accion = $bots[$asiento]->decidir($partida->vistaPara($asiento));

            $this->assertContains(
                $accion->aArray(),
                array_map(fn (Accion $valida) => $valida->aArray(), $partida->accionesPara($asiento)),
                "Semilla {$semilla}: el bot del asiento {$asiento} quiso hacer algo que no vale: ".json_encode($accion->aArray()),
            );

            $partida = $partida->aplicar($asiento, $accion);
        }

        return $partida->ganador();
    }

    /**
     * Cuántas partidas de la tanda gana el primer nivel. Se van turnando el asiento, y cada
     * bot tiene su propio azar, que sale de la semilla de la partida.
     */
    private function ganadasPor(Nivel $uno, Nivel $otro, int $partidas, int $desdeLaSemilla = 1): int
    {
        $ganadas = 0;

        for ($numero = 0; $numero < $partidas; $numero++) {
            $semilla = $desdeLaSemilla + $numero;
            $asiento = $numero % 2;

            $bots = [];
            $bots[$asiento] = $uno->bot(Azar::deSemilla($semilla * 7 + 1));
            $bots[1 - $asiento] = $otro->bot(Azar::deSemilla($semilla * 7 + 2));

            $ganadas += (int) ($this->partidaEntre($bots, $semilla) === $asiento);
        }

        return $ganadas;
    }
}
