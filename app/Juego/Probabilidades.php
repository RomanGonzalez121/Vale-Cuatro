<?php

namespace App\Juego;

use App\Motor\Bazas;
use App\Motor\Carta;
use App\Motor\Envido;
use App\Motor\Mazo;

/**
 * Las cuentas del bot Difícil: qué tan probable es ganar el envido, la flor y la mano.
 *
 * Las tres salen de lo mismo. El bot no ve las cartas del rival, pero sabe cuáles
 * no vio: las 40 del mazo menos las suyas y las que el rival ya tiró. Recorre
 * todas las manos que el rival puede tener con esas cartas y cuenta en cuántas gana.
 */
final class Probabilidades
{
    /**
     * Con qué tanto se suele cantar cada envido. Si el rival lo cantó, las manos
     * que llegan a ese tanto pesan más que las demás: se le cree, pero no del todo.
     */
    private const SE_CANTA_CON = [Envido::ENVIDO => 27, Envido::REAL => 30, Envido::FALTA => 31];

    private const CASI_LLEGA = 0.5;

    private const ESTA_MINTIENDO = 0.15;

    /** @var array<string, bool> Manos ya resueltas: la misma situación no se juega dos veces. */
    private static array $resueltas = [];

    /**
     * La probabilidad de ganar el envido con el tanto propio.
     *
     * Con $siLoCallo se cuenta además lo que el rival no cantó: ya pudo cantar envido y no lo hizo,
     * así que las manos con las que se suele cantar pesan eso (menos de 1) y las demás, entero.
     * Con $siMiente se cambia cuánto pesan las manos sin tanto de un rival que cantó: es para quien
     * ya vio cuánto miente ese rival.
     */
    public static function deGanarElEnvido(Lectura $lectura, ?float $siLoCallo = null, ?float $siMiente = null): float
    {
        $mio = $lectura->tanto();
        $cantado = self::loQueCantoElRival($lectura);
        $piso = $cantado === null ? null : self::SE_CANTA_CON[$cantado];
        $gano = 0.0;
        $total = 0.0;

        foreach (self::tantosDelRival($lectura) as $suyo => $manos) {
            $peso = $manos * self::credito($suyo, $piso, $siMiente ?? self::ESTA_MINTIENDO);

            if ($cantado === null && $siLoCallo !== null && $suyo >= self::SE_CANTA_CON[Envido::ENVIDO]) {
                $peso *= $siLoCallo;
            }
            $total += $peso;

            // Con el mismo tanto gana el mano.
            if ($mio > $suyo || ($mio === $suyo && $lectura->soyMano())) {
                $gano += $peso;
            }
        }

        return $total > 0 ? $gano / $total : 0.0;
    }

    /**
     * La probabilidad de ganar una contraflor: el rival tiene flor seguro, así que
     * solo cuentan las manos de tres cartas del mismo palo.
     */
    public static function deGanarLaFlor(Lectura $lectura): float
    {
        $mia = $lectura->tantoDeFlor();
        $vistas = $lectura->jugadasDe($lectura->rival);
        $base = 20 + array_sum(array_map(fn (Carta $carta) => $carta->valorDeEnvido(), $vistas));
        $porPalo = [];

        foreach (self::sinVer($lectura) as $carta) {
            $porPalo[$carta->palo->value][] = $carta->valorDeEnvido();
        }

        $gano = 0;
        $total = 0;

        foreach ($porPalo as $palo => $valores) {
            // Si ya tiró una carta, su flor es de ese palo.
            if ($vistas !== [] && $vistas[0]->palo->value !== $palo) {
                continue;
            }

            foreach (self::sumas($valores, 3 - count($vistas)) as $suma) {
                $suya = $base + $suma;
                $total++;

                if ($mia > $suya || ($mia === $suya && $lectura->soyMano())) {
                    $gano++;
                }
            }
        }

        return $total > 0 ? $gano / $total : 0.0;
    }

    /**
     * La probabilidad de ganar la mano, jugando cada uno lo mejor que puede.
     *
     * Para cada mano posible del rival se juega lo que queda con las cartas a la
     * vista, eligiendo cada lado su mejor carta, y se anota quién gana. Con
     * $jugando se pregunta lo mismo después de tirar esa carta: así se comparan.
     *
     * Con $posibles se cuentan solo esas manos del rival y no todas las que se arman con
     * las cartas sin ver: es para quien ya descartó algunas por lo que el rival dijo.
     *
     * @param  list<list<Carta>>|null  $posibles  Las cartas que le pueden quedar al rival en la mano.
     */
    public static function deGanarLaMano(Lectura $lectura, ?Carta $jugando = null, ?array $posibles = null): float
    {
        $miaEnLaMesa = $lectura->enLaMesaDe($lectura->asiento);
        $suyaEnLaMesa = $lectura->enLaMesaDe($lectura->rival);
        $propias = array_values(array_unique($lectura->vivas()));

        // Para saber quién gana una baza solo importa si la carta del rival es más alta, igual o más baja
        // que cada una de las propias. Con tres cartas propias hay a lo sumo siete lugares donde puede caer,
        // así que las miles de manos del rival se agrupan en unas pocas decenas. Los lugares van de 1 a 7:
        // los impares son "entre dos cartas propias" y los pares "igual a una propia". El 0 queda para "no hay carta".
        $lugar = fn (int $jerarquia): int => 1 + 2 * count(array_filter($propias, fn (int $propia) => $propia < $jerarquia))
            + (int) in_array($jerarquia, $propias, true);

        $mias = array_map(fn (Carta $carta) => $lugar($carta->jerarquia()), $lectura->enMano());
        $enLaMesa = $miaEnLaMesa ?? $suyaEnLaMesa;
        $mesa = $enLaMesa === null ? 0 : $lugar($enLaMesa->jerarquia());

        // Si hay una carta apoyada, juega el otro. Si no, el que tiene el turno.
        $turno = match (true) {
            $miaEnLaMesa !== null => -1,
            $suyaEnLaMesa !== null => 1,
            default => $lectura->meTocaJugar() ? 1 : -1,
        };

        $porLugar = [];

        foreach (self::sinVer($lectura) as $carta) {
            $donde = $lugar($carta->jerarquia());
            $porLugar[$donde] = ($porLugar[$donde] ?? 0) + 1;
        }

        $cual = $jugando === null ? null : array_search($lugar($jugando->jerarquia()), $mias, true);
        $resultados = $lectura->resultados();
        $soyMano = $lectura->soyMano();
        $gano = 0;
        $total = 0;

        $manos = $posibles === null
            ? self::manosPorLugar($porLugar, $lectura->vista['cartasEnMano'][$lectura->rival])
            : self::agrupadasPorLugar($posibles, $lugar);

        foreach ($manos as [$suyas, $veces]) {
            $total += $veces;

            $ganada = $cual === null
                ? self::gano($mias, $suyas, $mesa, $resultados, $turno, $soyMano)
                : self::trasJugar($cual, $mias, $suyas, $mesa, $resultados, 1, $soyMano);

            if ($ganada) {
                $gano += $veces;
            }
        }

        return $total > 0 ? $gano / $total : 0.0;
    }

    /**
     * Le cree al que canta: si el rival fue el último en cantar o en subir, las manos en las que
     * viene ganando pesan $veces más que las otras. Con $veces = 2, un 50 % pasa a ser un 33 %.
     */
    public static function creyendole(float $probabilidad, float $veces): float
    {
        $pierde = $veces * (1 - $probabilidad);

        return $probabilidad + $pierde > 0 ? $probabilidad / ($probabilidad + $pierde) : 0.0;
    }

    /**
     * Todas las manos que le pueden quedar al rival: cada forma de elegir, entre las cartas que
     * el bot no vio, tantas como el rival tiene en la mano.
     *
     * @return list<list<Carta>>
     */
    public static function manosDelRival(Lectura $lectura): array
    {
        return self::elegir(self::sinVer($lectura), $lectura->vista['cartasEnMano'][$lectura->rival]);
    }

    /**
     * @param  list<Carta>  $cartas
     * @return list<list<Carta>>
     */
    private static function elegir(array $cartas, int $cuantas): array
    {
        if ($cuantas === 0) {
            return [[]];
        }

        $manos = [];

        foreach ($cartas as $i => $carta) {
            foreach (self::elegir(array_slice($cartas, $i + 1), $cuantas - 1) as $resto) {
                $manos[] = [$carta, ...$resto];
            }
        }

        return $manos;
    }

    /**
     * Las mismas manos, dichas como las entiende la cuenta de la mano: por el lugar de cada carta
     * frente a las propias, y cuántas manos de cartas reales hay detrás de cada grupo.
     *
     * @param  list<list<Carta>>  $manos
     * @param  callable(int): int  $lugar
     * @return list<array{0: list<int>, 1: int}>
     */
    private static function agrupadasPorLugar(array $manos, callable $lugar): array
    {
        $grupos = [];

        foreach ($manos as $mano) {
            $lugares = array_map(fn (Carta $carta) => $lugar($carta->jerarquia()), $mano);
            sort($lugares);
            $clave = implode(',', $lugares);

            $grupos[$clave] ??= [$lugares, 0];
            $grupos[$clave][1]++;
        }

        return array_values($grupos);
    }

    /**
     * Las cartas que el bot no vio: pueden estar en la mano del rival o en el mazo.
     *
     * @return list<Carta>
     */
    private static function sinVer(Lectura $lectura): array
    {
        $vistas = array_map(
            fn (Carta $carta) => $carta->id(),
            [...$lectura->tresCartas(), ...$lectura->jugadasDe($lectura->rival)],
        );

        return array_values(array_filter(Mazo::completo(), fn (Carta $carta) => ! in_array($carta->id(), $vistas, true)));
    }

    /**
     * El envido más alto que cantó el rival en esta mano, o null si no cantó ninguno.
     */
    private static function loQueCantoElRival(Lectura $lectura): ?string
    {
        $cadena = $lectura->vista['envido']['cadena'];
        $ultimo = count($cadena) - 1;

        // Los cantos se alternan: el último es de quien figura en "canto", el anterior del otro, y así.
        $delRival = $lectura->vista['envido']['canto'] === $lectura->rival ? $ultimo : $ultimo - 1;

        return $cadena[$delRival] ?? null;
    }

    /**
     * Cuánto pesa una mano del rival sabiendo lo que cantó.
     */
    private static function credito(int $tanto, ?int $piso, float $siMiente): float
    {
        return match (true) {
            $piso === null, $tanto >= $piso => 1.0,
            // Lo que casi llega nunca pesa menos que una mentira.
            $tanto >= $piso - 3 => max(self::CASI_LLEGA, $siMiente),
            default => $siMiente,
        };
    }

    /**
     * Cuántas manos posibles del rival dan cada tanto.
     *
     * La cuenta del tanto está escrita acá con números sueltos, y no con Tanto::deEnvido(), porque
     * se hace miles de veces por decisión. Un test compara las dos para que no se separen.
     *
     * @return array<int, int>
     */
    private static function tantosDelRival(Lectura $lectura): array
    {
        $palos = [];
        $valores = [];

        // Primero las que ya tiró, que son seguras; después las que no se vieron.
        $vistas = $lectura->jugadasDe($lectura->rival);

        foreach ([...$vistas, ...self::sinVer($lectura)] as $carta) {
            $palos[] = $carta->palo;
            $valores[] = $carta->valorDeEnvido();
        }

        $fijas = count($vistas);
        $cartas = count($palos);
        $cuentas = [];

        // Cada mano son tres posiciones en orden. Las primeras son las cartas que ya se vieron.
        for ($a = 0; $a < $cartas; $a++) {
            if ($fijas >= 1 && $a !== 0) {
                break;
            }

            for ($b = $a + 1; $b < $cartas; $b++) {
                if ($fijas >= 2 && $b !== 1) {
                    break;
                }

                for ($c = $b + 1; $c < $cartas; $c++) {
                    if ($fijas >= 3 && $c !== 2) {
                        break;
                    }

                    $tanto = max($valores[$a], $valores[$b], $valores[$c]);

                    if ($palos[$a] === $palos[$b]) {
                        $tanto = max($tanto, 20 + $valores[$a] + $valores[$b]);
                    }

                    if ($palos[$a] === $palos[$c]) {
                        $tanto = max($tanto, 20 + $valores[$a] + $valores[$c]);
                    }

                    if ($palos[$b] === $palos[$c]) {
                        $tanto = max($tanto, 20 + $valores[$b] + $valores[$c]);
                    }

                    $cuentas[$tanto] = ($cuentas[$tanto] ?? 0) + 1;
                }
            }
        }

        return $cuentas;
    }

    /**
     * Todas las sumas que se arman eligiendo esa cantidad de valores.
     *
     * @param  list<int>  $valores
     * @return list<int>
     */
    private static function sumas(array $valores, int $cuantos): array
    {
        if ($cuantos === 0) {
            return [0];
        }

        $sumas = [];

        foreach ($valores as $i => $valor) {
            foreach (self::sumas(array_slice($valores, $i + 1), $cuantos - 1) as $resto) {
                $sumas[] = $valor + $resto;
            }
        }

        return $sumas;
    }

    /**
     * Las manos que puede tener el rival, agrupadas por el lugar de cada carta, y cuántas manos
     * de cartas reales hay detrás de cada grupo.
     *
     * @param  array<int, int>  $porLugar  Cuántas cartas sin ver caen en cada lugar.
     * @return list<array{0: list<int>, 1: int}>
     */
    private static function manosPorLugar(array $porLugar, int $cartas): array
    {
        ksort($porLugar);
        $manos = [[[], 1]];

        foreach ($porLugar as $lugar => $hay) {
            $siguientes = [];

            foreach ($manos as [$armada, $veces]) {
                for ($cuantas = 0; $cuantas <= min($hay, $cartas - count($armada)); $cuantas++) {
                    $siguientes[] = [[...$armada, ...array_fill(0, $cuantas, $lugar)], $veces * self::combinaciones($hay, $cuantas)];
                }
            }

            $manos = $siguientes;
        }

        return array_values(array_filter($manos, fn (array $mano) => count($mano[0]) === $cartas));
    }

    private static function combinaciones(int $de, int $cuantas): int
    {
        $formas = 1;

        for ($i = 0; $i < $cuantas; $i++) {
            $formas = intdiv($formas * ($de - $i), $i + 1);
        }

        return $formas;
    }

    /**
     * Si gano la mano con las cartas de los dos a la vista y cada uno jugando lo mejor que puede.
     * El turno es 1 cuando juego yo y -1 cuando juega el rival. $mesa es la carta apoyada por el
     * otro en la baza en juego, o 0 si le toca salir.
     *
     * @param  list<int>  $mias
     * @param  list<int>  $suyas
     * @param  list<int>  $resultados
     */
    private static function gano(array $mias, array $suyas, int $mesa, array $resultados, int $turno, bool $soyMano): bool
    {
        $clave = implode(',', $mias).'|'.implode(',', $suyas)."|{$mesa}|".implode(',', $resultados)."|{$turno}|".(int) $soyMano;

        return self::$resueltas[$clave] ??= self::buscar($mias, $suyas, $mesa, $resultados, $turno, $soyMano);
    }

    /**
     * @param  list<int>  $mias
     * @param  list<int>  $suyas
     * @param  list<int>  $resultados
     */
    private static function buscar(array $mias, array $suyas, int $mesa, array $resultados, int $turno, bool $soyMano): bool
    {
        $juegoYo = $turno === 1;

        foreach (array_keys(array_unique($juegoYo ? $mias : $suyas)) as $cual) {
            // A mí me alcanza con una carta que gane; al rival, con una que me haga perder.
            if (self::trasJugar($cual, $mias, $suyas, $mesa, $resultados, $turno, $soyMano) === $juegoYo) {
                return $juegoYo;
            }
        }

        return ! $juegoYo;
    }

    /**
     * Lo mismo, después de que quien tiene el turno tira la carta que ocupa ese lugar en su mano.
     *
     * @param  list<int>  $mias
     * @param  list<int>  $suyas
     * @param  list<int>  $resultados
     */
    private static function trasJugar(int $cual, array $mias, array $suyas, int $mesa, array $resultados, int $turno, bool $soyMano): bool
    {
        if ($turno === 1) {
            $carta = $mias[$cual];
            unset($mias[$cual]);
            $mias = array_values($mias);
        } else {
            $carta = $suyas[$cual];
            unset($suyas[$cual]);
            $suyas = array_values($suyas);
        }

        // Sale: la carta queda apoyada y juega el otro.
        if ($mesa === 0) {
            return self::gano($mias, $suyas, $carta, $resultados, -$turno, $soyMano);
        }

        // Contesta: se cierra la baza.
        $baza = $turno === 1 ? $carta <=> $mesa : $mesa <=> $carta;
        $resultados[] = $baza;

        $ganaLaMano = Bazas::ganadorDeMano(
            array_map(fn (int $resultado) => [1 => 0, -1 => 1, 0 => null][$resultado], $resultados),
            $soyMano ? 0 : 1,
        );

        if ($ganaLaMano !== null) {
            return $ganaLaMano === 0;
        }

        // Abre la baza siguiente quien ganó esta; después de una parda, el mano.
        return self::gano($mias, $suyas, 0, $resultados, $baza !== 0 ? $baza : ($soyMano ? 1 : -1), $soyMano);
    }
}
