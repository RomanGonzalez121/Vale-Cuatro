<?php

namespace Tests\Unit;

use App\Juego\Bot;
use App\Juego\Nivel;
use App\Juego\RecibeSenas;
use App\Juego\Recuerda;
use App\Juego\Senas;
use App\Motor\Accion;
use App\Motor\Azar;
use App\Motor\Carta;
use App\Motor\Fase;
use App\Motor\Mazo;
use App\Motor\Partida;

/**
 * Sienta a cuatro bots a jugar partidas enteras de truco de a cuatro sobre el motor, sin base de datos.
 * Los asientos 0 y 2 son una pareja y los asientos 1 y 3, la otra. Con la misma semilla la partida sale
 * siempre igual, así que un fallo se puede repetir.
 */
trait EnfrentandoParejas
{
    /**
     * Juega una partida y devuelve el equipo que ganó. Cada bot ve solo la vista de su asiento y las
     * señas de su compañero, igual que en la mesa, y lo que decide tiene que ser una acción válida.
     *
     * @param  array{0: Bot, 1: Bot, 2: Bot, 3: Bot}  $bots  El bot de cada asiento.
     */
    private function partidaDeACuatro(array $bots, int $semilla): int
    {
        $azar = Azar::deSemilla($semilla);
        $partida = Partida::nueva(4, $azar->entero(0, 3));
        $pasos = 0;
        $cerradas = [[], [], [], []];
        $senas = [[], [], [], []];

        while ($partida->fase() !== Fase::Terminada) {
            $this->assertLessThan(8000, ++$pasos, "Semilla {$semilla}: la partida no termina.");

            if ($partida->fase() === Fase::PorRepartir) {
                if ($partida->cierre() !== null) {
                    foreach ([0, 1, 2, 3] as $asiento) {
                        $cerradas[$asiento][] = $partida->vistaPara($asiento);
                    }
                }

                $partida = $partida->repartir(Mazo::mezcladoCon($azar));

                // Las señas de la mano: cada uno le dice a su compañero lo que tiene.
                foreach ($partida->aArray()['cartas'] as $asiento => $cartas) {
                    $senas[($asiento + 2) % 4] = Senas::de(array_map(Carta::de(...), $cartas));
                }

                continue;
            }

            // Un canto al equipo lo puede contestar cualquiera de los dos: acá contesta el primero.
            $asiento = collect([0, 1, 2, 3])->first(fn (int $uno) => $partida->accionesPara($uno) !== []);

            if ($bots[$asiento] instanceof Recuerda) {
                $bots[$asiento]->recordar($cerradas[$asiento]);
            }

            if ($bots[$asiento] instanceof RecibeSenas) {
                $bots[$asiento]->recibirSenas($senas[$asiento]);
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
     * Cuántas partidas de la tanda gana la pareja del primer nivel contra la pareja del segundo.
     * Se van turnando los asientos, y cada bot tiene su propio azar, que sale de la semilla.
     */
    private function ganadasPorLaPareja(Nivel $una, Nivel $otra, int $partidas, int $desdeLaSemilla = 1): int
    {
        $ganadas = 0;

        for ($numero = 0; $numero < $partidas; $numero++) {
            $semilla = $desdeLaSemilla + $numero;
            $equipo = $numero % 2;
            $bots = [];

            foreach ([0, 1, 2, 3] as $asiento) {
                $bots[$asiento] = ($asiento % 2 === $equipo ? $una : $otra)->botDePareja(Azar::deSemilla($semilla * 11 + $asiento));
            }

            $ganadas += (int) ($this->partidaDeACuatro($bots, $semilla) === $equipo);
        }

        return $ganadas;
    }
}
