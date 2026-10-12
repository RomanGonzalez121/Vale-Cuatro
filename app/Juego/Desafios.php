<?php

namespace App\Juego;

use App\Motor\Accion;
use App\Motor\Azar;
use App\Motor\Carta;
use App\Motor\Partida as Motor;

/**
 * Los desafíos: manos armadas con un objetivo.
 *
 * Cada uno es una ficha de datos (fichas-de-desafios.php): de dónde arranca (el tanteo, quién es mano,
 * las cartas de los dos y, si hace falta, lo que ya se jugó) y qué hay que lograr. Agregar uno es
 * agregar una ficha: no lleva código propio. El objetivo se escribe con las condiciones de Objetivos.
 *
 * Los sueltos se juegan en cualquier orden desde su pantalla. Los de la escalera son manos que
 * ocupan un escalón (App\Juego\Escalera) y no aparecen en esa lista.
 *
 * En un desafío el bot no juega al azar: con las mismas jugadas contesta siempre lo mismo. Así cada
 * desafío tiene una solución que se puede buscar, y no depende de la suerte de un intento.
 */
final class Desafios
{
    /** A cuántos puntos se juega la partida de la que sale la mano: los de siempre. */
    public const PUNTOS = 30;

    /**
     * Todos, sueltos y de la escalera.
     *
     * @return list<array<string, mixed>>
     */
    public static function todos(): array
    {
        return require __DIR__.'/fichas-de-desafios.php';
    }

    /**
     * Los que se juegan sueltos, en el orden en que se muestran.
     *
     * @return list<array<string, mixed>>
     */
    public static function sueltos(): array
    {
        return array_values(array_filter(self::todos(), fn (array $ficha) => $ficha['suelto']));
    }

    /**
     * El desafío con esa clave, suelto o de la escalera, o null si no existe.
     *
     * @return array<string, mixed>|null
     */
    public static function de(string $clave): ?array
    {
        foreach (self::todos() as $ficha) {
            if ($ficha['clave'] === $clave) {
                return $ficha;
            }
        }

        return null;
    }

    /**
     * Las cartas de cada asiento, como las espera el motor: primero las del jugador y después las del bot.
     *
     * @param  array<string, mixed>  $desafio
     * @return list<list<string>>
     */
    public static function manos(array $desafio): array
    {
        return [$desafio['cartas']['vos'], $desafio['cartas']['bot']];
    }

    /**
     * El asiento que es mano.
     *
     * @param  array<string, mixed>  $desafio
     */
    public static function mano(array $desafio): int
    {
        return self::asiento($desafio['mano']);
    }

    /**
     * El tanteo de arranque, como lo espera el motor.
     *
     * @param  array<string, mixed>  $desafio
     * @return array{0: int, 1: int}
     */
    public static function tanteo(array $desafio): array
    {
        return [$desafio['tanteo']['vos'], $desafio['tanteo']['bot']];
    }

    /**
     * Lo que ya está jugado cuando el desafío arranca, en orden: quién y qué.
     *
     * @param  array<string, mixed>  $desafio
     * @return list<array{0: int, 1: Accion}>
     */
    public static function jugadas(array $desafio): array
    {
        return array_map(
            fn (array $jugada) => [self::asiento($jugada[0]), self::accion($jugada[1])],
            $desafio['jugadas'] ?? [],
        );
    }

    /**
     * La mano del desafío puesta en el motor, todavía sin lo que ya venía jugado.
     *
     * @param  array<string, mixed>  $desafio
     */
    public static function motor(array $desafio): Motor
    {
        return Motor::armada(
            array_map(fn (array $mano) => array_map(Carta::de(...), $mano), self::manos($desafio)),
            self::mano($desafio),
            self::tanteo($desafio),
            self::PUNTOS,
        );
    }

    /**
     * El azar del bot para la jugada que va a quedar guardada con ese número, en ese desafío. Sale de la
     * clave y del número: la misma partida, jugada igual, recibe siempre las mismas respuestas.
     */
    public static function azar(string $clave, int $evento): Azar
    {
        return Azar::deSemilla((crc32($clave) + $evento) % 0x7FFFFFFF);
    }

    private static function asiento(string $quien): int
    {
        return $quien === 'vos' ? Mesa::JUGADOR : Mesa::BOT;
    }

    /**
     * Una jugada escrita en la ficha: una carta ("7-espada") o un canto ("truco", "no_quiero").
     */
    private static function accion(string $jugada): Accion
    {
        return str_contains($jugada, '-') ? Accion::jugar(Carta::de($jugada)) : Accion::desdeArray(['tipo' => $jugada]);
    }
}
