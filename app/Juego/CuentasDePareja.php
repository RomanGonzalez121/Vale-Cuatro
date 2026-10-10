<?php

namespace App\Juego;

use App\Motor\Azar;
use App\Motor\Bazas;
use App\Motor\Carta;
use App\Motor\Mazo;

/**
 * Las cuentas que saca el bot Difícil en el truco de a cuatro: con qué probabilidad gana la mano su pareja.
 *
 * No ve las cartas de nadie, así que las imagina: reparte muchas veces, al azar, las cartas que todavía
 * no vio entre los otros tres (respetando lo que su compañero le dijo con las señas), juega cada reparto
 * hasta el final con un criterio simple para todos, y cuenta en cuántos gana su pareja.
 *
 * Con eso compara sus cartas (cuál conviene tirar) y decide si un truco se canta, se quiere o se sube.
 * Todo sale de la vista de su asiento y de las señas: lo mismo que sabría un jugador sentado ahí.
 *
 * Las cartas se manejan por su jerarquía (un número), que es lo único que importa para ganar una baza.
 */
final class CuentasDePareja
{
    /** Cuántos repartos imagina por cada decisión. Más da un número más firme y tarda más. */
    public const REPARTOS = 36;

    /** La jerarquía de cada carta del mazo, por su identificador. Se arma una sola vez. */
    private static ?array $jerarquias = null;

    public function __construct(private readonly Azar $azar) {}

    /**
     * La probabilidad de que su pareja gane la mano desde donde está, sin elegir carta: cada uno juega
     * con el criterio de siempre. Sirve para contestar un canto cuando no le toca tirar.
     *
     * @param  bool  $leeAlRival  Si los rivales cantaron truco, darles más peso a los repartos donde tienen con qué.
     */
    public function deGanarLaMano(LecturaDePareja $lectura, bool $leeAlRival = false): float
    {
        return $this->contar($lectura, [null], $leeAlRival)[0];
    }

    /**
     * La probabilidad de ganar la mano tirando ahora cada una de esas cartas. Todas se prueban contra los
     * mismos repartos imaginados: así la diferencia entre una y otra es de las cartas y no de la suerte.
     *
     * @param  list<int>  $jerarquias  Las cartas a comparar, por su jerarquía.
     * @return array<int, float> Por jerarquía, la probabilidad de ganar.
     */
    public function tirando(LecturaDePareja $lectura, array $jerarquias, bool $leeAlRival = false): array
    {
        return array_combine($jerarquias, $this->contar($lectura, $jerarquias, $leeAlRival));
    }

    /**
     * @param  list<int|null>  $opciones  Qué tirar en cada cuenta; null es "lo que tiraría cualquiera".
     * @return list<float>
     */
    private function contar(LecturaDePareja $lectura, array $opciones, bool $leeAlRival): array
    {
        $vista = $lectura->vista;
        $porCarta = self::jerarquias();
        $propias = array_map(fn (string $carta) => $porCarta[$carta], $vista['misCartas']);
        sort($propias);

        // Lo que se sabe de la mano y no cambia de un reparto a otro.
        $jugadas = [];
        $resultados = [];
        $enJuego = [];

        foreach ($vista['bazas'] as $baza) {
            foreach ($baza['jugadas'] as [$quien, $carta]) {
                $jugadas[$carta] = $quien;

                if (! $baza['cerrada']) {
                    $enJuego[] = [$quien, $porCarta[$carta]];
                }
            }

            if ($baza['cerrada']) {
                $resultados[] = $baza['ganador'] === null ? null : $baza['ganador'] % 2;
            }
        }

        $libres = array_values(array_diff(array_keys($porCarta), $vista['misCartas'], array_keys($jugadas)));
        $senaladas = $lectura->elCompaneroSigueEnLaMano() ? $this->loQueSenalo($lectura, $libres, $jugadas) : [];
        $libres = array_values(array_diff($libres, $senaladas));
        $otros = array_values(array_diff(range(0, $vista['asientos'] - 1), [$lectura->asiento]));
        $abre = $enJuego !== [] ? $enJuego[0][0] : ($vista['turno'] ?? $vista['mano']);

        $ganadas = array_fill(0, count($opciones), 0.0);
        $total = 0.0;

        for ($reparto = 0; $reparto < self::REPARTOS; $reparto++) {
            $manos = $this->imaginar($lectura, $otros, $libres, $senaladas, $porCarta);
            $manos[$lectura->asiento] = $propias;

            $peso = $leeAlRival ? $this->pesoDelReparto($lectura, $manos) : 1.0;
            $total += $peso;

            foreach ($opciones as $cual => $tira) {
                if ($this->jugarHastaElFinal($vista, $manos, $resultados, $enJuego, $abre, $lectura->asiento, $tira) === $lectura->equipo) {
                    $ganadas[$cual] += $peso;
                }
            }
        }

        return array_map(fn (float $suma) => $total > 0 ? $suma / $total : 0.5, $ganadas);
    }

    /**
     * Un reparto posible de las cartas que no vio: a cada uno de los otros tres, tantas como le quedan
     * en la mano. A su compañero le tocan primero las que le señaló y todavía no jugó.
     *
     * @param  list<int>  $otros
     * @param  list<string>  $libres
     * @param  list<string>  $senaladas
     * @param  array<string, int>  $porCarta
     * @return array<int, list<int>> Por asiento, la jerarquía de cada carta que le queda, de la más baja a la más alta.
     */
    private function imaginar(LecturaDePareja $lectura, array $otros, array $libres, array $senaladas, array $porCarta): array
    {
        $vista = $lectura->vista;
        $manos = [];
        $quedan = count($libres);

        foreach ($otros as $asiento) {
            $mano = [];
            $leQuedan = in_array($asiento, $vista['enMazo'], true) ? 0 : $vista['cartasEnMano'][$asiento];

            if ($asiento === $lectura->companero) {
                foreach (array_slice($senaladas, 0, $leQuedan) as $carta) {
                    $mano[] = $porCarta[$carta];
                }
            }

            // Se sacan al azar de las libres, sin mezclar todo el mazo: la elegida pasa al final y no vuelve a salir.
            while (count($mano) < $leQuedan && $quedan > 0) {
                $cual = $this->azar->entero(0, $quedan - 1);
                $mano[] = $porCarta[$libres[$cual]];
                $quedan--;
                [$libres[$cual], $libres[$quedan]] = [$libres[$quedan], $libres[$cual]];
            }

            sort($mano);
            $manos[$asiento] = $mano;
        }

        return $manos;
    }

    /**
     * Las cartas que su compañero le dijo que tiene y que todavía están sin jugar. Para una seña que no
     * dice el palo ("un tres") elige una de las que quedan; si el compañero ya jugó una así, la seña ya se cumplió.
     *
     * @param  list<string>  $libres
     * @param  array<string, int>  $jugadas  Cada carta jugada y quién la tiró.
     * @return list<string>
     */
    private function loQueSenalo(LecturaDePareja $lectura, array $libres, array $jugadas): array
    {
        $porCarta = self::jerarquias();
        $cartas = [];

        foreach ($lectura->senas as $sena) {
            $datos = Senas::todas()[$sena] ?? null;

            if ($datos === null || $datos['fuerza'] === 0) {
                continue;
            }

            $sirve = fn (string $carta) => $datos['carta'] !== null ? $carta === $datos['carta'] : $porCarta[$carta] === $datos['fuerza'];

            // Si ya tiró una carta así, no hace falta darle otra.
            foreach ($jugadas as $carta => $quien) {
                if ($quien === $lectura->companero && $sirve((string) $carta)) {
                    continue 2;
                }
            }

            foreach ($libres as $carta) {
                if ($sirve($carta) && ! in_array($carta, $cartas, true)) {
                    $cartas[] = $carta;

                    break;
                }
            }
        }

        return $cartas;
    }

    /**
     * Cuánto pesa un reparto imaginado cuando los rivales cantaron truco: quien canta suele tener con qué,
     * así que los repartos donde los rivales tienen cartas altas cuentan más.
     *
     * @param  array<int, list<int>>  $manos
     */
    private function pesoDelReparto(LecturaDePareja $lectura, array $manos): float
    {
        $truco = $lectura->vista['truco'];

        if ($truco['nivel'] === 0 || $truco['canto'] === $lectura->equipo) {
            return 1.0;
        }

        $altas = 0;

        foreach ($manos as $asiento => $mano) {
            if ($asiento % 2 !== $lectura->equipo) {
                foreach ($mano as $carta) {
                    $altas += (int) ($carta >= 10);
                }
            }
        }

        return 0.4 + $altas;
    }

    /**
     * Juega la mano hasta el final con las cartas imaginadas y devuelve el equipo que la gana.
     *
     * Todos juegan con el mismo criterio: quien abre sale con la más alta; quien sigue no le pisa la carta
     * a su compañero, y si va perdiendo gana con la más baja que le alcance o tira la más baja.
     *
     * @param  array<string, mixed>  $vista
     * @param  array<int, list<int>>  $manos  De la más baja a la más alta.
     * @param  list<int|null>  $resultados  El equipo que ganó cada baza cerrada.
     * @param  list<array{0: int, 1: int}>  $enJuego  Lo que ya cayó en la baza abierta.
     */
    private function jugarHastaElFinal(array $vista, array $manos, array $resultados, array $enJuego, int $abre, int $yo, ?int $tira): int
    {
        $asientos = $vista['asientos'];
        $equipoMano = $vista['mano'] % 2;

        // Tres bazas como mucho; el tope es por si algo estuviera mal y no cerrara nunca.
        for ($vueltas = 0; $vueltas < 4; $vueltas++) {
            $ganador = Bazas::ganadorDeMano($resultados, $equipoMano);

            if ($ganador !== null || count($resultados) >= 3) {
                return $ganador ?? $equipoMano;
            }

            $yaJugaron = array_column($enJuego, 0);

            for ($paso = 0; $paso < $asientos; $paso++) {
                $asiento = ($abre + $paso) % $asientos;

                if ($manos[$asiento] === [] || in_array($asiento, $yaJugaron, true)) {
                    continue;
                }

                $carta = $asiento === $yo && $tira !== null && in_array($tira, $manos[$asiento], true)
                    ? $tira
                    : $this->laQueTiraria($asiento, $manos[$asiento], $enJuego);

                // Lo que se pidió probar se prueba una sola vez: después juega como cualquiera.
                if ($asiento === $yo) {
                    $tira = null;
                }

                unset($manos[$asiento][array_search($carta, $manos[$asiento], true)]);
                $manos[$asiento] = array_values($manos[$asiento]);
                $enJuego[] = [$asiento, $carta];
            }

            if ($enJuego === []) {
                // Nadie tenía cartas: no queda nada por jugar.
                return Bazas::ganadorDeMano([...$resultados, null, null, null], $equipoMano) ?? $equipoMano;
            }

            $gana = $this->quienGana($enJuego);
            $resultados[] = $gana === null ? null : $gana % 2;
            // Abre la siguiente quien ganó; después de una parda, el mano.
            $abre = $gana ?? $vista['mano'];
            $enJuego = [];
        }

        return Bazas::ganadorDeMano($resultados, $equipoMano) ?? $equipoMano;
    }

    /**
     * @param  list<int>  $mano  De la más baja a la más alta.
     * @param  list<array{0: int, 1: int}>  $enJuego
     */
    private function laQueTiraria(int $asiento, array $mano, array $enJuego): int
    {
        if ($enJuego === []) {
            return $mano[count($mano) - 1];
        }

        $gana = $this->quienGana($enJuego);

        if ($gana !== null && $gana % 2 === $asiento % 2) {
            return $mano[0];
        }

        $masAlta = max(array_column($enJuego, 1));

        foreach ($mano as $carta) {
            if ($carta > $masAlta) {
                return $carta;
            }
        }

        return $mano[0];
    }

    /**
     * El asiento que va ganando esas cartas, o null si las más altas empatan entre un equipo y el otro.
     *
     * @param  list<array{0: int, 1: int}>  $enJuego
     */
    private function quienGana(array $enJuego): ?int
    {
        $masAlta = -1;
        $puntero = null;
        $empatan = false;

        foreach ($enJuego as [$asiento, $carta]) {
            if ($carta > $masAlta) {
                [$masAlta, $puntero, $empatan] = [$carta, $asiento, false];
            } elseif ($carta === $masAlta && $asiento % 2 !== $puntero % 2) {
                $empatan = true;
            }
        }

        return $empatan ? null : $puntero;
    }

    /**
     * @return array<string, int>
     */
    private static function jerarquias(): array
    {
        if (self::$jerarquias === null) {
            self::$jerarquias = [];

            foreach (Mazo::completo() as $carta) {
                self::$jerarquias[$carta->id()] = $carta->jerarquia();
            }
        }

        return self::$jerarquias;
    }
}
