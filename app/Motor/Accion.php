<?php

namespace App\Motor;

use InvalidArgumentException;

/**
 * Una acción de un jugador: jugar una carta, cantar, contestar o irse al mazo.
 *
 * Se puede pasar a un arreglo y volver, que es como la va a guardar la partida (M3).
 */
final readonly class Accion
{
    private function __construct(public TipoDeAccion $tipo, public ?Carta $carta = null) {}

    public static function jugar(Carta $carta): self
    {
        return new self(TipoDeAccion::Jugar, $carta);
    }

    /**
     * Cualquier acción que no lleva carta: un canto, una respuesta o el mazo.
     */
    public static function de(TipoDeAccion $tipo): self
    {
        if ($tipo === TipoDeAccion::Jugar) {
            throw new InvalidArgumentException('Para jugar hay que decir qué carta.');
        }

        return new self($tipo);
    }

    /**
     * @param  array{tipo?: mixed, carta?: mixed}  $datos
     */
    public static function desdeArray(array $datos): self
    {
        $tipo = is_string($datos['tipo'] ?? null) ? TipoDeAccion::tryFrom($datos['tipo']) : null;

        if ($tipo === null) {
            throw new InvalidArgumentException('La acción no dice qué es, o dice algo que no existe.');
        }

        if ($tipo !== TipoDeAccion::Jugar) {
            return self::de($tipo);
        }

        if (! is_string($datos['carta'] ?? null)) {
            throw new InvalidArgumentException('Para jugar hay que decir qué carta.');
        }

        return self::jugar(Carta::de($datos['carta']));
    }

    /**
     * @return array{tipo: string, carta?: string}
     */
    public function aArray(): array
    {
        return $this->carta === null
            ? ['tipo' => $this->tipo->value]
            : ['tipo' => $this->tipo->value, 'carta' => $this->carta->id()];
    }
}
