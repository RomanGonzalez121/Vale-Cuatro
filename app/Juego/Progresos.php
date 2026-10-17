<?php

namespace App\Juego;

use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Progreso;

/**
 * Lo que cada jugador lleva pasado de los desafíos, de la escalera del club y de la gira de los clubes.
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
     * Las claves de lo que ese jugador ya pasó: "desafio:<clave>", "escalon:<número>" y "gira:<barrio>:<nivel>".
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
     * Anota cómo terminó una partida de un desafío, de la escalera o de la gira: un intento más y, si se pasó,
     * desde cuándo. De la escalera y la gira, también los fósforos, si este intento dio más que los anteriores.
     * De cualquier otra partida no anota nada.
     */
    public function anotar(Partida $partida, ?Mesa $mesa = null): void
    {
        $clave = self::claveDe($partida);

        if ($clave === null) {
            return;
        }

        $progreso = Progreso::query()->firstOrCreate(['jugador_id' => $partida->jugador_id, 'clave' => $clave]);
        $progreso->intentos++;

        // Pasarlo es terminarlo habiéndolo ganado: una partida abandonada cuenta como un intento y nada más.
        $paso = $partida->estado === Partida::TERMINADA && $partida->ganador === Mesa::JUGADOR;

        if ($paso) {
            $progreso->superado_en ??= now();
        }

        if (Gira::deLaPartida($partida) !== null) {
            $fosforos = $mesa === null ? (int) $paso : self::fosforosDe($partida, $mesa);
            $progreso->fosforos = max($progreso->fosforos, $fosforos);
        }

        $progreso->save();
    }

    /**
     * Cuántos fósforos dio una partida de la gira (o de la escalera, que es Boedo), contados desde sus eventos.
     */
    public static function fosforosDe(Partida $partida, Mesa $mesa): int
    {
        [$barrio, $numero] = Gira::deLaPartida($partida) ?? [null, null];

        if ($barrio === null) {
            return 0;
        }

        $hechos = [];
        $anterior = null;
        $final = null;
        $inicio = [0, 0];

        foreach ($mesa->pasoAPaso($partida) as [$evento, $motor]) {
            // El tanteo de arranque (un nivel que empieza abajo, o una mano armada) queda con el primer reparto.
            if ($anterior === null && $evento->tipo === EventoDePartida::REPARTO && isset($evento->datos['tanteo'])) {
                $inicio = $evento->datos['tanteo'];
            }

            // Si el motor es el mismo de antes, el evento no pasó por el motor y sus hechos ya se contaron.
            if ($motor === $anterior) {
                continue;
            }

            $anterior = $motor;
            $final = $motor->tanteo();
            $hechos = [...$hechos, ...$motor->hechos()];
        }

        $paso = $partida->estado === Partida::TERMINADA && $partida->ganador === Mesa::JUGADOR;

        return FosforosDeGira::cuantos(Gira::nivel($barrio, $numero), $paso, $hechos, $inicio, $final ?? $inicio);
    }

    /**
     * La clave con la que se anota una partida, o null si no es de un desafío ni de la escalera.
     * Una mano de la escalera cuenta para su escalón, no como desafío suelto.
     */
    public static function claveDe(Partida $partida): ?string
    {
        return match (true) {
            $partida->escalon !== null => self::claveDeEscalon($partida->escalon),
            $partida->gira !== null => "gira:{$partida->gira}",
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
