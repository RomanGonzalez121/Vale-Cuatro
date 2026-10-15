<?php

namespace App\Juego;

/**
 * La escalera del club: ocho escalones en orden. Pasar uno abre el siguiente.
 *
 * Un escalón es una partida corta contra alguien del club (un bot, cada vez de un nivel más alto) o
 * una mano con objetivo, que es un desafío del catálogo. Los rivales son jugadores de ejemplo del
 * ranking, con la carta que los identifica: cuanto más alta, más difícil el bot.
 */
final class Escalera
{
    /** A cuántos puntos van las partidas de la escalera: cortas, como las de un torneo. */
    public const PUNTOS = 15;

    public const PARTIDA = 'partida';

    public const MANO = 'mano';

    /**
     * Los escalones, de abajo hacia arriba. De una partida: contra quién, su nivel y su carta.
     * De una mano: el desafío que se juega.
     *
     * @return list<array{numero: int, tipo: string, titulo: string, detalle: string, nivel: Nivel, rival: string|null, carta: string|null, desafio: string|null}>
     */
    public static function escalones(): array
    {
        $escalones = [];

        foreach ([
            [self::PARTIDA, 'Pichón Ibarra'],
            [self::MANO, 'con-la-justa'],
            [self::PARTIDA, 'Cacho de Lanús'],
            [self::MANO, 'los-tres-que-faltan'],
            [self::PARTIDA, 'La Tana Rossi'],
            [self::MANO, 'al-resto'],
            [self::MANO, 'vale-cuatro'],
            [self::PARTIDA, 'Don Anselmo'],
        ] as $i => [$tipo, $cual]) {
            $escalones[] = ['numero' => $i + 1, 'tipo' => $tipo, ...($tipo === self::PARTIDA ? self::partida($cual) : self::mano($cual))];
        }

        return $escalones;
    }

    /**
     * El escalón con ese número, o null si no existe.
     *
     * @return array{numero: int, tipo: string, titulo: string, detalle: string, nivel: Nivel, rival: string|null, carta: string|null, desafio: string|null}|null
     */
    public static function escalon(int $numero): ?array
    {
        return self::escalones()[$numero - 1] ?? null;
    }

    public static function cuantos(): int
    {
        return count(self::escalones());
    }

    /**
     * @return array{titulo: string, detalle: string, nivel: Nivel, rival: string, carta: string, desafio: null}
     */
    private static function partida(string $rival): array
    {
        $nivel = JugadoresDeEjemplo::lista()[$rival];

        return [
            'titulo' => $rival,
            'detalle' => 'Una partida a '.self::PUNTOS.' contra la máquina '.$nivel->deLaMaquina().'.',
            'nivel' => $nivel,
            'rival' => $rival,
            'carta' => JugadoresDeEjemplo::cartas()[$rival],
            'desafio' => null,
        ];
    }

    /**
     * @return array{titulo: string, detalle: string, nivel: Nivel, rival: null, carta: null, desafio: string}
     */
    private static function mano(string $clave): array
    {
        $desafio = Desafios::de($clave);

        return [
            'titulo' => $desafio['titulo'],
            'detalle' => $desafio['consigna'],
            'nivel' => Nivel::from($desafio['nivel']),
            'rival' => null,
            'carta' => null,
            'desafio' => $clave,
        ];
    }
}
