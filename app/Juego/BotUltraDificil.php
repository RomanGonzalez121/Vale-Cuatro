<?php

namespace App\Juego;

use App\Motor\Carta;

/**
 * El bot Ultra difícil: juega como el Difícil y además lee al rival.
 *
 * Tampoco ve las cartas del otro. Lo que suma sale de lo que cualquier jugador
 * atento tiene a la vista (Deducciones), y cambia las dos cuentas del Difícil:
 *
 * - Los tantos que se dijeron. Si el rival dijo "28", tiene dos cartas del mismo
 *   palo que suman 8; si dijo "son buenas", su tanto no supera el propio. Las
 *   manos que no cierran con eso se descartan al calcular quién gana la mano.
 * - Lo que no se cantó. Si el rival ya pudo cantar envido y no lo hizo, casi
 *   seguro tiene poco tanto: el envido se canta con menos.
 *
 * Las manos anteriores, que la mesa le recuerda antes de cada jugada, todavía
 * no se usan: con ellas va a aprender cómo juega el rival en esa partida.
 */
final class BotUltraDificil extends BotDificil implements Recuerda
{
    /** Cuánto pesa una mano con tanto para cantar cuando el rival pudo cantar envido y se calló. */
    private const SE_CALLO_TENIENDO = 0.25;

    /** @var list<array<string, mixed>> */
    private array $manos = [];

    /** @var array<string, mixed>|null La vista con la que se armaron las manos posibles: en una jugada se piden varias veces. */
    private ?array $vistaLeida = null;

    /** @var list<list<Carta>>|null */
    private ?array $posibles = null;

    public function recordar(array $manos): void
    {
        $this->manos = $manos;
    }

    protected function ganaElEnvido(Lectura $lectura): float
    {
        return Probabilidades::deGanarElEnvido($lectura, Deducciones::seCalloElEnvido($lectura) ? self::SE_CALLO_TENIENDO : null);
    }

    protected function ganaLaMano(Lectura $lectura, ?Carta $jugando = null): float
    {
        if ($this->vistaLeida !== $lectura->vista) {
            $this->vistaLeida = $lectura->vista;
            $this->posibles = Deducciones::manosPosibles($lectura);
        }

        return Probabilidades::deGanarLaMano($lectura, $jugando, $this->posibles);
    }
}
