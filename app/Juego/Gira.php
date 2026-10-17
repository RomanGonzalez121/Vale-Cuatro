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

    /**
     * Cómo se dice la manía de cada jefe, en el cartel de su bar.
     */
    public const MANIAS = [
        '' => 'Sin manía: es el que mejor juega del club.',
        'nunca_se_va' => 'Nunca se va al mazo: juega todas las manos hasta el final, aunque no tenga nada.',
        'canta_todo' => 'Canta todo: envido y truco en cada mano que puede. Hay que saber cuándo no querer.',
        'miente_siempre' => 'Miente siempre: lee las cartas y canta truco con cualquier cosa. Hay que animarse a querer.',
        'quiere_todo' => 'Quiere todo: nunca dice que no. Cada canto se juega hasta el final.',
        'arranca_con_ventaja' => 'Arranca con ventaja: la partida va a 30 y él empieza con 8.',
    ];

    /** Los tipos de nivel que se juegan (todos: la constante queda por si se suma un tipo nuevo que todavía no). */
    public const SE_JUEGAN = ['partida', 'abajo', 'jefe', 'mano', 'cuatro', 'serie', 'copa'];

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

        // Lo que se pasó cuenta por lo menos un fósforo: un escalón de antes de la gira puede no tener ninguno anotado.
        return Progreso::query()->where('jugador_id', $jugador->getKey())
            ->where(fn ($consulta) => $consulta->where('fosforos', '>', 0)->orWhereNotNull('superado_en'))
            ->get(['clave', 'fosforos'])
            ->mapWithKeys(fn (Progreso $progreso) => [$progreso->clave => max(1, (int) $progreso->fosforos)])->all();
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
     * Qué es un nivel, dicho para su ficha: un título, una línea de qué se juega y la carta que lo representa
     * (la del rival, la del jefe, o null si los rivales son una sorpresa).
     *
     * @param  array<string, mixed>  $barrio
     * @param  array<string, mixed>  $nivel
     * @return array{titulo: string, dice: string, carta: string|null}
     */
    public static function queEs(array $barrio, array $nivel): array
    {
        $maquina = Nivel::from($nivel['nivel'])->deLaMaquina();
        $rival = $nivel['rival'] ?? null;
        $carta = $rival === null ? null : (JugadoresDeEjemplo::cartas()[$rival] ?? null);

        return match ($nivel['tipo']) {
            'mano' => ['titulo' => Desafios::de($nivel['desafio'])['titulo'], 'dice' => Desafios::de($nivel['desafio'])['consigna'], 'carta' => null],
            'cuatro' => ['titulo' => 'De a cuatro', 'dice' => "Con un compañero máquina contra dos rivales, a {$nivel['puntos']}. Máquina {$maquina}.", 'carta' => null],
            'serie' => ['titulo' => $rival, 'dice' => "Al mejor de tres, partidas a {$nivel['puntos']}. Máquina {$maquina}.", 'carta' => $carta],
            'copa' => ['titulo' => "Copa de {$barrio['nombre']}", 'dice' => "Un torneo de cuatro a {$nivel['puntos']}: hay que salir campeón. Máquina {$maquina}.", 'carta' => null],
            'abajo' => ['titulo' => $rival, 'dice' => "Partida a {$nivel['puntos']} que arranca {$nivel['tanteo'][0]} a {$nivel['tanteo'][1]}. Máquina {$maquina}.", 'carta' => $carta],
            'jefe' => ['titulo' => $barrio['jefe']['nombre'], 'dice' => self::MANIAS[$barrio['jefe']['mania'] ?? ''], 'carta' => $barrio['jefe']['carta']],
            default => ['titulo' => $rival, 'dice' => "Partida a {$nivel['puntos']}. Máquina {$maquina}.", 'carta' => $carta],
        };
    }

    /**
     * La gira como la ve un jugador: cada barrio con sus niveles, cuántos fósforos lleva en cada uno y qué
     * está abierto. Sin jugador, todo en cero y solo el primer nivel abierto.
     *
     * @return list<array<string, mixed>>
     */
    public static function de(?Jugador $jugador): array
    {
        $fosforos = self::fosforos($jugador);
        $barrios = [];
        $abiertoElBarrio = true;

        foreach (self::barrios() as $barrio) {
            $niveles = [];
            $anteriorPasado = $abiertoElBarrio;

            foreach ($barrio['niveles'] as $nivel) {
                $suyos = $fosforos[self::clave($barrio['clave'], $nivel['numero'])] ?? 0;
                $niveles[] = [...$nivel, 'fosforos' => $suyos, 'pasado' => $suyos > 0, 'abierto' => $anteriorPasado, ...self::queEs($barrio, $nivel)];
                $anteriorPasado = $suyos > 0;
            }

            $barrios[] = [
                ...$barrio,
                'niveles' => $niveles,
                'abierto' => $abiertoElBarrio,
                'fosforos' => array_sum(array_column($niveles, 'fosforos')),
                'deTodos' => count($niveles) * FosforosDeGira::MAXIMO,
                'completo' => end($niveles)['pasado'],
            ];
            $abiertoElBarrio = end($niveles)['pasado'];
        }

        return $barrios;
    }

    /**
     * La manía con que juega la máquina en esa partida: la del jefe, si la partida es contra el jefe de un barrio
     * y su manía cambia cómo juega (App\Juego\BotConMania). Null en cualquier otra.
     */
    public static function maniaDe(Partida $partida): ?string
    {
        [$clave, $numero] = self::deLaPartida($partida) ?? [null, null];
        $nivel = $clave === null ? null : self::nivel($clave, $numero);

        if ($nivel === null || $nivel['tipo'] !== 'jefe') {
            return null;
        }

        $mania = self::barrio($clave)['jefe']['mania'] ?? null;

        return in_array($mania, BotConMania::MANIAS, true) ? $mania : null;
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
