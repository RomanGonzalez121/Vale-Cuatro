<?php

namespace App\Juego;

use App\Motor\Accion;
use App\Motor\TipoDeAccion;
use InvalidArgumentException;

/**
 * Un jefe de la gira: la máquina de su nivel con una manía encima. Decide la máquina, y la manía corrige
 * esa decisión cuando le toca:
 *
 * - nunca_se_va: nunca se va al mazo. Si la máquina quería irse, juega una carta (o, contestando un canto, no quiere).
 * - canta_todo: en vez de tirar una carta, canta envido si puede y, si no, truco.
 * - miente_siempre: en vez de tirar una carta, canta truco si puede, tenga lo que tenga.
 * - quiere_todo: nunca dice "no quiero" ni se va contestando un canto: quiere.
 *
 * La manía solo elige entre las acciones que la vista trae como válidas: nunca hace trampa ni ve más que la
 * máquina. "Arranca con ventaja" no es de esta capa: es un dato de la partida (el tanteo con que empieza).
 */
final class BotConMania implements Bot, Recuerda
{
    /** Las manías que cambian cómo juega. */
    public const MANIAS = ['nunca_se_va', 'canta_todo', 'miente_siempre', 'quiere_todo'];

    public function __construct(private readonly Bot $bot, private readonly string $mania)
    {
        if (! in_array($mania, self::MANIAS, true)) {
            throw new InvalidArgumentException("No existe la manía «{$mania}».");
        }
    }

    public function recordar(array $manos): void
    {
        if ($this->bot instanceof Recuerda) {
            $this->bot->recordar($manos);
        }
    }

    public function decidir(array $vista): Accion
    {
        $accion = $this->bot->decidir($vista);
        $validas = array_map(fn (array $datos) => Accion::desdeArray($datos), $vista['acciones']);
        $puede = fn (TipoDeAccion $tipo) => array_filter($validas, fn (Accion $valida) => $valida->tipo === $tipo) !== [];
        $carta = array_values(array_filter($validas, fn (Accion $valida) => $valida->tipo === TipoDeAccion::Jugar))[0] ?? null;

        return match ($this->mania) {
            'nunca_se_va' => match (true) {
                $accion->tipo !== TipoDeAccion::Mazo => $accion,
                $carta !== null => $carta,
                $puede(TipoDeAccion::NoQuiero) => Accion::de(TipoDeAccion::NoQuiero),
                default => $accion,
            },
            'canta_todo' => match (true) {
                $accion->tipo !== TipoDeAccion::Jugar => $accion,
                $puede(TipoDeAccion::Envido) => Accion::de(TipoDeAccion::Envido),
                $puede(TipoDeAccion::Truco) => Accion::de(TipoDeAccion::Truco),
                default => $accion,
            },
            'miente_siempre' => $accion->tipo === TipoDeAccion::Jugar && $puede(TipoDeAccion::Truco) ? Accion::de(TipoDeAccion::Truco) : $accion,
            'quiere_todo' => in_array($accion->tipo, [TipoDeAccion::NoQuiero, TipoDeAccion::Mazo], true) && $puede(TipoDeAccion::Quiero)
                ? Accion::de(TipoDeAccion::Quiero)
                : $accion,
        };
    }
}
