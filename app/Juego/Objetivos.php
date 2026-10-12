<?php

namespace App\Juego;

use App\Models\EventoDePartida;
use App\Motor\Partida as Motor;

/**
 * El objetivo de un desafío, y cómo se sabe si se cumplió.
 *
 * Un objetivo es una lista de condiciones escritas como datos, y se cumple cuando se cumplen todas.
 * Agregar un desafío no pide código: se combinan estas condiciones.
 *
 * - ganar_la_mano: la mano la gana el jugador, como sea (por las bazas, o porque el bot no quiso o se fue).
 * - ganar_la_partida: con esa mano el jugador llega a los puntos de la partida.
 * - ganar_el_envido: el envido se quiso y el mejor tanto fue el del jugador.
 * - sumar: el jugador suma al menos "puntos" en la mano. Con "por", solo cuentan esos conceptos (envido, truco, flor...).
 * - que_no_sume: el bot suma, como mucho, "mas_de" puntos en la mano.
 * - que_no_quiera: el bot contesta "no quiero" a un "canto" del jugador (truco, envido o contraflor).
 * - que_quiera: el bot contesta "quiero" a ese canto.
 *
 * Lo que pasó en la mano no se le pregunta a la pantalla: sale de volver a pasar los eventos de la
 * partida por el motor, igual que el historial y el ranking.
 */
final class Objetivos
{
    /** Las condiciones que existen, cada una con los datos que pide además de "que". */
    public const CONDICIONES = [
        'ganar_la_mano' => [],
        'ganar_la_partida' => [],
        'ganar_el_envido' => [],
        'sumar' => ['puntos'],
        'que_no_sume' => ['mas_de'],
        'que_no_quiera' => ['canto'],
        'que_quiera' => ['canto'],
    ];

    /** Los cantos que se contestan con "quiero" o "no quiero", como los nombra el motor al contestarlos. */
    private const CANTOS = ['truco', 'envido', 'contraflor'];

    /** Los eventos que mueven el motor. De los demás (una llegada, un abandono) no salen hechos nuevos. */
    private const QUE_JUEGAN = [EventoDePartida::REPARTO, EventoDePartida::ACCION, EventoDePartida::VENCIMIENTO];

    /**
     * Todo lo que pasó en la partida, en orden, y cómo quedó el motor al final.
     *
     * @param  iterable<int, array{0: EventoDePartida, 1: Motor}>  $pasoAPaso  Lo que da Mesa::pasoAPaso().
     * @return array{0: list<array<string, mixed>>, 1: Motor|null}
     */
    public static function loQuePaso(iterable $pasoAPaso): array
    {
        $hechos = [];
        $final = null;

        foreach ($pasoAPaso as [$evento, $motor]) {
            if (in_array($evento->tipo, self::QUE_JUEGAN, true)) {
                $hechos = [...$hechos, ...$motor->hechos()];
            }

            $final = $motor;
        }

        return [$hechos, $final];
    }

    /**
     * Si con lo que pasó en la mano se cumplió el objetivo. Una mano que no llegó a cerrarse no cumple nada.
     *
     * @param  list<array<string, mixed>>  $objetivo
     * @param  list<array<string, mixed>>  $hechos  Los hechos de la mano, en orden.
     */
    public static function cumple(array $objetivo, array $hechos, ?Motor $final): bool
    {
        $cierre = $final?->cierre();

        if ($cierre === null || $objetivo === []) {
            return false;
        }

        foreach ($objetivo as $condicion) {
            if (! self::seCumple($condicion, $hechos, $final, $cierre)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Si un objetivo está bien escrito: condiciones que existen, con los datos que piden.
     *
     * @param  list<array<string, mixed>>  $objetivo
     */
    public static function estaBienEscrito(array $objetivo): bool
    {
        foreach ($objetivo as $condicion) {
            $pide = self::CONDICIONES[$condicion['que'] ?? ''] ?? null;

            if ($pide === null || array_diff($pide, array_keys($condicion)) !== []) {
                return false;
            }

            if (isset($condicion['canto']) && ! in_array($condicion['canto'], self::CANTOS, true)) {
                return false;
            }
        }

        return $objetivo !== [];
    }

    /**
     * @param  array<string, mixed>  $condicion
     * @param  list<array<string, mixed>>  $hechos
     * @param  array<string, mixed>  $cierre
     */
    private static function seCumple(array $condicion, array $hechos, Motor $final, array $cierre): bool
    {
        return match ($condicion['que']) {
            'ganar_la_mano' => $cierre['ganador'] === Mesa::JUGADOR,
            'ganar_la_partida' => $final->ganador() === Mesa::JUGADOR,
            'ganar_el_envido' => self::hay($hechos, ['tipo' => 'tantos', 'canto' => 'envido', 'ganador' => Mesa::JUGADOR]),
            'sumar' => self::suma($cierre, Mesa::JUGADOR, $condicion['por'] ?? null) >= $condicion['puntos'],
            'que_no_sume' => self::suma($cierre, Mesa::BOT) <= $condicion['mas_de'],
            'que_no_quiera' => self::hay($hechos, ['tipo' => 'respuesta', 'asiento' => Mesa::BOT, 'canto' => $condicion['canto'], 'quiere' => false]),
            'que_quiera' => self::hay($hechos, ['tipo' => 'respuesta', 'asiento' => Mesa::BOT, 'canto' => $condicion['canto'], 'quiere' => true]),
        };
    }

    /**
     * Si entre los hechos hay uno con esos datos.
     *
     * @param  list<array<string, mixed>>  $hechos
     * @param  array<string, mixed>  $datos
     */
    private static function hay(array $hechos, array $datos): bool
    {
        foreach ($hechos as $hecho) {
            foreach ($datos as $dato => $valor) {
                if (! array_key_exists($dato, $hecho) || $hecho[$dato] !== $valor) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Lo que sumó un equipo en la mano. Con $conceptos, solo lo anotado por esos.
     *
     * @param  array<string, mixed>  $cierre
     * @param  list<string>|null  $conceptos
     */
    private static function suma(array $cierre, int $equipo, ?array $conceptos = null): int
    {
        $puntos = 0;

        foreach ($cierre['anotado'] as $anotado) {
            if ($anotado['equipo'] === $equipo && ($conceptos === null || in_array($anotado['concepto'], $conceptos, true))) {
                $puntos += $anotado['puntos'];
            }
        }

        return $puntos;
    }
}
