<?php

namespace App\Juego;

use App\Motor\Carta;
use App\Motor\Mesa;
use App\Motor\Tanto;
use App\Motor\TipoDeAccion;

/**
 * Lo que un bot lee de su vista en el truco de a cuatro y en el gallito, dicho en palabras del juego.
 *
 * Es la misma vista que recibe un jugador en ese asiento, más las señas que le hizo su compañero:
 * acá no hay ninguna carta ajena que no esté jugada. De a cuatro los compañeros se sientan enfrentados.
 * En el gallito los equipos cambian en cada mano: el gallo (que es el mano) no tiene compañero, y los
 * otros dos son pareja.
 */
final class LecturaDePareja
{
    public readonly int $asiento;

    /** Los equipos de esta mano, como los arma el motor. */
    public readonly Mesa $mesa;

    public readonly int $equipo;

    /** Su compañero en esta mano, o null si es el gallo. */
    public readonly ?int $companero;

    /**
     * @param  array<string, mixed>  $vista  Lo que entrega Partida::vistaPara() para el asiento del bot.
     * @param  list<string>  $senas  Las señas que recibió de su compañero en esta mano.
     */
    public function __construct(public readonly array $vista, public readonly array $senas = [])
    {
        $this->asiento = $vista['asiento'];
        $this->mesa = (new Mesa($vista['asientos']))->conGallo($vista['mano']);
        $this->equipo = $this->mesa->equipoDe($this->asiento);
        $this->companero = array_values(array_diff($this->mesa->asientosDe($this->equipo), [$this->asiento]))[0] ?? null;
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

    public function esDeMiEquipo(int $asiento): bool
    {
        return $this->mesa->equipoDe($asiento) === $this->equipo;
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
     * Las tres cartas que recibió: las que le quedan y las que ya jugó. Con ellas se calcula el tanto.
     *
     * @return list<Carta>
     */
    public function tresCartas(): array
    {
        $cartas = $this->enMano();

        foreach ($this->vista['bazas'] as $baza) {
            foreach ($baza['jugadas'] as [$quien, $carta]) {
                if ($quien === $this->asiento) {
                    $cartas[] = Carta::de($carta);
                }
            }
        }

        return $cartas;
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
     * Las cartas apoyadas en la baza que se está jugando, en el orden en que cayeron.
     *
     * @return list<array{0: int, 1: Carta}> El asiento y su carta.
     */
    public function enLaMesa(): array
    {
        $baza = $this->vista['bazas'][count($this->vista['bazas']) - 1] ?? null;

        if ($baza === null || $baza['cerrada']) {
            return [];
        }

        return array_map(fn (array $jugada) => [$jugada[0], Carta::de($jugada[1])], $baza['jugadas']);
    }

    /**
     * Quién va ganando la baza en juego: el asiento de la carta más alta, o null si no cayó ninguna
     * o si las dos más altas empatan entre un equipo y el otro.
     */
    public function vaGanando(): ?int
    {
        $mejor = null;
        $empatan = false;

        foreach ($this->enLaMesa() as [$asiento, $carta]) {
            if ($mejor === null || $carta->leGanaA($mejor[1])) {
                [$mejor, $empatan] = [[$asiento, $carta], false];
            } elseif ($carta->empataCon($mejor[1]) && ! $this->sonCompaneros($asiento, $mejor[0])) {
                $empatan = true;
            }
        }

        return $mejor === null || $empatan ? null : $mejor[0];
    }

    /**
     * La carta más alta que hay en la baza en juego, o null si todavía no cayó ninguna.
     */
    public function laMasAltaDeLaMesa(): ?Carta
    {
        $mejor = null;

        foreach ($this->enLaMesa() as [, $carta]) {
            if ($mejor === null || $carta->leGanaA($mejor)) {
                $mejor = $carta;
            }
        }

        return $mejor;
    }

    /**
     * Cuántos rivales todavía tienen que tirar su carta en esta baza, después de él.
     */
    public function rivalesQueFaltan(): int
    {
        $yaJugaron = array_column($this->vista['bazas'][count($this->vista['bazas']) - 1]['jugadas'] ?? [], 0);
        $faltan = 0;

        foreach (range(0, $this->vista['asientos'] - 1) as $asiento) {
            if ($asiento !== $this->asiento
                && ! $this->esDeMiEquipo($asiento)
                && ! in_array($asiento, $yaJugaron, true)
                && ! in_array($asiento, $this->vista['enMazo'], true)) {
                $faltan++;
            }
        }

        return $faltan;
    }

    public function elCompaneroSigueEnLaMano(): bool
    {
        return $this->companero !== null && ! in_array($this->companero, $this->vista['enMazo'], true);
    }

    /**
     * Las cartas con las que todavía puede ganar una baza, por su jerarquía: las de la mano y la que
     * ya tiró en la baza en juego.
     *
     * @return list<int>
     */
    public function vivas(): array
    {
        $vivas = array_map(fn (Carta $carta) => $carta->jerarquia(), $this->enMano());

        foreach ($this->enLaMesa() as [$asiento, $carta]) {
            if ($asiento === $this->asiento) {
                $vivas[] = $carta->jerarquia();
            }
        }

        return $vivas;
    }

    public function masAlta(): int
    {
        return max([0, ...$this->vivas()]);
    }

    /**
     * La carta más fuerte que su compañero le dijo que tiene, en la escala del motor. 0 si no le dijo
     * ninguna o si el compañero ya se fue de la mano.
     */
    public function loQueDijoElCompanero(): int
    {
        return $this->elCompaneroSigueEnLaMano() ? Senas::fuerzaDe($this->senas) : 0;
    }

    public function elCompaneroTieneEnvido(): bool
    {
        return $this->elCompaneroSigueEnLaMano() && in_array(Senas::BUEN_ENVIDO, $this->senas, true);
    }

    /**
     * La carta más alta con la que cuenta la pareja: la suya o la que le señaló el compañero.
     */
    public function masAltaDeLaPareja(): int
    {
        return max($this->masAlta(), $this->loQueDijoElCompanero());
    }

    /**
     * Cuántas bazas cerradas ganó su equipo.
     */
    public function bazasGanadas(): int
    {
        return count(array_filter(
            $this->vista['bazas'],
            fn (array $baza) => $baza['cerrada'] && $baza['ganador'] !== null && $this->esDeMiEquipo($baza['ganador']),
        ));
    }

    /**
     * Cuántas bazas cerradas ganaron los rivales.
     */
    public function bazasPerdidas(): int
    {
        return count(array_filter(
            $this->vista['bazas'],
            fn (array $baza) => $baza['cerrada'] && $baza['ganador'] !== null && ! $this->esDeMiEquipo($baza['ganador']),
        ));
    }

    /**
     * Si su compañero todavía tiene que tirar su carta en la baza en juego.
     */
    public function elCompaneroTodaviaJuega(): bool
    {
        $yaJugaron = array_column($this->vista['bazas'][count($this->vista['bazas']) - 1]['jugadas'] ?? [], 0);

        return $this->elCompaneroSigueEnLaMano() && ! in_array($this->companero, $yaJugaron, true);
    }

    /**
     * Las cartas que ya se vieron en la mano, por su jerarquía: las jugadas en las bazas y las propias.
     * Sirve para saber cuál es la más alta que todavía puede aparecer.
     *
     * @return list<int>
     */
    public function vistas(): array
    {
        $vistas = array_map(fn (Carta $carta) => $carta->jerarquia(), $this->enMano());

        foreach ($this->vista['bazas'] as $baza) {
            foreach ($baza['jugadas'] as [, $carta]) {
                $vistas[] = Carta::de($carta)->jerarquia();
            }
        }

        return $vistas;
    }

    /**
     * Sus puntos. En el gallito cada uno suma aparte: los suyos.
     */
    public function misPuntos(): int
    {
        return $this->mesa->esGallito() ? $this->vista['tanteo'][$this->asiento] : $this->vista['tanteo'][$this->equipo];
    }

    /**
     * Los del rival. En el gallito, los del que va más arriba de los otros dos.
     */
    public function susPuntos(): int
    {
        if ($this->mesa->esGallito()) {
            $otros = $this->vista['tanteo'];
            unset($otros[$this->asiento]);

            return max($otros);
        }

        return $this->vista['tanteo'][1 - $this->equipo];
    }

    private function sonCompaneros(int $uno, int $otro): bool
    {
        return $this->mesa->equipoDe($uno) === $this->mesa->equipoDe($otro);
    }
}
