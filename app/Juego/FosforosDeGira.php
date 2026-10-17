<?php

namespace App\Juego;

use InvalidArgumentException;

/**
 * Cuántos fósforos da un nivel de la gira: uno por pasarlo, otro por lo que pide su "segundo" y otro por lo
 * que pide su "tercero" (las condiciones están nombradas en fichas-de-gira.php).
 *
 * Se cuenta desde los hechos del motor, igual que todo lo demás: nadie le dice al servidor cuántos fósforos
 * ganó. Es una cuenta pura (recibe los hechos y el tanteo) para que se pueda probar sin jugar partidas.
 */
final class FosforosDeGira
{
    public const MAXIMO = 3;

    /**
     * Las condiciones que se pueden pedir, con cuántos datos llevan.
     */
    public const CONDICIONES = [
        'por' => 1, 'sin_mazo' => 0, 'envidos' => 1, 'trucos' => 1, 'retrucos' => 1, 'vale_cuatros' => 1, 'faltas' => 1,
        'sin_envido_perdido' => 0, 'sin_truco_perdido' => 0, 'envido_y_truco' => 0, 'sumar' => 1, 'que_no_sume' => 0,
        'sin_cantar' => 1, 'en_dos_bazas' => 0, 'gana_el_truco' => 0, 'contraflor_al_resto' => 0,
        'serie_sin_perder' => 0, 'final_por' => 1, 'final_sin_envido_perdido' => 0,
    ];

    /**
     * @param  array{segundo: array<int, mixed>, tercero: array<int, mixed>}  $nivel
     * @param  list<array<string, mixed>>  $hechos  Todos los hechos de la partida, en orden.
     * @param  array{0: int, 1: int}  $inicio  El tanteo con que arrancó (equipo de la persona primero).
     * @param  array{0: int, 1: int}  $final  El tanteo con que terminó.
     * @param  array<string, mixed>  $extra  Lo que no sale de una sola partida (la serie, la final del torneo).
     */
    public static function cuantos(array $nivel, bool $paso, array $hechos, array $inicio, array $final, int $asiento = 0, array $extra = []): int
    {
        if (! $paso) {
            return 0;
        }

        $cuenta = self::contar($hechos, $asiento);
        $cumple = fn (array $condicion) => self::cumple($condicion, $cuenta, $inicio, $final, $extra);

        return 1 + (int) $cumple($nivel['segundo']) + (int) $cumple($nivel['tercero']);
    }

    public static function estaBienEscrita(array $condicion): bool
    {
        $que = $condicion[0] ?? null;

        return is_string($que) && isset(self::CONDICIONES[$que]) && count($condicion) === 1 + self::CONDICIONES[$que];
    }

    /**
     * @param  array<int, mixed>  $condicion
     * @param  array<string, mixed>  $c
     * @param  array{0: int, 1: int}  $inicio
     * @param  array{0: int, 1: int}  $final
     * @param  array<string, mixed>  $extra
     */
    private static function cumple(array $condicion, array $c, array $inicio, array $final, array $extra): bool
    {
        $n = $condicion[1] ?? null;

        return match ($condicion[0]) {
            'por' => $n <= $final[0] - $final[1],
            'sin_mazo' => $c['mazos'] === 0,
            'envidos' => $c['envidos_ganados'] >= $n,
            'trucos' => $c['trucos_ganados'] >= $n,
            'retrucos' => $c['retrucos_ganados'] >= $n,
            'vale_cuatros' => $c['vale_cuatros_ganados'] >= $n,
            'faltas' => $c['faltas_ganadas'] >= $n,
            'sin_envido_perdido' => $c['envidos_perdidos'] === 0,
            'sin_truco_perdido' => $c['trucos_perdidos'] === 0,
            'envido_y_truco' => $c['envidos_ganados'] >= 1 && $c['trucos_ganados'] >= 1,
            'sumar' => $n <= $final[0] - $inicio[0],
            'que_no_sume' => $final[1] === $inicio[1],
            'sin_cantar' => ! in_array($n, $c['cantados'], true),
            'en_dos_bazas' => $c['manos_en_dos_bazas'] >= 1,
            'gana_el_truco' => $c['trucos_ganados'] >= 1,
            'contraflor_al_resto' => $c['al_resto_ganadas'] >= 1,
            'serie_sin_perder' => ($extra['perdidas_en_la_serie'] ?? 1) === 0,
            'final_por' => isset($extra['final']) && $extra['final'][0] - $extra['final'][1] >= $n,
            'final_sin_envido_perdido' => ($extra['envidos_perdidos_en_la_final'] ?? 1) === 0,
            default => throw new InvalidArgumentException("No se sabe contar «{$condicion[0]}»."),
        };
    }

    /**
     * Lo que hizo la persona (y su equipo) en la partida.
     *
     * @param  list<array<string, mixed>>  $hechos
     * @return array<string, mixed>
     */
    public static function contar(array $hechos, int $asiento = 0): array
    {
        $equipo = $asiento % 2;
        $c = [
            'mazos' => 0, 'envidos_ganados' => 0, 'envidos_perdidos' => 0, 'trucos_ganados' => 0, 'trucos_perdidos' => 0,
            'retrucos_ganados' => 0, 'vale_cuatros_ganados' => 0, 'faltas_ganadas' => 0, 'al_resto_ganadas' => 0,
            'cantados' => [], 'manos_en_dos_bazas' => 0,
        ];
        // La respuesta dice "envido" o "contraflor" para toda la familia: lo que se quiso es el último canto.
        $ultimoEnvido = null;
        $alResto = false;
        $falta = false;
        $bazas = 0;

        foreach ($hechos as $hecho) {
            switch ($hecho['tipo']) {
                case 'reparto':
                    $bazas = 0;
                    $falta = false;
                    $alResto = false;
                    $ultimoEnvido = null;
                    break;

                case 'mazo':
                    $c['mazos'] += (int) ($hecho['asiento'] === $asiento);
                    break;

                case 'canto':
                    if ($hecho['asiento'] === $asiento) {
                        $c['cantados'][] = $hecho['canto'];
                    }

                    if (in_array($hecho['canto'], ['envido', 'real_envido', 'falta_envido'], true)) {
                        $ultimoEnvido = $hecho['canto'];
                    }

                    if (in_array($hecho['canto'], ['contraflor', 'contraflor_al_resto'], true)) {
                        $alResto = $hecho['canto'] === 'contraflor_al_resto';
                    }
                    break;

                case 'respuesta':
                    if ($hecho['canto'] === 'envido') {
                        $falta = $hecho['quiere'] && $ultimoEnvido === 'falta_envido';
                    }
                    break;

                case 'tantos':
                    $nuestro = $equipo === $hecho['ganador'] % 2;

                    if ($hecho['canto'] === 'contraflor') {
                        $c['al_resto_ganadas'] += (int) ($nuestro && $alResto);
                        break;
                    }

                    $c[$nuestro ? 'envidos_ganados' : 'envidos_perdidos']++;
                    $c['faltas_ganadas'] += (int) ($nuestro && $falta);
                    break;

                case 'baza':
                    $bazas++;
                    break;

                case 'puntos':
                    // Una mano que se cerró con un truco querido vale 2 o más; 3 con el retruco y 4 con el vale cuatro.
                    if ($hecho['concepto'] === 'truco' && $hecho['puntos'] >= 2) {
                        if ($hecho['equipo'] === $equipo) {
                            $c['trucos_ganados']++;
                            $c['retrucos_ganados'] += (int) ($hecho['puntos'] >= 3);
                            $c['vale_cuatros_ganados'] += (int) ($hecho['puntos'] >= 4);
                        } else {
                            $c['trucos_perdidos']++;
                        }
                    }
                    break;

                case 'mano_cerrada':
                    $c['manos_en_dos_bazas'] += (int) ($hecho['ganador'] === $equipo && $hecho['motivo'] === 'bazas' && $bazas === 2);
                    break;
            }
        }

        return $c;
    }
}
