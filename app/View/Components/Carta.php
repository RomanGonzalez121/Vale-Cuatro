<?php

namespace App\View\Components;

use App\Juego\Mazos;
use App\View\MazoEnUso;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

/**
 * Un naipe del mazo propio de Vale Cuatro, dibujado en SVG.
 *
 * No hay 40 dibujos: hay un solo componente que arma la carta con el símbolo
 * del palo repetido en las posiciones que le tocan a cada número.
 */
class Carta extends Component
{
    public const PALOS = ['espada', 'basto', 'oro', 'copa'];

    public const NUMEROS = [1, 2, 3, 4, 5, 6, 7, 10, 11, 12];

    private const FIGURAS = [10 => 'sota', 11 => 'caballo', 12 => 'rey'];

    /**
     * Dónde va el palo que cada figura lleva en la mano, como [x, y, ancho, alto].
     * Es grande a propósito: el palo tiene que leerse con la carta en chico.
     */
    private const PALO_EN_MANO = [
        'sota' => [61, 34, 28, 42],
        'caballo' => [58, 12, 26, 39],
        'rey' => [62, 37, 27, 40.5],
    ];

    /**
     * En el Catalán: el rey y la sota lo alzan a la derecha; el caballero, a pie, a la izquierda.
     */
    private const PALO_EN_MANO_CATALAN = [
        'sota' => [57, 49, 20, 30],
        'caballo' => [19, 30, 18, 27],
        'rey' => [65, 26, 21, 31.5],
    ];

    /**
     * En el Billete el palo va en un medallón chico debajo del retrato.
     */
    private const PALO_EN_MANO_BILLETE = [
        'sota' => [45.5, 124.5, 9, 13.5],
        'caballo' => [45.5, 124.5, 9, 13.5],
        'rey' => [45.5, 124.5, 9, 13.5],
    ];

    /**
     * En el Afiche las figuras son de geometría y alzan el palo a la derecha, junto al borde.
     */
    private const PALO_EN_MANO_AFICHE = [
        'sota' => [79, 97, 14, 21],
        'caballo' => [68, 28, 15, 22.5],
        'rey' => [80, 36, 16, 24],
    ];

    /**
     * En el Fileteado el palo va debajo del óvalo del emblema.
     */
    private const PALO_EN_MANO_FILETE = [
        'sota' => [43, 123.5, 14, 21],
        'caballo' => [43, 123.5, 14, 21],
        'rey' => [43, 123.5, 14, 21],
    ];

    /**
     * En el Criollo cada figura alza su objeto de campo con la mano derecha.
     */
    private const PALO_EN_MANO_CRIOLLO = [
        'sota' => [65.5, 72, 14, 21],
        'caballo' => [65, 30, 14, 21],
        'rey' => [71, 37, 14, 21],
    ];

    /**
     * En el Tango el palo va arriba, a la derecha, junto al círculo del escenario.
     */
    private const PALO_EN_MANO_TANGO = [
        'sota' => [72, 28, 14, 21],
        'caballo' => [72, 28, 14, 21],
        'rey' => [74, 30, 13, 19.5],
    ];

    /**
     * En el Diario el palo va al final del epígrafe de la foto.
     */
    private const PALO_EN_MANO_DIARIO = [
        'sota' => [77, 113.5, 9, 13.5],
        'caballo' => [77, 113.5, 9, 13.5],
        'rey' => [77, 113.5, 9, 13.5],
    ];

    /**
     * En la Caja de fósforos el palo va arriba a la derecha del panel, fuera de la viñeta.
     */
    private const PALO_EN_MANO_ETIQUETA = [
        'sota' => [71, 25, 11, 16.5],
        'caballo' => [71, 25, 11, 16.5],
        'rey' => [71, 25, 11, 16.5],
    ];

    /**
     * En el Pizarrón: el mozo lo lleva en la bandeja, el caballito lo tiene arriba y el rey lo alza.
     */
    private const PALO_EN_MANO_TIZA = [
        'sota' => [74.5, 40, 14, 21],
        'caballo' => [17, 31, 14, 21],
        'rey' => [72, 40, 14, 21],
    ];

    /**
     * En el Tipográfico el palo va como un taco suelto a la derecha del clisé.
     */
    private const PALO_EN_MANO_CLISE = [
        'sota' => [72, 76, 13, 19.5],
        'caballo' => [70, 76, 13, 19.5],
        'rey' => [73, 76, 13, 19.5],
    ];

    /**
     * En la Estampilla: el cartero lo lleva en la cartera, el correo de posta lo alza y el rey lo tiene debajo del camafeo.
     */
    private const PALO_EN_MANO_ESTAMPILLA = [
        'sota' => [59.7, 87.5, 11, 16.5],
        'caballo' => [54.5, 30.5, 13, 19.5],
        'rey' => [43.5, 101, 13, 19.5],
    ];

    /**
     * En el Bordado el palo va bordado a un costado de la figura, como en un muestrario.
     */
    private const PALO_EN_MANO_BORDADO = [
        'sota' => [72, 56, 12, 18],
        'caballo' => [18, 32, 12, 18],
        'rey' => [72, 54, 12, 18],
    ];

    /**
     * En el Azulejo de subte: el canillita lo alza, en la calesita es la sortija arriba del caño y el rey lo tiene al lado.
     */
    private const PALO_EN_MANO_AZULEJO = [
        'sota' => [62, 43, 12, 18],
        'caballo' => [44, 39.5, 12, 18],
        'rey' => [64.5, 62, 11, 16.5],
    ];

    /**
     * En la Murga: el murguero lo alza con el bastón, junto al caballo va como un aplique suelto y el Rey Momo lo levanta.
     */
    private const PALO_EN_MANO_MURGA = [
        'sota' => [58, 39.5, 11, 16.5],
        'caballo' => [21, 46.5, 11, 16.5],
        'rey' => [64, 56.5, 11, 16.5],
    ];

    /**
     * En el Clásico cada figura alza el palo con la mano derecha, como en la baraja castellana.
     */
    private const PALO_EN_MANO_CLASICO = [
        'sota' => [60.5, 24, 16, 24],
        'caballo' => [55.6, 11, 16, 24],
        'rey' => [61.8, 30.5, 16, 24],
    ];

    /**
     * En el Tarot cada figura alza el palo, como las del tarot de Marsella.
     */
    private const PALO_EN_MANO_TAROT = [
        'sota' => [61, 33, 12, 18],
        'caballo' => [56, 24, 12, 18],
        'rey' => [63, 41, 12, 18],
    ];

    /**
     * En los Gatos cada gato alza el palo con la pata.
     */
    private const PALO_EN_MANO_GATOS = [
        'sota' => [60, 42, 11, 16.5],
        'caballo' => [56, 34, 11, 16.5],
        'rey' => [61, 52, 11, 16.5],
    ];

    /**
     * En el Retratos: el muchacho tiene el palo sobre la mesita, y el jinete y el señor del sillón lo alzan.
     */
    private const PALO_EN_MANO_RETRATO = [
        'sota' => [66, 62, 13, 19.5],
        'caballo' => [68, 42, 12, 18],
        'rey' => [63, 48, 12, 18],
    ];

    /**
     * Dónde van los palos de las cartas de número en las cartas de otro mundo, como [x desde, x hasta, y desde,
     * y hasta]: la disposición del arco se lleva a esa zona (en el Afiche, el papel a la derecha de la franja).
     */
    private const ZONAS = [
        'afiche' => [35, 91, 16, 140],
        'catalan' => [12, 88, 24, 134],
        'fileteado' => [14, 86, 30, 128],
        'criollo' => [14, 86, 30, 126],
        'tango' => [14, 86, 30, 128],
        'diario' => [14, 86, 33, 123],
        'caja-de-fosforos' => [17, 83, 29, 127],
        'pizarron' => [14, 86, 31, 127],
        'tipografico' => [16, 84, 44, 112],
        'estampilla' => [17, 83, 30, 126],
        'bordado' => [17, 83, 27, 129],
        'azulejo' => [16, 84, 35, 121],
        'murga' => [17, 83, 39, 119],
        'clasico' => [12, 88, 22, 134],
        'tarot' => [16, 84, 30, 126],
        'gatos' => [18, 82, 40, 128],
        'retratos' => [16, 84, 25, 112],
    ];

    /**
     * La pinta: los cortes en el marco que identifican al palo en la baraja
     * española. Oro sin cortes, copa uno, espada dos, basto tres.
     */
    private const CORTES = [
        'oro' => [],
        'copa' => [50],
        'espada' => [36, 64],
        'basto' => [30, 50, 70],
    ];

    /**
     * Ancho de cada símbolo y centro de cada uno, sobre un lienzo de 100 x 156.
     */
    private const DISPOSICION = [
        1 => ['ancho' => 58, 'centros' => [[50, 78]]],
        2 => ['ancho' => 32, 'centros' => [[50, 49], [50, 107]]],
        3 => ['ancho' => 26, 'centros' => [[50, 37], [50, 78], [50, 119]]],
        4 => ['ancho' => 28, 'centros' => [[33, 51], [67, 51], [33, 105], [67, 105]]],
        5 => ['ancho' => 25, 'centros' => [[31, 46], [69, 46], [50, 78], [31, 110], [69, 110]]],
        6 => ['ancho' => 23, 'centros' => [[33, 40], [67, 40], [33, 78], [67, 78], [33, 116], [67, 116]]],
        7 => ['ancho' => 21, 'centros' => [[33, 40], [67, 40], [50, 59], [33, 78], [67, 78], [33, 116], [67, 116]]],
    ];

    /**
     * La misma disposición acomodada adentro del arco: un poco más chica y más abajo, para no tocar la curva.
     */
    private const DISPOSICION_EN_ARCO = [
        1 => ['ancho' => 44, 'centros' => [[50, 80]]],
        2 => ['ancho' => 26, 'centros' => [[50, 53], [50, 104]]],
        3 => ['ancho' => 21, 'centros' => [[50, 44], [50, 77], [50, 110]]],
        4 => ['ancho' => 24, 'centros' => [[35, 60], [65, 60], [35, 106], [65, 106]]],
        5 => ['ancho' => 22, 'centros' => [[34, 56], [66, 56], [50, 82], [34, 108], [66, 108]]],
        6 => ['ancho' => 20, 'centros' => [[36, 50], [64, 50], [36, 81], [64, 81], [36, 112], [64, 112]]],
        7 => ['ancho' => 19, 'centros' => [[36, 50], [64, 50], [50, 65.5], [36, 81], [64, 81], [36, 112], [64, 112]]],
    ];

    /**
     * El mazo con que se dibuja: el que se pida, o el de la página (el de quien mira en la mesa de juego, el de
     * fábrica en todas las demás).
     */
    public string $mazo;

    public function __construct(public string $palo, public int $numero, ?string $mazo = null)
    {

        if (! in_array($palo, self::PALOS, true)) {
            throw new InvalidArgumentException("El palo [{$palo}] no existe en la baraja española.");
        }

        if (! in_array($numero, self::NUMEROS, true)) {
            throw new InvalidArgumentException("El número [{$numero}] no existe en un mazo de 40 cartas.");
        }

        $this->mazo = $mazo ?? MazoEnUso::actual();
        MazoEnUso::anotar($this->mazo);
    }

    /**
     * Las 40 cartas del mazo, como pares [palo, número].
     *
     * @return list<array{0: string, 1: int}>
     */
    public static function mazo(): array
    {
        $cartas = [];

        foreach (self::PALOS as $palo) {
            foreach (self::NUMEROS as $numero) {
                $cartas[] = [$palo, $numero];
            }
        }

        return $cartas;
    }

    public function identificador(): string
    {
        return "{$this->numero}-{$this->palo}";
    }

    public function nombre(): string
    {
        return "{$this->numero} de {$this->palo}";
    }

    /**
     * El símbolo del palo y el de la figura en el sprite, según el juego de piezas del mazo.
     */
    public function simboloDelPalo(): string
    {
        return Mazos::simbolo("palo-{$this->palo}", Mazos::de($this->mazo)['palos']);
    }

    public function simboloDeLaFigura(): string
    {
        return Mazos::simbolo('figura-'.$this->figura(), Mazos::de($this->mazo)['figuras']);
    }

    /**
     * La tinta del mazo: la de fábrica no lleva nada; las otras cambian los colores de la carta (app.css).
     */
    public function tinta(): ?string
    {
        $tinta = Mazos::de($this->mazo)['tinta'];

        return $tinta === 'fabrica' ? null : "mazo-tinta-{$tinta}";
    }

    /**
     * Con qué carta se arma: la de fábrica o la del mundo de su mazo (resources/views/components/cartas).
     */
    public function estilo(): string
    {
        return Mazos::de($this->mazo)['carta'];
    }

    public function figura(): ?string
    {
        return self::FIGURAS[$this->numero] ?? null;
    }

    /**
     * Cada símbolo del palo como [x, y, ancho, alto], listo para un <use>.
     *
     * @return list<array{0: float, 1: float, 2: float, 3: float}>
     */
    public function pintas(): array
    {
        if ($this->figura() !== null) {
            $enMano = match (Mazos::de($this->mazo)['figuras']) {
                'retrato' => self::PALO_EN_MANO_RETRATO,
                'catalan-antigua' => self::PALO_EN_MANO_CATALAN,
                'billete' => self::PALO_EN_MANO_BILLETE,
                'afiche' => self::PALO_EN_MANO_AFICHE,
                'filete' => self::PALO_EN_MANO_FILETE,
                'criollo' => self::PALO_EN_MANO_CRIOLLO,
                'tango' => self::PALO_EN_MANO_TANGO,
                'diario' => self::PALO_EN_MANO_DIARIO,
                'etiqueta' => self::PALO_EN_MANO_ETIQUETA,
                'tiza' => self::PALO_EN_MANO_TIZA,
                'clise' => self::PALO_EN_MANO_CLISE,
                'estampilla' => self::PALO_EN_MANO_ESTAMPILLA,
                'bordado' => self::PALO_EN_MANO_BORDADO,
                'azulejo' => self::PALO_EN_MANO_AZULEJO,
                'murga' => self::PALO_EN_MANO_MURGA,
                'clasico' => self::PALO_EN_MANO_CLASICO,
                'tarot' => self::PALO_EN_MANO_TAROT,
                'gatos' => self::PALO_EN_MANO_GATOS,
                default => self::PALO_EN_MANO,
            };

            return [$enMano[$this->figura()]];
        }

        // En una carta de otro mundo (con medallones en las esquinas), los palos van más adentro.
        $adentro = $this->estilo() !== 'fabrica';
        $disposicion = ($adentro ? self::DISPOSICION_EN_ARCO : self::DISPOSICION)[$this->numero];
        $centros = $disposicion['centros'];
        $ancho = $disposicion['ancho'];

        // En una zona propia, la disposición del arco (16 a 84 de ancho, 24 a 128 de alto) se lleva a esa zona.
        if (isset(self::ZONAS[$this->estilo()])) {
            [$x0, $x1, $y0, $y1] = self::ZONAS[$this->estilo()];
            $ex = ($x1 - $x0) / 68;
            $ey = ($y1 - $y0) / 104;
            $centros = array_map(fn (array $c) => [$x0 + ($c[0] - 16) * $ex, $y0 + ($c[1] - 24) * $ey], $centros);
            $ancho *= min($ex, $ey);
        }

        $alto = $ancho * 1.5;

        return array_map(
            fn (array $centro) => [$centro[0] - $ancho / 2, $centro[1] - $alto / 2, $ancho, $alto],
            $centros,
        );
    }

    /**
     * El marco interior con los cortes del palo arriba y abajo.
     */
    public function marco(): string
    {
        $tramos = ['M9 9V147', 'M91 9V147'];

        foreach ([9, 147] as $y) {
            $desde = 9;

            foreach (self::CORTES[$this->palo] as $corte) {
                $tramos[] = "M{$desde} {$y}H".($corte - 3);
                $desde = $corte + 3;
            }

            $tramos[] = "M{$desde} {$y}H91";
        }

        return implode('', $tramos);
    }

    public function render(): View
    {
        return view('components.carta');
    }
}
