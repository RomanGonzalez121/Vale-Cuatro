<?php

namespace App\Juego;

use App\Motor\Carta;
use App\Motor\Tanto;

/**
 * Las señas del truco de a cuatro: lo que un jugador le dice a su compañero con un gesto, sin hablar.
 *
 * Son un conjunto fijo, no texto libre. No son reglamento (el motor no sabe nada de ellas): son
 * comunicación entre compañeros, y una seña llega solo al compañero de quien la hace.
 *
 * De cada una se sabe el gesto, qué quiere decir y cómo se dibuja para quien no las sabe todas:
 * la carta exacta, una carta con el número solo cuando la seña no dice el palo, o un ícono del sitio.
 */
final class Senas
{
    public const ANCHO_DE_ESPADA = 'ancho_de_espada';

    public const ANCHO_DE_BASTO = 'ancho_de_basto';

    public const SIETE_DE_ESPADA = 'siete_de_espada';

    public const SIETE_DE_ORO = 'siete_de_oro';

    public const UN_TRES = 'un_tres';

    public const UN_DOS = 'un_dos';

    public const ANCHO_FALSO = 'ancho_falso';

    public const NADA = 'nada';

    public const BUEN_ENVIDO = 'buen_envido';

    /** Desde qué tanto se dice "tengo buen envido": con lo que el bot Intermedio ya canta envido. */
    public const TANTO_PARA_EL_ENVIDO = 27;

    /**
     * Todas, de la carta más fuerte a la más débil, y al final las dos que no son una carta.
     *
     * - "gesto": lo que se ve hacer al otro ("Levanta las cejas").
     * - "hacer": lo mismo dicho para quien la va a hacer ("Levantar las cejas").
     * - "tiene": lo que dice que tiene, para armar "Tiene el ancho de espada" o "Tengo el ancho de espada".
     * - "carta": la carta exacta, si la seña la dice. "numero": el número solo, si no dice el palo.
     * - "fuerza": cuánto vale esa carta en el truco, con la misma escala que el motor (0 si no es una carta).
     *
     * @return array<string, array{gesto: string, hacer: string, tiene: string, carta: string|null, numero: int|null, fuerza: int}>
     */
    public static function todas(): array
    {
        return [
            self::ANCHO_DE_ESPADA => ['gesto' => 'Levanta las cejas', 'hacer' => 'Levantar las cejas', 'tiene' => 'el ancho de espada', 'carta' => '1-espada', 'numero' => null, 'fuerza' => 14],
            self::ANCHO_DE_BASTO => ['gesto' => 'Guiña un ojo', 'hacer' => 'Guiñar un ojo', 'tiene' => 'el ancho de basto', 'carta' => '1-basto', 'numero' => null, 'fuerza' => 13],
            self::SIETE_DE_ESPADA => ['gesto' => 'Mueve la boca a la derecha', 'hacer' => 'Mover la boca a la derecha', 'tiene' => 'el siete de espada', 'carta' => '7-espada', 'numero' => null, 'fuerza' => 12],
            self::SIETE_DE_ORO => ['gesto' => 'Mueve la boca a la izquierda', 'hacer' => 'Mover la boca a la izquierda', 'tiene' => 'el siete de oro', 'carta' => '7-oro', 'numero' => null, 'fuerza' => 11],
            self::UN_TRES => ['gesto' => 'Se muerde el labio', 'hacer' => 'Morderse el labio', 'tiene' => 'un tres', 'carta' => null, 'numero' => 3, 'fuerza' => 10],
            self::UN_DOS => ['gesto' => 'Tira un beso', 'hacer' => 'Tirar un beso', 'tiene' => 'un dos', 'carta' => null, 'numero' => 2, 'fuerza' => 9],
            self::ANCHO_FALSO => ['gesto' => 'Abre la boca', 'hacer' => 'Abrir la boca', 'tiene' => 'un ancho falso', 'carta' => null, 'numero' => 1, 'fuerza' => 8],
            self::NADA => ['gesto' => 'Cierra los ojos', 'hacer' => 'Cerrar los ojos', 'tiene' => 'nada', 'carta' => null, 'numero' => null, 'fuerza' => 0],
            self::BUEN_ENVIDO => ['gesto' => 'Ladea la cabeza', 'hacer' => 'Ladear la cabeza', 'tiene' => 'buen envido', 'carta' => null, 'numero' => null, 'fuerza' => 0],
        ];
    }

    public static function existe(string $sena): bool
    {
        return array_key_exists($sena, self::todas());
    }

    /**
     * Las señas que se pueden hacer con esas tres cartas, de la carta más fuerte a la más débil: una por
     * cada carta que tenga seña (dos tres son una sola seña), o "no tengo nada" si no tiene ninguna, y
     * "tengo buen envido" si el tanto alcanza. Nadie puede hacer una seña de lo que no tiene.
     *
     * @param  list<Carta>  $cartas  Las tres cartas que recibió en la mano.
     * @return list<string>
     */
    public static function de(array $cartas): array
    {
        $ids = array_map(fn (Carta $carta) => $carta->id(), $cartas);
        $numeros = array_map(fn (Carta $carta) => $carta->numero, $cartas);
        $senas = [];

        foreach (self::todas() as $sena => $datos) {
            $laTiene = match (true) {
                $datos['carta'] !== null => in_array($datos['carta'], $ids, true),
                $sena === self::ANCHO_FALSO => in_array('1-copa', $ids, true) || in_array('1-oro', $ids, true),
                $datos['numero'] !== null => in_array($datos['numero'], $numeros, true),
                default => false,
            };

            if ($laTiene) {
                $senas[] = $sena;
            }
        }

        if ($senas === []) {
            $senas[] = self::NADA;
        }

        if (Tanto::deEnvido($cartas) >= self::TANTO_PARA_EL_ENVIDO) {
            $senas[] = self::BUEN_ENVIDO;
        }

        return $senas;
    }

    /**
     * La carta más fuerte que dicen esas señas, en la escala del motor. 0 si no dicen ninguna.
     *
     * @param  list<string>  $senas
     */
    public static function fuerzaDe(array $senas): int
    {
        $todas = self::todas();

        return max([0, ...array_map(fn (string $sena) => $todas[$sena]['fuerza'] ?? 0, $senas)]);
    }
}
