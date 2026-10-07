<?php

namespace App\Juego;

use App\Motor\Carta;

/**
 * El bot Ultra difícil: juega como el Difícil y además lee al rival.
 *
 * Tampoco ve las cartas del otro. Hereda las decisiones del Difícil y cambia de
 * dónde salen sus números, con lo que cualquier jugador atento tiene a la vista:
 *
 * - Los tantos que se dijeron (Deducciones). Si el rival dijo "28", tiene dos
 *   cartas del mismo palo que suman 8; si dijo "son buenas", su tanto no supera
 *   el propio. Las manos que no cierran con eso se descartan al calcular la mano.
 * - Lo que no se cantó. Si el rival ya pudo cantar envido y no lo hizo, casi
 *   seguro tiene poco tanto: el envido se canta con menos.
 * - Cómo viene jugando el rival en la partida (Perfil), con las manos anteriores
 *   que la mesa le recuerda antes de cada jugada: cuánto miente el envido, cuánto
 *   se calla teniendo, si tiene cuando canta truco y cuánto se va cuando le cantan.
 * - El tanteo: ganando por mucho se cuida, perdiendo por mucho arriesga.
 */
final class BotUltraDificil extends BotDificil implements Recuerda
{
    /** Desde cuántos puntos de diferencia cambia cuánto arriesga, y cuánto corre sus números. */
    private const VENTAJA = 6;

    private const SE_CUIDA = 0.05;

    /** Miente más seguido contra un rival que se va mucho, y casi nunca contra uno que quiere todo. */
    private const SE_VA_MUCHO = 0.55;

    private const QUIERE_TODO = 0.25;

    /** Una de cada tantas veces: contra el que se va mucho, contra uno cualquiera y contra el que quiere todo. */
    private const MIENTE_CADA = [3, 6, 10];

    private ?Perfil $perfil = null;

    /** @var list<array<string, mixed>> */
    private array $manos = [];

    /** @var array<string, mixed>|null La vista con la que se armaron las manos posibles: en una jugada se piden varias veces. */
    private ?array $vistaLeida = null;

    /** @var list<list<Carta>>|null */
    private ?array $posibles = null;

    public function recordar(array $manos): void
    {
        $this->manos = $manos;
        $this->perfil = null;
    }

    protected function ganaElEnvido(Lectura $lectura): float
    {
        $rival = $this->rival();

        return Probabilidades::deGanarElEnvido(
            $lectura,
            Deducciones::seCalloElEnvido($lectura) ? $rival->seCallaTeniendo : null,
            $rival->mienteElEnvido,
        );
    }

    protected function ganaLaMano(Lectura $lectura, ?Carta $jugando = null): float
    {
        if ($this->vistaLeida !== $lectura->vista) {
            $this->vistaLeida = $lectura->vista;
            $this->posibles = Deducciones::manosPosibles($lectura);
        }

        return Probabilidades::deGanarLaMano($lectura, $jugando, $this->posibles);
    }

    /**
     * Al truco de este rival se le cree según lo que mostró: si cantó sin tener, menos; si siempre tuvo, más.
     */
    protected function creyendole(float $gana, int $cantado): float
    {
        return Probabilidades::creyendole($gana, max(1.0, (1 + $cantado) * $this->rival()->creibleElTruco()));
    }

    /**
     * Ganando por mucho pide un poco más antes de cantar; perdiendo por mucho, un poco menos.
     */
    protected function cuidado(Lectura $lectura): float
    {
        $ventaja = $lectura->misPuntos() - $lectura->susPuntos();

        return match (true) {
            $ventaja >= self::VENTAJA => self::SE_CUIDA,
            $ventaja <= -self::VENTAJA => -self::SE_CUIDA,
            default => 0.0,
        };
    }

    /**
     * Mentir sirve contra quien se va. La frecuencia no es fija: sale de cuánto se fue este rival
     * cuando le cantaron, y se duplica si el tanteo apura.
     */
    protected function miente(Lectura $lectura): bool
    {
        $seVa = $this->rival()->seVa;

        $unaDe = self::MIENTE_CADA[match (true) {
            $seVa >= self::SE_VA_MUCHO => 0,
            $seVa <= self::QUIERE_TODO => 2,
            default => 1,
        }];

        return $this->unaDe($this->apurado($lectura) ? max(2, intdiv($unaDe, 2)) : $unaDe);
    }

    private function rival(): Perfil
    {
        return $this->perfil ??= Perfil::de($this->manos);
    }
}
