<?php

namespace App\Motor;

use InvalidArgumentException;

/**
 * Una carta para el reglamento: sabe cuánto vale en el truco y en el envido.
 *
 * No sabe dibujarse. De eso se ocupa el componente de la vista, que usa el
 * mismo identificador ("7-espada").
 */
final readonly class Carta
{
    public const NUMEROS = [1, 2, 3, 4, 5, 6, 7, 10, 11, 12];

    /**
     * Las cuatro cartas que valen más que cualquier 3.
     */
    private const BRAVAS = ['1-espada' => 14, '1-basto' => 13, '7-espada' => 12, '7-oro' => 11];

    /**
     * El resto vale por su número, sin importar el palo.
     */
    private const COMUNES = [3 => 10, 2 => 9, 1 => 8, 12 => 7, 11 => 6, 10 => 5, 7 => 4, 6 => 3, 5 => 2, 4 => 1];

    public function __construct(public Palo $palo, public int $numero)
    {
        if (! in_array($numero, self::NUMEROS, true)) {
            throw new InvalidArgumentException("El número [{$numero}] no existe en un mazo de 40 cartas.");
        }
    }

    /**
     * Arma la carta desde su identificador, por ejemplo "7-espada".
     */
    public static function de(string $id): self
    {
        $partes = explode('-', $id);
        $palo = Palo::tryFrom($partes[1] ?? '');

        if (count($partes) !== 2 || $palo === null || ! ctype_digit($partes[0])) {
            throw new InvalidArgumentException("[{$id}] no es una carta: se escribe como \"7-espada\".");
        }

        return new self($palo, (int) $partes[0]);
    }

    public function id(): string
    {
        return "{$this->numero}-{$this->palo->value}";
    }

    public function nombre(): string
    {
        return "{$this->numero} de {$this->palo->value}";
    }

    /**
     * Qué tan alta es en el truco: de 14 (el 1 de espada) a 1 (los 4).
     * Dos cartas con el mismo valor empatan.
     */
    public function jerarquia(): int
    {
        return self::BRAVAS[$this->id()] ?? self::COMUNES[$this->numero];
    }

    public function leGanaA(self $otra): bool
    {
        return $this->jerarquia() > $otra->jerarquia();
    }

    public function empataCon(self $otra): bool
    {
        return $this->jerarquia() === $otra->jerarquia();
    }

    /**
     * Lo que suma en el envido y en la flor: las figuras valen 0.
     */
    public function valorDeEnvido(): int
    {
        return $this->numero >= 10 ? 0 : $this->numero;
    }

    public function es(self $otra): bool
    {
        return $this->palo === $otra->palo && $this->numero === $otra->numero;
    }
}
