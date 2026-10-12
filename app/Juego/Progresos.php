<?php

namespace App\Juego;

use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Progreso;

/**
 * Lo que cada jugador lleva pasado de los desafíos y de la escalera del club.
 *
 * Se anota en un solo lugar: cuando se cierra una partida que es de uno de los dos (Mesa::cerrar).
 * Ninguna pantalla ni pedido marca nada como pasado: lo dice el resultado de la partida, y ese lo
 * decidió el servidor con sus eventos.
 *
 * Vale igual para quien juega sin cuenta: es la misma fila de jugador, y la conserva si se registra.
 */
final class Progresos
{
    /**
     * Las claves de lo que ese jugador ya pasó: "desafio:<clave>" y "escalon:<número>".
     *
     * @return list<string>
     */
    public function superados(?Jugador $jugador): array
    {
        if ($jugador === null) {
            return [];
        }

        return Progreso::query()->where('jugador_id', $jugador->getKey())->whereNotNull('superado_en')->pluck('clave')->all();
    }

    /**
     * El escalón en el que está: el primero que todavía no pasó. Con la escalera completa, uno más que el último.
     */
    public function escalonActual(?Jugador $jugador): int
    {
        $superados = $this->superados($jugador);

        foreach (Escalera::escalones() as $escalon) {
            if (! in_array(self::claveDeEscalon($escalon['numero']), $superados, true)) {
                return $escalon['numero'];
            }
        }

        return Escalera::cuantos() + 1;
    }

    /**
     * Si ese escalón se puede jugar: existe y ya se pasaron todos los anteriores. Uno ya pasado se
     * puede volver a jugar.
     */
    public function escalonAbierto(?Jugador $jugador, int $numero): bool
    {
        return Escalera::escalon($numero) !== null && $numero <= $this->escalonActual($jugador);
    }

    /**
     * Anota cómo terminó una partida de un desafío o de la escalera: un intento más y, si se pasó,
     * desde cuándo. De cualquier otra partida no anota nada.
     */
    public function anotar(Partida $partida): void
    {
        $clave = self::claveDe($partida);

        if ($clave === null) {
            return;
        }

        $progreso = Progreso::query()->firstOrCreate(['jugador_id' => $partida->jugador_id, 'clave' => $clave]);
        $progreso->intentos++;

        // Pasarlo es terminarlo habiéndolo ganado: una partida abandonada cuenta como un intento y nada más.
        if ($partida->estado === Partida::TERMINADA && $partida->ganador === Mesa::JUGADOR) {
            $progreso->superado_en ??= now();
        }

        $progreso->save();
    }

    /**
     * La clave con la que se anota una partida, o null si no es de un desafío ni de la escalera.
     * Una mano de la escalera cuenta para su escalón, no como desafío suelto.
     */
    public static function claveDe(Partida $partida): ?string
    {
        return match (true) {
            $partida->escalon !== null => self::claveDeEscalon($partida->escalon),
            $partida->desafio !== null => self::claveDeDesafio($partida->desafio),
            default => null,
        };
    }

    public static function claveDeDesafio(string $clave): string
    {
        return "desafio:{$clave}";
    }

    public static function claveDeEscalon(int $numero): string
    {
        return "escalon:{$numero}";
    }
}
