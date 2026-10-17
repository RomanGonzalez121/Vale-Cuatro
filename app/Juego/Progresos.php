<?php

namespace App\Juego;

use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Progreso;
use App\Models\Serie;
use App\Models\Torneo;
use Closure;

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

        // Pasarlo es terminarlo habiéndolo ganado: una partida abandonada cuenta como un intento y nada más.
        $paso = $partida->estado === Partida::TERMINADA && $partida->ganador === Mesa::JUGADOR;
        $extra = [];

        // Un nivel al mejor de tres se anota una sola vez, cuando la serie termina: lo pasa quien la gana.
        if ($partida->serie_id !== null && $partida->gira !== null) {
            $serie = Serie::query()->find($partida->serie_id);

            if ($serie === null || ! $serie->cerrada()) {
                return;
            }

            $paso = $serie->ganador === Mesa::JUGADOR;
            $extra['perdidas_en_la_serie'] = $serie->marcador()[Mesa::BOT];
        }

        $esDeLaGira = Gira::deLaPartida($partida) !== null;
        $this->anotarIntento($partida->jugador_id, $clave, $paso, $esDeLaGira, fn () => $mesa === null ? (int) $paso : self::fosforosDe($partida, $mesa, $paso, $extra));
    }

    /**
     * Anota cómo terminó la copa de un barrio de la gira: un torneo de cuatro que se pasa saliendo campeón. Los
     * fósforos salen de la final, que es la última partida que jugó la persona. Lo llama Torneos cuando hay campeón.
     */
    public function anotarCopa(Torneo $torneo, Mesa $mesa): void
    {
        if ($torneo->gira === null) {
            return;
        }

        [$barrio, $numero] = explode(':', $torneo->gira);
        $paso = $torneo->campeon === $torneo->lugarDeLaPersona();
        $final = $torneo->cruces()->where('ronda', $torneo->rondas())->first()?->partida;

        $this->anotarIntento($torneo->jugador_id, "gira:{$torneo->gira}", $paso, true, function () use ($final, $mesa, $paso, $barrio, $numero) {
            if (! $paso || $final === null) {
                return (int) $paso;
            }

            [$hechos, $inicio, $tanteo] = self::recorrer($final, $mesa);

            return FosforosDeGira::cuantos(Gira::nivel($barrio, (int) $numero), $paso, $hechos, $inicio, $tanteo, 0, [
                'final' => $tanteo,
                'envidos_perdidos_en_la_final' => FosforosDeGira::contar($hechos)['envidos_perdidos'],
            ]);
        });
    }

    /**
     * Un intento más de algo y, si se pasó, desde cuándo. De la gira, también los fósforos: queda el mejor intento.
     *
     * @param  Closure(): int  $fosforos
     */
    private function anotarIntento(int $jugadorId, string $clave, bool $paso, bool $conFosforos, Closure $fosforos): void
    {
        $progreso = Progreso::query()->firstOrCreate(['jugador_id' => $jugadorId, 'clave' => $clave]);
        $progreso->intentos++;

        if ($paso) {
            $progreso->superado_en ??= now();
        }

        if ($conFosforos) {
            $progreso->fosforos = max($progreso->fosforos, $fosforos());
        }

        $progreso->save();
    }

    /**
     * Cuántos fósforos dio una partida de la gira (o de la escalera, que es Boedo), contados desde sus eventos.
     */
    public static function fosforosDe(Partida $partida, Mesa $mesa, ?bool $paso = null, array $extra = []): int
    {
        [$barrio, $numero] = Gira::deLaPartida($partida) ?? [null, null];

        if ($barrio === null) {
            return 0;
        }

        [$hechos, $inicio, $final] = self::recorrer($partida, $mesa);
        $paso ??= $partida->estado === Partida::TERMINADA && $partida->ganador === Mesa::JUGADOR;

        return FosforosDeGira::cuantos(Gira::nivel($barrio, $numero), $paso, $hechos, $inicio, $final, 0, $extra);
    }

    /**
     * Todos los hechos de una partida en orden, con el tanteo con que arrancó y con el que terminó.
     *
     * @return array{0: list<array<string, mixed>>, 1: array{0: int, 1: int}, 2: array{0: int, 1: int}}
     */
    private static function recorrer(Partida $partida, Mesa $mesa): array
    {
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

        return [$hechos, $inicio, $final ?? $inicio];
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
