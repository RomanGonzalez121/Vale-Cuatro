<?php

namespace App\Juego;

use App\Motor\Carta;
use App\Motor\Tanto;
use App\Motor\TipoDeAccion;

/**
 * Lo que un bot lee de su vista, dicho en palabras del juego.
 *
 * Es la misma vista que recibe un jugador: acá no hay ninguna carta del rival
 * que no esté jugada. Sirve para el mano a mano, con un asiento por lado.
 */
final class Lectura
{
    public readonly int $asiento;

    public readonly int $rival;

    /**
     * @param  array<string, mixed>  $vista  Lo que entrega Partida::vistaPara() para el asiento del bot.
     */
    public function __construct(public readonly array $vista)
    {
        $this->asiento = $vista['asiento'];
        $this->rival = 1 - $this->asiento;
    }

    public function puede(TipoDeAccion $tipo): bool
    {
        return in_array($tipo->value, array_column($this->vista['acciones'], 'tipo'), true);
    }

    /**
     * El canto que hay que contestar, o null si le toca jugar.
     */
    public function pendiente(): ?string
    {
        return $this->vista['pendiente']['canto'] ?? null;
    }

    /**
     * Las cartas que le quedan, de la más baja a la más alta.
     *
     * @return list<Carta>
     */
    public function enMano(): array
    {
        $cartas = array_map(Carta::de(...), $this->vista['misCartas']);
        usort($cartas, fn (Carta $una, Carta $otra) => $una->jerarquia() <=> $otra->jerarquia());

        return $cartas;
    }

    /**
     * Las cartas que un asiento ya tiró en esta mano, en orden.
     *
     * @return list<Carta>
     */
    public function jugadasDe(int $asiento): array
    {
        $cartas = [];

        foreach ($this->vista['bazas'] as $baza) {
            foreach ($baza['jugadas'] as [$quien, $carta]) {
                if ($quien === $asiento) {
                    $cartas[] = Carta::de($carta);
                }
            }
        }

        return $cartas;
    }

    /**
     * Las tres cartas que recibió: las que le quedan y las que ya jugó. Con ellas se calcula el tanto.
     *
     * @return list<Carta>
     */
    public function tresCartas(): array
    {
        return [...$this->enMano(), ...$this->jugadasDe($this->asiento)];
    }

    public function tanto(): int
    {
        return Tanto::deEnvido($this->tresCartas());
    }

    public function tantoDeFlor(): int
    {
        return Tanto::deFlor($this->tresCartas());
    }

    /**
     * La carta que un asiento tiene apoyada en la baza que se está jugando.
     */
    public function enLaMesaDe(int $asiento): ?Carta
    {
        $baza = $this->vista['bazas'][count($this->vista['bazas']) - 1] ?? null;

        if ($baza === null || $baza['cerrada']) {
            return null;
        }

        foreach ($baza['jugadas'] as [$quien, $carta]) {
            if ($quien === $asiento) {
                return Carta::de($carta);
            }
        }

        return null;
    }

    /**
     * Las cartas con las que todavía puede ganar una baza: las de la mano y la que ya tiró
     * en la baza en juego. Sin esa, con la mano vacía regalaría el truco.
     *
     * @return list<int> La jerarquía de cada una.
     */
    public function vivas(): array
    {
        $cartas = $this->enMano();

        if (($enLaMesa = $this->enLaMesaDe($this->asiento)) !== null) {
            $cartas[] = $enLaMesa;
        }

        return array_map(fn (Carta $carta) => $carta->jerarquia(), $cartas);
    }

    public function masAlta(): int
    {
        return max([0, ...$this->vivas()]);
    }

    /**
     * Cuántas de sus cartas vivas valen por lo menos eso: 11 es una brava, 9 es un 2.
     */
    public function cuantasDesde(int $jerarquia): int
    {
        return count(array_filter($this->vivas(), fn (int $viva) => $viva >= $jerarquia));
    }

    /**
     * Cómo le fue en cada baza cerrada: 1 la ganó, -1 la perdió y 0 fue parda.
     *
     * @return list<int>
     */
    public function resultados(): array
    {
        $resultados = [];

        foreach ($this->vista['bazas'] as $baza) {
            if ($baza['cerrada']) {
                $resultados[] = match ($baza['ganador']) {
                    null => 0,
                    $this->asiento => 1,
                    default => -1,
                };
            }
        }

        return $resultados;
    }

    public function bazasGanadas(): int
    {
        return count(array_filter($this->resultados(), fn (int $resultado) => $resultado === 1));
    }

    public function soyMano(): bool
    {
        return $this->vista['mano'] === $this->asiento;
    }

    public function meTocaJugar(): bool
    {
        return $this->vista['turno'] === $this->asiento;
    }

    public function misPuntos(): int
    {
        return $this->vista['tanteo'][$this->asiento];
    }

    public function susPuntos(): int
    {
        return $this->vista['tanteo'][$this->rival];
    }

    public function paraGanar(): int
    {
        return $this->vista['puntosParaGanar'];
    }

    /**
     * Lo que vale la mano con lo que ya se quiso: 1 sin truco, 2 con truco, 3 con retruco y 4 con vale cuatro.
     */
    public function valorDeLaMano(): int
    {
        return $this->vista['truco']['querido'] + 1;
    }
}
