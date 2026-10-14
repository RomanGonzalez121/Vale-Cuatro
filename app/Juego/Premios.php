<?php

namespace App\Juego;

use App\Models\Jugador;
use App\Models\Resultado;

/**
 * Lo que se gana jugando y se ve: "tu carta" de perfil y el dorso de las cartas propias.
 * Ninguno cambia nada en la mesa: son cómo te ven los demás.
 *
 * Las cartas forman un camino en el orden del truco, del 4 al ancho de espada, y cada una se destraba
 * con una cantidad de partidas ganadas (las mismas que cuentan para el ranking). Las categorías son
 * tres tramos de ese camino. Los dorsos se ganan pasando escalones de la escalera del club.
 *
 * Qué tiene destrabado cada uno no se guarda: se calcula de sus resultados y de sus progresos. Lo que
 * se guarda es qué eligió, y al leerlo se vuelve a comprobar que lo tenga.
 *
 * La cuenta de administración tiene todas las cartas y los dorsos abiertos, para ver cómo queda cada
 * premio sin inventar partidas. Su categoría no cambia: sale de sus partidas, como la de todos.
 */
final class Premios
{
    public const CARTA_DE_FABRICA = '4-copa';

    public const DORSO_DE_FABRICA = 'fabrica';

    /**
     * El camino: cada carta y con cuántas partidas ganadas se destraba. Va de menor a mayor en el orden del truco.
     *
     * @var array<string, int>
     */
    public const CAMINO = [
        '4-copa' => 0,
        '5-espada' => 2,
        '6-oro' => 4,
        '7-copa' => 6,
        '10-basto' => 8,
        '11-espada' => 10,
        '12-oro' => 14,
        '1-copa' => 18,
        '2-basto' => 24,
        '3-copa' => 31,
        '7-oro' => 40,
        '7-espada' => 55,
        '1-basto' => 75,
        '1-espada' => 100,
    ];

    /**
     * Los dorsos: su nombre y qué escalón de la escalera hay que pasar para usarlo. El de fábrica es de todos.
     *
     * @var array<string, array{nombre: string, escalon: int}>
     */
    public const DORSOS = [
        'fabrica' => ['nombre' => 'De fábrica', 'escalon' => 0],
        'fosforos' => ['nombre' => 'Fósforos', 'escalon' => 3],
        'tinta' => ['nombre' => 'Tinta', 'escalon' => 6],
        'ronda' => ['nombre' => 'Ronda', 'escalon' => 8],
    ];

    /**
     * Las partidas ganadas que cuentan: las que anotó el ranking leyendo los eventos de cada partida.
     */
    public function ganadas(?Jugador $jugador): int
    {
        return $jugador === null ? 0 : Resultado::query()->where('jugador_id', $jugador->getKey())->where('gano', true)->count();
    }

    public function categoria(?Jugador $jugador): Categoria
    {
        return Categoria::con($this->ganadas($jugador));
    }

    /**
     * El camino entero como lo ve ese jugador: qué cartas tiene abiertas, cuál eligió y en qué categoría cae cada una.
     *
     * @return list<array{carta: string, ganadas: int, categoria: Categoria, abierta: bool, elegida: bool}>
     */
    public function camino(?Jugador $jugador): array
    {
        $ganadas = $this->ganadas($jugador);
        $todo = self::loTieneTodo($jugador);
        $elegida = self::cartaCon($jugador?->carta, $ganadas, $todo);

        return array_map(fn (string $carta, int $desde) => [
            'carta' => $carta,
            'ganadas' => $desde,
            'categoria' => Categoria::con($desde),
            'abierta' => $todo || $ganadas >= $desde,
            'elegida' => $carta === $elegida,
        ], array_keys(self::CAMINO), self::CAMINO);
    }

    /**
     * La próxima carta que le falta destrabar y cuántas partidas ganadas le faltan, o null si ya tiene todas.
     *
     * @return array{carta: string, ganadas: int, faltan: int, desde: int}|null `desde` es con cuántas se destrabó la anterior.
     */
    public function proxima(?Jugador $jugador): ?array
    {
        $ganadas = $this->ganadas($jugador);
        $anterior = 0;

        foreach (self::CAMINO as $carta => $desde) {
            if ($ganadas < $desde) {
                return ['carta' => $carta, 'ganadas' => $desde, 'faltan' => $desde - $ganadas, 'desde' => $anterior];
            }

            $anterior = $desde;
        }

        return null;
    }

    /**
     * Los dorsos como los ve ese jugador: cuáles tiene abiertos y cuál eligió.
     *
     * @return list<array{clave: string, nombre: string, escalon: int, abierto: bool, elegido: bool}>
     */
    public function dorsos(?Jugador $jugador): array
    {
        $pasados = $this->escalonesPasados($jugador);
        $todo = self::loTieneTodo($jugador);
        $elegido = self::dorsoCon($jugador?->dorso, $pasados, $todo);

        return array_map(fn (string $clave, array $dorso) => [
            'clave' => $clave,
            ...$dorso,
            'abierto' => $todo || $dorso['escalon'] === 0 || in_array($dorso['escalon'], $pasados, true),
            'elegido' => $clave === $elegido,
        ], array_keys(self::DORSOS), self::DORSOS);
    }

    public function tieneCarta(Jugador $jugador, string $carta): bool
    {
        return isset(self::CAMINO[$carta]) && (self::loTieneTodo($jugador) || $this->ganadas($jugador) >= self::CAMINO[$carta]);
    }

    public function tieneDorso(Jugador $jugador, string $dorso): bool
    {
        $escalon = self::DORSOS[$dorso]['escalon'] ?? null;

        return $escalon !== null && (self::loTieneTodo($jugador) || $escalon === 0 || in_array($escalon, $this->escalonesPasados($jugador), true));
    }

    /**
     * La carta que representa a ese jugador. Un jugador de ejemplo tiene la suya, fija, según su nivel.
     */
    public function cartaDe(?Jugador $jugador): string
    {
        if ($jugador?->esDeEjemplo()) {
            return JugadoresDeEjemplo::cartas()[$jugador->apodo] ?? self::CARTA_DE_FABRICA;
        }

        return self::cartaCon($jugador?->carta, $this->ganadas($jugador), self::loTieneTodo($jugador));
    }

    /**
     * El dorso de las cartas de ese jugador: lo que ve su rival.
     */
    public function dorsoDe(?Jugador $jugador): string
    {
        return self::dorsoCon($jugador?->dorso, $this->escalonesPasados($jugador), self::loTieneTodo($jugador));
    }

    /**
     * Lo único del perfil de un jugador que recibe otro: su apodo, su categoría, su carta y su dorso.
     *
     * @return array{apodo: string, categoria: string, carta: string, dorso: string}
     */
    public function aLaVista(Jugador $jugador): array
    {
        return [
            'apodo' => $jugador->apodo,
            'categoria' => $this->categoria($jugador)->nombre(),
            'carta' => $this->cartaDe($jugador),
            'dorso' => $this->dorsoDe($jugador),
        ];
    }

    /**
     * Si ese jugador tiene todo abierto: es la cuenta de administración, que prueba los premios.
     */
    public static function loTieneTodo(?Jugador $jugador): bool
    {
        return $jugador?->esAdministrador() ?? false;
    }

    /**
     * La carta elegida si existe y está destrabada con esas ganadas (o lo tiene todo); si no, la de fábrica.
     * Sirve también para quien arma una tabla y ya tiene las ganadas de cada uno.
     */
    public static function cartaCon(?string $elegida, int $ganadas, bool $todo = false): string
    {
        return $elegida !== null && isset(self::CAMINO[$elegida]) && ($todo || $ganadas >= self::CAMINO[$elegida]) ? $elegida : self::CARTA_DE_FABRICA;
    }

    /**
     * @param  list<int>  $pasados  Los escalones que ya pasó.
     */
    private static function dorsoCon(?string $elegido, array $pasados, bool $todo = false): string
    {
        $escalon = self::DORSOS[$elegido ?? '']['escalon'] ?? null;

        return $escalon !== null && ($todo || $escalon === 0 || in_array($escalon, $pasados, true)) ? (string) $elegido : self::DORSO_DE_FABRICA;
    }

    /**
     * Los números de los escalones de la escalera que ese jugador ya pasó.
     *
     * @return list<int>
     */
    private function escalonesPasados(?Jugador $jugador): array
    {
        $pasados = [];

        foreach ((new Progresos)->superados($jugador) as $clave) {
            if (str_starts_with($clave, 'escalon:')) {
                $pasados[] = (int) substr($clave, 8);
            }
        }

        return $pasados;
    }
}
