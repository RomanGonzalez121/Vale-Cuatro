<?php

namespace Tests\Unit;

use App\Juego\Bot;
use App\Juego\Nivel;
use App\Juego\Recuerda;
use App\Motor\Accion;
use App\Motor\Azar;
use App\Motor\Fase;
use App\Motor\Mazo;
use App\Motor\Partida;

/**
 * Sienta a tres bots a jugar gallitos enteros sobre el motor, sin base de datos. Con la misma semilla
 * la partida sale siempre igual, así que un fallo se puede repetir.
 */
trait EnfrentandoGallitos
{
    /**
     * Juega un gallito y devuelve el asiento que ganó. Cada bot ve solo la vista de su asiento, igual
     * que en la mesa, y lo que decide tiene que ser una acción válida.
     *
     * @param  array{0: Bot, 1: Bot, 2: Bot}  $bots  El bot de cada asiento.
     */
    private function partidaDeGallito(array $bots, int $semilla): int
    {
        $azar = Azar::deSemilla($semilla);
        $partida = Partida::nueva(3, $azar->entero(0, 2));
        $pasos = 0;
        $cerradas = [[], [], []];

        while ($partida->fase() !== Fase::Terminada) {
            $this->assertLessThan(8000, ++$pasos, "Semilla {$semilla}: el gallito no termina.");

            if ($partida->fase() === Fase::PorRepartir) {
                if ($partida->cierre() !== null) {
                    foreach ([0, 1, 2] as $asiento) {
                        $cerradas[$asiento][] = $partida->vistaPara($asiento);
                    }
                }

                $partida = $partida->repartir(Mazo::mezcladoCon($azar));

                continue;
            }

            // Un canto a la pareja lo puede contestar cualquiera de los dos: acá contesta el primero.
            $asiento = collect([0, 1, 2])->first(fn (int $uno) => $partida->accionesPara($uno) !== []);

            if ($bots[$asiento] instanceof Recuerda) {
                $bots[$asiento]->recordar($cerradas[$asiento]);
            }

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
     * Cuántos gallitos gana un bot del primer nivel contra dos del segundo. El asiento del primero va
     * rotando, y cada bot tiene su propio azar, que sale de la semilla. Por azar ganaría uno de cada tres.
     */
    private function ganadosPorUno(Nivel $uno, Nivel $otros, int $partidas, int $desdeLaSemilla = 1): int
    {
        $ganados = 0;

        for ($numero = 0; $numero < $partidas; $numero++) {
            $semilla = $desdeLaSemilla + $numero;
            $suyo = $numero % 3;
            $bots = [];

            foreach ([0, 1, 2] as $asiento) {
                $bots[$asiento] = ($asiento === $suyo ? $uno : $otros)->botDePareja(Azar::deSemilla($semilla * 11 + $asiento));
            }

            $ganados += (int) ($this->partidaDeGallito($bots, $semilla) === $suyo);
        }

        return $ganados;
    }
}
