<?php

namespace App\Juego;

use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Progreso;

/**
 * La gira de los clubes (M26): seis barrios en orden, cada uno con sus niveles y un jefe al final. Pasar un
 * nivel abre el siguiente y ganarle al jefe abre el barrio que sigue.
 *
 * Los barrios están en fichas-de-gira.php. Boedo es la escalera del club: sus niveles son los escalones y se
 * anotan con su clave de siempre ("escalon:N"), así el progreso de antes vale tal cual. Los de los otros
 * barrios se anotan como "gira:<barrio>:<nivel>".
 *
 * Cada nivel da hasta tres fósforos (App\Juego\FosforosDeGira); queda guardado el mejor intento.
 */
final class Gira
{
    /** A cuántos puntos van las partidas de la gira, salvo que el nivel diga otra cosa: cortas, como en la escalera. */
    public const PUNTOS = 15;

    /** @var list<array<string, mixed>>|null */
    private static ?array $barrios = null;

    /**
     * Los barrios, en orden, con sus niveles completos (los de Boedo, armados desde la escalera).
     *
     * @return list<array<string, mixed>>
     */
    public static function barrios(): array
    {
        return self::$barrios ??= array_map(self::armar(...), require __DIR__.'/fichas-de-gira.php');
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function barrio(string $clave): ?array
    {
        foreach (self::barrios() as $barrio) {
            if ($barrio['clave'] === $clave) {
                return $barrio;
            }
        }

        return null;
    }

    /**
     * Un nivel de un barrio (del 1 en adelante), o null si no existe.
     *
     * @return array<string, mixed>|null
     */
    public static function nivel(string $barrio, int $numero): ?array
    {
        return self::barrio($barrio)['niveles'][$numero - 1] ?? null;
    }

    /**
     * La clave con que se anota un nivel en los progresos.
     */
    public static function clave(string $barrio, int $numero): string
    {
        $nivel = self::nivel($barrio, $numero);

        return isset($nivel['escalon']) ? Progresos::claveDeEscalon($nivel['escalon']) : "gira:{$barrio}:{$numero}";
    }

    /**
     * El barrio y el nivel de una partida de la gira (o de un escalón de la escalera, que es Boedo).
     *
     * @return array{0: string, 1: int}|null
     */
    public static function deLaPartida(Partida $partida): ?array
    {
        if ($partida->escalon !== null) {
            return ['boedo', $partida->escalon];
        }

        if ($partida->gira === null || ! str_contains($partida->gira, ':')) {
            return null;
        }

        [$barrio, $numero] = explode(':', $partida->gira, 2);

        return self::nivel($barrio, (int) $numero) === null ? null : [$barrio, (int) $numero];
    }

    /**
     * Los fósforos que lleva un jugador en cada nivel: clave => fósforos (solo los que tienen alguno).
     *
     * @return array<string, int>
     */
    public static function fosforos(?Jugador $jugador): array
    {
        if ($jugador === null) {
            return [];
        }

        return Progreso::query()->where('jugador_id', $jugador->getKey())->where('fosforos', '>', 0)->pluck('fosforos', 'clave')
            ->map(fn ($fosforos) => (int) $fosforos)->all();
    }

    public static function pasado(?Jugador $jugador, string $barrio, int $numero, ?array $fosforos = null): bool
    {
        $fosforos ??= self::fosforos($jugador);

        return ($fosforos[self::clave($barrio, $numero)] ?? 0) > 0;
    }

    /**
     * Si un nivel se puede jugar: existe, el anterior del barrio está pasado y, si es el primero de un barrio,
     * le ganaste al jefe del barrio anterior. Uno ya pasado se puede volver a jugar, para sumar fósforos.
     */
    public static function abierto(?Jugador $jugador, string $barrio, int $numero): bool
    {
        if (self::nivel($barrio, $numero) === null) {
            return false;
        }

        $fosforos = self::fosforos($jugador);

        if ($numero > 1) {
            return self::pasado($jugador, $barrio, $numero - 1, $fosforos);
        }

        $anterior = self::anterior($barrio);

        return $anterior === null || self::pasado($jugador, $anterior['clave'], count($anterior['niveles']), $fosforos);
    }

    /**
     * Cuántos niveles tiene la gira entera.
     */
    public static function cuantos(): int
    {
        return array_sum(array_map(fn (array $barrio) => count($barrio['niveles']), self::barrios()));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function anterior(string $barrio): ?array
    {
        $previo = null;

        foreach (self::barrios() as $uno) {
            if ($uno['clave'] === $barrio) {
                return $previo;
            }

            $previo = $uno;
        }

        return null;
    }

    /**
     * Completa un barrio: a cada nivel le pone su número, su nivel de máquina y sus puntos; a los de Boedo,
     * lo que dice su escalón.
     *
     * @param  array<string, mixed>  $barrio
     * @return array<string, mixed>
     */
    private static function armar(array $barrio): array
    {
        $niveles = [];

        foreach ($barrio['niveles'] as $i => $nivel) {
            if (isset($nivel['escalon'])) {
                $escalon = Escalera::escalon($nivel['escalon']);
                $nivel += [
                    'tipo' => $escalon['tipo'] === Escalera::MANO ? 'mano' : ($nivel['escalon'] === Escalera::cuantos() ? 'jefe' : 'partida'),
                    'rival' => $escalon['rival'],
                    'desafio' => $escalon['desafio'],
                    'nivel' => $escalon['nivel']->value,
                    'puntos' => $escalon['tipo'] === Escalera::MANO ? Desafios::PUNTOS : Escalera::PUNTOS,
                ];
            } else {
                $nivel['nivel'] ??= match ($nivel['tipo']) {
                    'jefe' => $barrio['jefe']['nivel'],
                    'mano' => Desafios::de($nivel['desafio'])['nivel'],
                    default => JugadoresDeEjemplo::lista()[$nivel['rival']]->value,
                };
                $nivel['puntos'] ??= $nivel['tipo'] === 'mano' ? Desafios::PUNTOS : self::PUNTOS;
            }

            $niveles[] = ['numero' => $i + 1, ...$nivel];
        }

        return ['escalera' => false, ...$barrio, 'niveles' => $niveles];
    }
}
