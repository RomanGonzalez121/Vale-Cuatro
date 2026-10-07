<?php

namespace App\Juego;

/**
 * Un bot que lleva la cuenta de cómo viene la partida.
 *
 * El turno del bot sale de un job que reconstruye la partida cada vez, así que
 * entre una jugada y otra no le queda nada en la memoria. Antes de decidir se
 * le pasa lo que su asiento vio al cerrarse cada mano anterior: las mismas
 * vistas que recibiría un jugador, sin las cartas que el rival no mostró.
 */
interface Recuerda
{
    /**
     * @param  list<array<string, mixed>>  $manos  La vista de su asiento al cierre de cada mano anterior, en orden.
     */
    public function recordar(array $manos): void;
}
