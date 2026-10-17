<?php

namespace App\Juego;

use App\Models\Jugador;
use App\Models\Resultado;

/**
 * Lo que se gana jugando y se ve: "tu carta" de perfil, el dorso de las cartas, la mesa, la forma de anotar,
 * el distintivo y el título. Ninguno cambia nada del juego.
 *
 * Se destraban subiendo de nivel (App\Juego\Camino; el camino entero está en fichas-de-premios.php). Las
 * cartas van cada tres niveles en el orden del truco, del 4 de copa de entrada al ancho de espada en el 40.
 * Tres dorsos se ganan en la escalera del club. Las categorías son tramos de niveles.
 *
 * Cada uno ve lo suyo: en tu pantalla todas las cartas boca abajo salen con tu dorso, también las del
 * rival, y lo mismo tu mesa y tu forma de anotar. Al otro le llega de vos solo el apodo, la categoría, tu
 * carta, tu título y tu distintivo.
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
        'damero' => ['nombre' => 'Damero', 'nivel' => 8],
        'esterilla' => ['nombre' => 'Esterilla', 'nivel' => 11],
        'lunares' => ['nombre' => 'Lunares', 'nivel' => 12],
        'aros' => ['nombre' => 'Aros', 'nivel' => 14],
        'zigzag' => ['nombre' => 'Zigzag', 'nivel' => 16],
        'guarda-griega' => ['nombre' => 'Guarda griega', 'nivel' => 18],
        'ramitas' => ['nombre' => 'Ramitas', 'nivel' => 20],
        'cuadros' => ['nombre' => 'Cuadros', 'nivel' => 21],
        'abanicos' => ['nombre' => 'Abanicos', 'nivel' => 23],
        'ondas' => ['nombre' => 'Ondas', 'nivel' => 24],
        'tanteador' => ['nombre' => 'Tanteador', 'nivel' => 27],
        'estrella' => ['nombre' => 'Estrella', 'nivel' => 30],
        'grabado' => ['nombre' => 'Grabado', 'nivel' => 39],
        'las-que-mandan' => ['nombre' => 'Las que mandan', 'nivel' => 40],
        'guarda-pampa' => ['nombre' => 'Guarda pampa', 'nivel' => 32],
        'monograma' => ['nombre' => 'Monograma', 'nivel' => 35],
        'fosforos' => ['nombre' => 'Fósforos', 'escalon' => 3],
        'tinta' => ['nombre' => 'Tinta', 'escalon' => 6],
        'ronda' => ['nombre' => 'Ronda', 'escalon' => 8],
    ];

    /**
     * Las mesas, las formas de anotar y los distintivos ya dibujados, con el nivel en que se ganan. La mesa y
     * la forma de anotar de fábrica son de todos; el distintivo no tiene uno de fábrica: sin elegir, no hay.
     * Cada uno ve su mesa y su forma de anotar; el distintivo, como el título, lo ven todos.
     *
     * @var array<string, array<string, array{nombre: string, nivel: int, ficha?: string}>>
     */
    public const POR_NIVEL = [
        'mesa' => [
            'fabrica' => ['nombre' => 'Paño', 'nivel' => 1],
            'chapa' => ['nombre' => 'Chapa de conventillo', 'nivel' => 2],
            'adoquines' => ['nombre' => 'Adoquines', 'nivel' => 3],
            'cajon' => ['nombre' => 'Cajón del mercado', 'nivel' => 4],
            'pano-petroleo' => ['nombre' => 'Paño petróleo', 'nivel' => 5],
            'bordado' => ['nombre' => 'Bordado', 'nivel' => 9],
            'marmol' => ['nombre' => 'Mármol de café', 'nivel' => 10],
            'pizarron' => ['nombre' => 'Pizarrón', 'nivel' => 14],
            'madera' => ['nombre' => 'Madera del club', 'nivel' => 17],
            'baldosa' => ['nombre' => 'Baldosa calcárea', 'nivel' => 20],
            'mantel' => ['nombre' => 'Mantel de bodegón', 'nivel' => 23],
            'milonga' => ['nombre' => 'Milonga', 'nivel' => 25],
            'marmol-negro' => ['nombre' => 'Mármol negro', 'nivel' => 26],
            'diario' => ['nombre' => 'Diario de café', 'nivel' => 28],
            'pana' => ['nombre' => 'Pana del club inglés', 'nivel' => 30],
            'cuero' => ['nombre' => 'Cuero de estancia', 'nivel' => 32],
            'pulperia' => ['nombre' => 'Pulpería', 'nivel' => 34],
            'cobre' => ['nombre' => 'Cobre de la pava', 'nivel' => 36],
            'art-deco' => ['nombre' => 'Art Déco', 'nivel' => 38],
            'tarot' => ['nombre' => 'Tarot', 'nivel' => 38],
            'grafito' => ['nombre' => 'Grafito y latón', 'nivel' => 39],
            'fileteado' => ['nombre' => 'Fileteado porteño', 'nivel' => 40],
        ],
        // Los mazos: cómo se ven las cartas en tu mesa (App\Juego\Mazos dice de qué piezas está hecho cada uno).
        'mazo' => [
            'fabrica' => ['nombre' => 'De fábrica', 'nivel' => 1],
            // Las dos variantes del de fábrica: de todos desde el principio y fuera del camino (Román, 16 de octubre de 2026).
            'noche' => ['nombre' => 'De fábrica, de noche', 'nivel' => 1],
            'una-tinta' => ['nombre' => 'De fábrica, a una tinta', 'nivel' => 1],
            'clasico' => ['nombre' => 'Clásico', 'nivel' => 5],
            'gatos' => ['nombre' => 'Gatos', 'nivel' => 6],
            'azulejo' => ['nombre' => 'Azulejo de subte', 'nivel' => 8],
            'tango' => ['nombre' => 'Tango', 'nivel' => 9],
            'murga' => ['nombre' => 'Murga', 'nivel' => 12],
            'diario' => ['nombre' => 'Diario', 'nivel' => 15],
            'bordado' => ['nombre' => 'Bordado', 'nivel' => 17],
            'caja-de-fosforos' => ['nombre' => 'Caja de fósforos', 'nivel' => 19],
            'retratos' => ['nombre' => 'Retratos', 'nivel' => 22],
            'pizarron' => ['nombre' => 'Pizarrón', 'nivel' => 26],
            'tipografico' => ['nombre' => 'Tipográfico', 'nivel' => 29],
            'billete' => ['nombre' => 'Billete', 'nivel' => 31],
            'afiche' => ['nombre' => 'Afiche', 'nivel' => 33],
            'catalan' => ['nombre' => 'Catalán', 'nivel' => 35],
            'estampilla' => ['nombre' => 'Estampilla', 'nivel' => 36],
            'fileteado' => ['nombre' => 'Fileteado', 'nivel' => 37],
            'criollo' => ['nombre' => 'Criollo', 'nivel' => 39],
            'tarot' => ['nombre' => 'Tarot', 'nivel' => 40],
        ],
        'anotar' => [
            'fabrica' => ['nombre' => 'Fósforos', 'nivel' => 1],
            'palotes' => ['nombre' => 'Palotes con lápiz', 'nivel' => 7],
            'punto-cruz' => ['nombre' => 'Punto cruz', 'nivel' => 9],
            'tiza' => ['nombre' => 'Tiza', 'nivel' => 13],
            'porotos' => ['nombre' => 'Porotos y garbanzos', 'nivel' => 27],
        ],
        'distintivo' => [
            'un-fosforo' => ['nombre' => 'Un fósforo', 'nivel' => 2],
            'cuadrado' => ['nombre' => 'El cuadrado', 'nivel' => 6],
            'grupo-de-cinco' => ['nombre' => 'Grupo de cinco', 'nivel' => 11],
            'quince' => ['nombre' => 'El 15', 'nivel' => 15],
            'cruzados' => ['nombre' => 'Dos cruzados', 'nivel' => 18],
            'siete' => ['nombre' => 'El 7', 'nivel' => 21],
            'cuatro' => ['nombre' => 'El 4', 'nivel' => 24],
            'treinta' => ['nombre' => 'El 30', 'nivel' => 29],
            'treinta-y-tres' => ['nombre' => 'El 33', 'nivel' => 33],
            // Los cuatro palos son un solo premio del camino: al ganarlo se abren los cuatro y cada uno elige cuál lleva.
            'palo-oro' => ['nombre' => 'Oro', 'nivel' => 38, 'ficha' => 'cuatro-palos'],
            'palo-copa' => ['nombre' => 'Copa', 'nivel' => 38, 'ficha' => 'cuatro-palos'],
            'palo-espada' => ['nombre' => 'Espada', 'nivel' => 38, 'ficha' => 'cuatro-palos'],
            'palo-basto' => ['nombre' => 'Basto', 'nivel' => 38, 'ficha' => 'cuatro-palos'],
        ],
    ];

    /**
     * Los títulos que muestran los jugadores de ejemplo: el primero de esta lista que ganaron en sus partidas.
     */
    private const TITULOS_DE_EJEMPLO = ['truquero', 'tantero', 'florido', 'cantor'];

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

    /**
     * Las mesas, formas de anotar o distintivos como los ve ese jugador: cuáles tiene abiertos y cuál eligió.
     *
     * @return list<array{clave: string, nombre: string, nivel: int, abierto: bool, elegido: bool}>
     */
    public function piezas(string $tipo, ?Jugador $jugador): array
    {
        $nivel = $this->nivel($jugador);
        $todo = self::loTieneTodo($jugador);
        $elegida = $this->elegidaDe($tipo, $jugador);

        return array_map(fn (string $clave, array $pieza) => [
            'clave' => $clave,
            ...$pieza,
            'abierto' => $todo || $nivel >= $pieza['nivel'],
            'elegido' => $clave === $elegida,
        ], array_keys(self::POR_NIVEL[$tipo]), self::POR_NIVEL[$tipo]);
    }

    public function tiene(string $tipo, Jugador $jugador, string $clave): bool
    {
        $pieza = self::POR_NIVEL[$tipo][$clave] ?? null;

        return $pieza !== null && (self::loTieneTodo($jugador) || $this->nivel($jugador) >= $pieza['nivel']);
    }

    /**
     * Lo que ese jugador eligió de ese tipo, si todavía lo tiene. Si no, lo de fábrica (o null: sin distintivo).
     */
    public function elegidaDe(string $tipo, ?Jugador $jugador): ?string
    {
        $elegida = $jugador?->{$tipo};
        $fabrica = isset(self::POR_NIVEL[$tipo]['fabrica']) ? 'fabrica' : null;

        if ($elegida === null || $jugador === null || ! $this->tiene($tipo, $jugador, $elegida)) {
            return $fabrica;
        }

        return $elegida;
    }

    /**
     * La mesa o la forma de anotar de quien está mirando la página, una sola vez por pedido.
     */
    public static function propia(string $tipo): string
    {
        $pedido = request();
        $clave = "{$tipo}-propia";

        if (! $pedido->attributes->has($clave)) {
            $pedido->attributes->set($clave, (new self)->elegidaDe($tipo, $pedido->user()) ?? 'fabrica');
        }

        return $pedido->attributes->get($clave);
    }

    /**
     * Los títulos como los ve ese jugador: cuáles ganó, cuál eligió y cuánto le falta para cada uno.
     *
     * @return list<array{clave: string, nombre: string, femenino: string, pide: string, lleva: int, hace: int, abierto: bool, elegido: bool}>
     */
    public function titulos(?Jugador $jugador): array
    {
        $todo = self::loTieneTodo($jugador);
        $elegido = $this->tituloElegido($jugador);

        return array_map(fn (string $clave, array $titulo) => [
            'clave' => $clave,
            'nombre' => $titulo['nombre'],
            'femenino' => $titulo['femenino'],
            'pide' => $titulo['cuenta'] === null ? 'De entrada' : ($titulo['dice'] ?? $titulo['pide'].' '.Titulos::QUE_PIDE[$titulo['cuenta']]),
            'lleva' => $titulo['cuenta'] === null ? 0 : (int) ($jugador?->{$titulo['cuenta']} ?? 0),
            'hace' => $titulo['pide'],
            'abierto' => $todo || Titulos::gano($jugador, $clave),
            'elegido' => $clave === $elegido,
        ], array_keys(Titulos::TODOS), Titulos::TODOS);
    }

    public function tieneTitulo(Jugador $jugador, string $titulo): bool
    {
        return isset(Titulos::TODOS[$titulo]) && (self::loTieneTodo($jugador) || Titulos::gano($jugador, $titulo));
    }

    /**
     * El título que muestra ese jugador, ya en la forma que eligió. Un jugador de ejemplo muestra el mejor que ganó.
     */
    public function tituloDe(?Jugador $jugador): string
    {
        return Titulos::nombre($this->tituloElegido($jugador), (bool) ($jugador?->titulo_femenino ?? false));
    }

    public function tituloElegido(?Jugador $jugador): string
    {
        if ($jugador?->esDeEjemplo()) {
            foreach (self::TITULOS_DE_EJEMPLO as $titulo) {
                if (Titulos::gano($jugador, $titulo)) {
                    return $titulo;
                }
            }

            return Titulos::DE_FABRICA;
        }

        $elegido = $jugador?->titulo;

        return $elegido !== null && $jugador !== null && $this->tieneTitulo($jugador, $elegido) ? $elegido : Titulos::DE_FABRICA;
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
     * Lo único del perfil de un jugador que recibe otro: su apodo, su categoría, su carta, su título y su
     * distintivo. Su mesa, su dorso y su forma de anotar son para él: no salen de su pantalla.
     *
     * @return array{apodo: string, categoria: string, carta: string, titulo: string, distintivo: string|null}
     */
    public function aLaVista(Jugador $jugador): array
    {
        return [
            'apodo' => $jugador->apodo,
            'categoria' => $this->categoria($jugador)->nombre(),
            'carta' => $this->cartaDe($jugador),
            'titulo' => $this->tituloDe($jugador),
            'distintivo' => $this->elegidaDe('distintivo', $jugador),
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
