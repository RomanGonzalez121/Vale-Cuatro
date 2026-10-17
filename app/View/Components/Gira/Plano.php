<?php

namespace App\View\Components\Gira;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * El plano de un barrio de la gira: una página de guía de calles doblada en cuatro, con las manzanas, las
 * calles del barrio, el recorrido de los niveles y el bar del jefe al final. Hay dos: acostado (escritorio)
 * y parado (celular), con la misma idea y otra grilla.
 *
 * El recorrido es una línea por las calles; las paradas se reparten parejas sobre ella y el jefe espera al
 * final, en su bar. Lo hecho se dibuja como un fósforo hasta la parada que sigue; lo que falta, con puntos.
 */
class Plano extends Component
{
    /** @var array<string, mixed> */
    public array $g;

    /** @var list<array{numero: int, x: float, y: float, estado: string, fosforos: int, jefe: bool, titulo: string}> */
    public array $paradas = [];

    /** Hasta dónde llega lo hecho, como lista de puntos. */
    public string $hecho = '';

    /** Lo que falta, como lista de puntos. */
    public string $falta = '';

    /** La parada que sigue (la primera abierta sin pasar), o null si el barrio está completo o cerrado. */
    public ?int $sigue = null;

    /**
     * @param  array<string, mixed>  $barrio  Un barrio como lo da Gira::de(): con sus niveles y su estado.
     */
    public function __construct(public array $barrio, public string $orientacion = 'acostado', public ?string $carta = null)
    {
        $this->g = self::GEOMETRIA[$orientacion];
        $niveles = $barrio['niveles'];
        $cuantos = count($niveles);
        $camino = $this->g['camino'];
        $largo = self::largo($camino);

        foreach ($niveles as $i => $nivel) {
            $jefe = $i === $cuantos - 1;
            [$x, $y] = $jefe ? end($camino) : self::puntoA($camino, $largo * $i / ($cuantos - 1));
            $estado = match (true) {
                $nivel['pasado'] => 'pasada',
                $nivel['abierto'] => 'sigue',
                default => 'cerrada',
            };
            $this->paradas[] = ['numero' => $nivel['numero'], 'x' => $x, 'y' => $y, 'estado' => $estado, 'fosforos' => $nivel['fosforos'], 'jefe' => $jefe, 'titulo' => $nivel['titulo']];

            if ($estado === 'sigue' && $this->sigue === null) {
                $this->sigue = $nivel['numero'];
            }
        }

        // Lo hecho llega hasta la parada que sigue; con el barrio completo, hasta el bar.
        $hasta = match (true) {
            $barrio['completo'] => $largo,
            $this->sigue === null => 0.0,
            default => $this->sigue === $cuantos ? $largo : $largo * ($this->sigue - 1) / ($cuantos - 1),
        };
        [$hecho, $falta] = self::cortar($camino, $hasta);
        $this->hecho = self::puntos($hecho);
        $this->falta = self::puntos($falta);
    }

    public function render(): View
    {
        return view('components.gira.plano');
    }

    /**
     * Las dos grillas. Las calles se nombran por su eje; el camino va por el medio de las calles.
     */
    public const GEOMETRIA = [
        'acostado' => [
            'ancho' => 640, 'alto' => 448, 'marco' => [24, 24, 592, 392],
            'columnas' => [[40, 110], [172, 110], [304, 110], [436, 110]],
            'filas' => [[40, 90], [152, 90], [264, 90]],
            'verticales' => [161, 293, 425], 'horizontales' => [141, 253], 'calle' => 22,
            'avenida' => 379, 'costado' => 571, 'abajo' => [398, 18], 'bordes' => [[24, 12], [590, 26]],
            'camino' => [[60, 379], [161, 379], [161, 253], [293, 253], [293, 141], [425, 141], [492, 141]],
            'bar' => [440, 64, 96, 60], 'norte' => [603, 52], 'letras' => ['A', 'B', 'C', 'D'], 'numeros' => 3,
            'lugares' => ['mercado' => [58, 70], 'iglesia' => [332, 70]],
        ],
        'parado' => [
            'ancho' => 400, 'alto' => 580, 'marco' => [20, 20, 360, 530],
            'columnas' => [[34, 92], [144, 92], [254, 92]],
            'filas' => [[34, 80], [132, 80], [230, 80], [328, 80]],
            'verticales' => [135, 245], 'horizontales' => [123, 221, 319], 'calle' => 18,
            'avenida' => 424, 'costado' => 368, 'abajo' => [448, 84], 'bordes' => [[20, 8]],
            'camino' => [[60, 424], [135, 424], [135, 319], [245, 319], [245, 221], [245, 123], [298, 123]],
            'bar' => [256, 54, 88, 56], 'norte' => [368, 46], 'letras' => ['A', 'B', 'C'], 'numeros' => 5,
            'lugares' => ['mercado' => [43, 52], 'iglesia' => [162, 54]],
        ],
    ];

    /**
     * @param  list<array{0: float, 1: float}>  $camino
     */
    private static function largo(array $camino): float
    {
        $largo = 0.0;

        for ($i = 1; $i < count($camino); $i++) {
            $largo += hypot($camino[$i][0] - $camino[$i - 1][0], $camino[$i][1] - $camino[$i - 1][1]);
        }

        return $largo;
    }

    /**
     * El punto que queda a esa distancia del principio, siguiendo el camino.
     *
     * @param  list<array{0: float, 1: float}>  $camino
     * @return array{0: float, 1: float}
     */
    private static function puntoA(array $camino, float $distancia): array
    {
        return end(self::cortar($camino, $distancia)[0]);
    }

    /**
     * El camino partido en dos a esa distancia: lo de antes y lo de después (los dos comparten el punto del corte).
     *
     * @param  list<array{0: float, 1: float}>  $camino
     * @return array{0: list<array{0: float, 1: float}>, 1: list<array{0: float, 1: float}>}
     */
    private static function cortar(array $camino, float $distancia): array
    {
        $antes = [$camino[0]];

        for ($i = 1; $i < count($camino); $i++) {
            [$x0, $y0] = $camino[$i - 1];
            [$x1, $y1] = $camino[$i];
            $tramo = hypot($x1 - $x0, $y1 - $y0);

            if ($distancia <= $tramo) {
                $t = $tramo === 0.0 ? 0 : $distancia / $tramo;
                $corte = [round($x0 + ($x1 - $x0) * $t, 1), round($y0 + ($y1 - $y0) * $t, 1)];

                return [[...$antes, $corte], [$corte, ...array_slice($camino, $i)]];
            }

            $distancia -= $tramo;
            $antes[] = $camino[$i];
        }

        return [$antes, [end($camino)]];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $puntos
     */
    private static function puntos(array $puntos): string
    {
        return implode(' ', array_map(fn (array $p) => "{$p[0]},{$p[1]}", $puntos));
    }
}
