<?php

namespace App\Juego;

use App\Motor\Carta;
use App\Motor\Tanto;

/**
 * Lo que se puede saber de las cartas del rival sin verlas, por lo que dijo y por lo que calló.
 *
 * Todo sale de la vista del asiento: es lo que deduciría un jugador atento sentado ahí.
 */
final class Deducciones
{
    /**
     * Las manos que le pueden quedar al rival sabiendo el tanto que dijo en el envido de esta mano,
     * o null si no dijo nada que sirva para descartar.
     *
     * Si dijo su número, sus tres cartas dan ese tanto exacto. Si dijo "son buenas", su tanto no
     * supera el que se había cantado antes.
     *
     * @return list<list<Carta>>|null
     */
    public static function manosPosibles(Lectura $lectura): ?array
    {
        $dicho = self::tantoDicho($lectura);

        if ($dicho === null) {
            return null;
        }

        [$exacto, $tanto] = $dicho;
        $jugadas = $lectura->jugadasDe($lectura->rival);

        $posibles = array_values(array_filter(
            Probabilidades::manosDelRival($lectura),
            function (array $resto) use ($jugadas, $exacto, $tanto) {
                $suyo = Tanto::deEnvido([...$jugadas, ...$resto]);

                return $exacto ? $suyo === $tanto : $suyo <= $tanto;
            },
        ));

        // La mano de verdad siempre cierra con lo que dijo. Si no quedara ninguna, no se descarta nada.
        return $posibles === [] ? null : $posibles;
    }

    /**
     * El rival ya tiró su primera carta sin cantar envido: tuvo su momento y lo dejó pasar.
     */
    public static function seCalloElEnvido(Lectura $lectura): bool
    {
        return $lectura->vista['envido']['cadena'] === [] && $lectura->jugadasDe($lectura->rival) !== [];
    }

    /**
     * Lo que el rival dejó saber de su tanto al cantarse los tantos del envido, o null si no dijo nada.
     *
     * @return array{0: bool, 1: int}|null Si el tanto es exacto (o un tope) y cuál es.
     */
    public static function tantoDicho(Lectura $lectura): ?array
    {
        $mejor = null;

        // Los tantos se cantan en orden desde el mano: lo que se dijo antes que el rival es su tope.
        foreach ($lectura->vista['envido']['tantos'] as $dicho) {
            if ($dicho['asiento'] === $lectura->rival) {
                return match (true) {
                    $dicho['tanto'] !== null => [true, $dicho['tanto']],
                    $mejor !== null => [false, $mejor],
                    default => null,
                };
            }

            $mejor = max($mejor ?? 0, $dicho['tanto'] ?? 0);
        }

        return null;
    }
}
