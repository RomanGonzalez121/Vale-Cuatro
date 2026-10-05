<?php

namespace Tests\Motor;

use App\Motor\Accion;
use App\Motor\AccionInvalida;
use App\Motor\Carta;
use App\Motor\Partida;
use App\Motor\TipoDeAccion;

/**
 * Atajos para escribir manos en los tests como se cuentan en la mesa:
 * "0 7-espada" es el asiento 0 jugando el 7 de espada y "1 truco" es el asiento 1 cantando truco.
 */
trait Jugando
{
    /**
     * @param  list<list<string>>  $manos  Las cartas de cada asiento, por identificador.
     * @param  array{0: int, 1: int}  $tanteo
     */
    private function armada(array $manos, int $mano = 0, array $tanteo = [0, 0], int $puntos = 30): Partida
    {
        return Partida::armada(array_map($this->cartas(...), $manos), $mano, $tanteo, $puntos);
    }

    private function jugar(Partida $partida, string ...$jugadas): Partida
    {
        foreach ($jugadas as $jugada) {
            [$asiento, $accion] = $this->leer($jugada);
            $partida = $partida->aplicar($asiento, $accion);
        }

        return $partida;
    }

    /**
     * El motivo con el que el motor rechaza una jugada. Falla si la acepta.
     */
    private function rechazo(Partida $partida, string $jugada): string
    {
        [$asiento, $accion] = $this->leer($jugada);

        try {
            $partida->aplicar($asiento, $accion);
        } catch (AccionInvalida $rechazo) {
            return $rechazo->getMessage();
        }

        $this->fail("El motor aceptó [{$jugada}] y tenía que rechazarla.");
    }

    /**
     * Lo que puede hacer un asiento, escrito corto: las cartas por su identificador y el resto por su nombre.
     *
     * @return list<string>
     */
    private function opciones(Partida $partida, int $asiento): array
    {
        return array_map(
            fn (Accion $accion) => $accion->carta?->id() ?? $accion->tipo->value,
            $partida->accionesPara($asiento),
        );
    }

    /**
     * @param  list<string>  $ids
     * @return list<Carta>
     */
    private function cartas(array $ids): array
    {
        return array_map(Carta::de(...), $ids);
    }

    /**
     * @return array{0: int, 1: Accion}
     */
    private function leer(string $jugada): array
    {
        [$asiento, $que] = explode(' ', $jugada, 2);

        return [
            (int) $asiento,
            str_contains($que, '-') ? Accion::jugar(Carta::de($que)) : Accion::de(TipoDeAccion::from($que)),
        ];
    }
}
