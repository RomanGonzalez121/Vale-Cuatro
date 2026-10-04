<?php

namespace App\View\Components;

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

    public function __construct(public string $palo, public int $numero)
    {
        if (! in_array($palo, self::PALOS, true)) {
            throw new InvalidArgumentException("El palo [{$palo}] no existe en la baraja española.");
        }

        if (! in_array($numero, self::NUMEROS, true)) {
            throw new InvalidArgumentException("El número [{$numero}] no existe en un mazo de 40 cartas.");
        }
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
            return [[69, 16, 16, 24]];
        }

        $ancho = self::DISPOSICION[$this->numero]['ancho'];
        $alto = $ancho * 1.5;

        return array_map(
            fn (array $centro) => [$centro[0] - $ancho / 2, $centro[1] - $alto / 2, $ancho, $alto],
            self::DISPOSICION[$this->numero]['centros'],
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
