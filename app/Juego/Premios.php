<?php

namespace App\Juego;

use App\Models\Jugador;
use App\Models\Resultado;

/**
 * Lo que se gana jugando y se ve: "tu carta" de perfil y el dorso de las cartas. Ninguno cambia nada en la mesa.
 *
 * Se destraban subiendo de nivel (App\Juego\Camino; el camino entero está en fichas-de-premios.php). Las
 * cartas van cada tres niveles en el orden del truco, del 4 de copa de entrada al ancho de espada en el 40.
 * Tres dorsos se ganan en la escalera del club. Las categorías son tramos de niveles.
 *
 * Cada uno ve lo suyo: en tu pantalla todas las cartas boca abajo salen con tu dorso, también las del
 * rival. Al otro le llega de vos solo el apodo, la categoría y tu carta.
 *
 * Qué tiene destrabado cada uno no se guarda: se calcula de su nivel y de sus progresos. Lo que se guarda
 * es qué eligió, y al leerlo se vuelve a comprobar que lo tenga.
 *
 * La cuenta de administración tiene todo abierto, para ver cómo queda cada premio sin inventar partidas.
 * Su nivel y su categoría salen de sus partidas, como los de todos.
 */
final class Premios
{
    public const CARTA_DE_FABRICA = '4-copa';

    public const DORSO_DE_FABRICA = 'fabrica';

    /**
     * Las cartas y en qué nivel se destraba cada una. Va de menor a mayor en el orden del truco.
     *
     * @var array<string, int>
     */
    public const CAMINO = [
        '4-copa' => 1,
        '5-espada' => 4,
        '6-oro' => 7,
        '7-copa' => 10,
        '10-basto' => 13,
        '11-espada' => 16,
        '12-oro' => 19,
        '1-copa' => 22,
        '2-basto' => 25,
        '3-copa' => 28,
        '7-oro' => 31,
        '7-espada' => 34,
        '1-basto' => 37,
        '1-espada' => 40,
    ];

    /**
     * Los dorsos ya dibujados: su nombre y cómo se ganan, por nivel o por un escalón de la escalera. El de
     * fábrica es de todos. Los que faltan dibujar están en el camino como "Próximamente".
     *
     * @var array<string, array{nombre: string, nivel?: int, escalon?: int}>
     */
    public const DORSOS = [
        'fabrica' => ['nombre' => 'Isotipos', 'nivel' => 1],
        'rombos' => ['nombre' => 'Rombos', 'nivel' => 3],
        'fosforos' => ['nombre' => 'Fósforos', 'escalon' => 3],
        'tinta' => ['nombre' => 'Tinta', 'escalon' => 6],
        'ronda' => ['nombre' => 'Ronda', 'escalon' => 8],
    ];

    /**
     * Las partidas ganadas que cuentan para el ranking. Ya no deciden los premios: es un número del perfil.
     */
    public function ganadas(?Jugador $jugador): int
    {
        return $jugador === null ? 0 : Resultado::query()->where('jugador_id', $jugador->getKey())->where('gano', true)->count();
    }

    public function nivel(?Jugador $jugador): int
    {
        return Camino::nivelDe($jugador);
    }

    public function categoria(?Jugador $jugador): Categoria
    {
        return Categoria::delNivel($this->nivel($jugador));
    }

    /**
     * Las cartas como las ve ese jugador: cuáles tiene abiertas, cuál eligió y en qué categoría cae cada una.
     *
     * @return list<array{carta: string, nivel: int, categoria: Categoria, abierta: bool, elegida: bool}>
     */
    public function camino(?Jugador $jugador): array
    {
        $nivel = $this->nivel($jugador);
        $todo = self::loTieneTodo($jugador);
        $elegida = self::cartaCon($jugador?->carta, $nivel, $todo);

        return array_map(fn (string $carta, int $desde) => [
            'carta' => $carta,
            'nivel' => $desde,
            'categoria' => Categoria::delNivel($desde),
            'abierta' => $todo || $nivel >= $desde,
            'elegida' => $carta === $elegida,
        ], array_keys(self::CAMINO), self::CAMINO);
    }

    /**
     * La próxima carta que le falta y en qué nivel llega, o null si ya tiene todas.
     *
     * @return array{carta: string, nivel: int}|null
     */
    public function proxima(?Jugador $jugador): ?array
    {
        $nivel = $this->nivel($jugador);

        foreach (self::CAMINO as $carta => $desde) {
            if ($nivel < $desde) {
                return ['carta' => $carta, 'nivel' => $desde];
            }
        }

        return null;
    }

    /**
     * Los dorsos como los ve ese jugador: cuáles tiene abiertos y cuál eligió.
     *
     * @return list<array{clave: string, nombre: string, nivel?: int, escalon?: int, abierto: bool, elegido: bool}>
     */
    public function dorsos(?Jugador $jugador): array
    {
        $nivel = $this->nivel($jugador);
        $pasados = $this->escalonesPasados($jugador);
        $todo = self::loTieneTodo($jugador);
        $elegido = self::dorsoCon($jugador?->dorso, $nivel, $pasados, $todo);

        return array_map(fn (string $clave, array $dorso) => [
            'clave' => $clave,
            ...$dorso,
            'abierto' => $todo || self::dorsoAbierto($dorso, $nivel, $pasados),
            'elegido' => $clave === $elegido,
        ], array_keys(self::DORSOS), self::DORSOS);
    }

    public function tieneCarta(Jugador $jugador, string $carta): bool
    {
        return isset(self::CAMINO[$carta]) && (self::loTieneTodo($jugador) || $this->nivel($jugador) >= self::CAMINO[$carta]);
    }

    public function tieneDorso(Jugador $jugador, string $dorso): bool
    {
        return isset(self::DORSOS[$dorso])
            && (self::loTieneTodo($jugador) || self::dorsoAbierto(self::DORSOS[$dorso], $this->nivel($jugador), $this->escalonesPasados($jugador)));
    }

    /**
     * La carta que representa a ese jugador. Un jugador de ejemplo tiene la suya, fija, según su nivel de máquina.
     */
    public function cartaDe(?Jugador $jugador): string
    {
        if ($jugador?->esDeEjemplo()) {
            return JugadoresDeEjemplo::cartas()[$jugador->apodo] ?? self::CARTA_DE_FABRICA;
        }

        return self::cartaCon($jugador?->carta, $this->nivel($jugador), self::loTieneTodo($jugador));
    }

    /**
     * El dorso con que ese jugador ve todas las cartas boca abajo: las suyas, las del mazo y las del rival.
     */
    public function dorsoDe(?Jugador $jugador): string
    {
        return self::dorsoCon($jugador?->dorso, $this->nivel($jugador), $this->escalonesPasados($jugador), self::loTieneTodo($jugador));
    }

    /**
     * El dorso de quien está mirando la página, una sola vez por pedido: lo usa cada carta boca abajo.
     */
    public static function dorsoPropio(): string
    {
        $pedido = request();

        if (! $pedido->attributes->has('dorso-propio')) {
            $pedido->attributes->set('dorso-propio', (new self)->dorsoDe($pedido->user()));
        }

        return $pedido->attributes->get('dorso-propio');
    }

    /**
     * Lo único del perfil de un jugador que recibe otro: su apodo, su categoría y su carta.
     *
     * @return array{apodo: string, categoria: string, carta: string}
     */
    public function aLaVista(Jugador $jugador): array
    {
        return [
            'apodo' => $jugador->apodo,
            'categoria' => $this->categoria($jugador)->nombre(),
            'carta' => $this->cartaDe($jugador),
        ];
    }

    /**
     * Si subió de nivel desde la última vez que miró su perfil y en el medio se destrabó algo ya dibujado:
     * el menú le marca su apodo con un puntito hasta que lo vea.
     */
    public function hayNovedad(?Jugador $jugador): bool
    {
        if ($jugador === null || $jugador->esInvitado()) {
            return false;
        }

        $nivel = $this->nivel($jugador);

        $visto = (int) ($jugador->nivel_visto ?? 1);

        return $nivel > $visto && array_filter(Camino::entre($visto, $nivel), fn (array $ficha) => $ficha['listo']) !== [];
    }

    /**
     * Ya vio sus premios hasta el nivel en que está.
     */
    public function marcarVisto(Jugador $jugador): void
    {
        $nivel = $this->nivel($jugador);

        if ($jugador->nivel_visto !== $nivel) {
            $jugador->forceFill(['nivel_visto' => $nivel])->save();
        }
    }

    /**
     * Si ese jugador tiene todo abierto: es la cuenta de administración, que prueba los premios.
     */
    public static function loTieneTodo(?Jugador $jugador): bool
    {
        return $jugador?->esAdministrador() ?? false;
    }

    /**
     * La carta elegida si existe y está destrabada en ese nivel (o lo tiene todo); si no, la de fábrica.
     * Sirve también para quien arma una tabla y ya tiene el nivel de cada uno.
     */
    public static function cartaCon(?string $elegida, int $nivel, bool $todo = false): string
    {
        return $elegida !== null && isset(self::CAMINO[$elegida]) && ($todo || $nivel >= self::CAMINO[$elegida]) ? $elegida : self::CARTA_DE_FABRICA;
    }

    /**
     * @param  list<int>  $pasados  Los escalones que ya pasó.
     */
    private static function dorsoCon(?string $elegido, int $nivel, array $pasados, bool $todo = false): string
    {
        $dorso = self::DORSOS[$elegido ?? ''] ?? null;

        return $dorso !== null && ($todo || self::dorsoAbierto($dorso, $nivel, $pasados)) ? (string) $elegido : self::DORSO_DE_FABRICA;
    }

    /**
     * @param  array{nombre: string, nivel?: int, escalon?: int}  $dorso
     * @param  list<int>  $pasados
     */
    private static function dorsoAbierto(array $dorso, int $nivel, array $pasados): bool
    {
        return isset($dorso['escalon']) ? in_array($dorso['escalon'], $pasados, true) : $nivel >= $dorso['nivel'];
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
