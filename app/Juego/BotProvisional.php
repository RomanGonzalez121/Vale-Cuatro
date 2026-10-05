<?php

namespace App\Juego;

use App\Motor\Accion;
use App\Motor\Carta;
use App\Motor\Envido;
use App\Motor\Tanto;
use App\Motor\TipoDeAccion;

/**
 * El bot de M3: juega con un criterio mínimo, para que se pueda jugar una
 * partida entera sin que haga locuras. No calcula probabilidades ni miente.
 * Los tres niveles de verdad son de M4, que lo reemplaza.
 *
 * Su criterio: con flor, la canta. Canta envido con 29 o más y truco solo con
 * una carta brava y una baza ganada. Quiere lo que le cantan si tiene con qué.
 * Con las cartas, gana la baza con la más baja que alcance; si no puede ganar,
 * tira la más baja; y cuando le toca salir, sale con la más alta.
 */
final class BotProvisional implements Bot
{
    public function decidir(array $vista): Accion
    {
        $puede = array_column($vista['acciones'], 'tipo');
        $cartas = $this->lasTresCartas($vista);

        // La flor se canta siempre que se pueda: en su turno o contestando un envido o un truco.
        if (in_array('flor', $puede, true)) {
            return Accion::de(TipoDeAccion::Flor);
        }

        if ($vista['pendiente'] !== null) {
            return Accion::de($this->quiere($vista, $cartas) ? TipoDeAccion::Quiero : TipoDeAccion::NoQuiero);
        }

        if (in_array('contraflor', $puede, true) && Tanto::deFlor($cartas) >= 30) {
            return Accion::de(TipoDeAccion::Contraflor);
        }

        if (in_array('envido', $puede, true) && Tanto::deEnvido($cartas) >= 29) {
            return Accion::de(TipoDeAccion::Envido);
        }

        if (in_array('truco', $puede, true) && $this->bazasGanadas($vista) > 0 && $this->laMasAlta($vista) >= 11) {
            return Accion::de(TipoDeAccion::Truco);
        }

        return Accion::jugar($this->elegirCarta($vista));
    }

    /**
     * @param  list<Carta>  $cartas
     */
    private function quiere(array $vista, array $cartas): bool
    {
        return match ($vista['pendiente']['canto']) {
            'contraflor' => Tanto::deFlor($cartas) >= 30,
            'envido' => Tanto::deEnvido($cartas) >= $this->tantoParaQuerer($vista['envido']['cadena']),
            // Un truco se quiere con una brava, o con un 2 o un 3 si ya ganó una baza o el canto recién empieza.
            default => $this->laMasAlta($vista) >= 11
                || ($this->laMasAlta($vista) >= 9 && ($this->bazasGanadas($vista) > 0 || $vista['truco']['nivel'] === 1)),
        };
    }

    /**
     * Cuanto más vale lo que se cantó, más tanto pide para quererlo.
     *
     * @param  list<string>  $cadena
     */
    private function tantoParaQuerer(array $cadena): int
    {
        return match (true) {
            in_array(Envido::FALTA, $cadena, true) => 31,
            in_array(Envido::REAL, $cadena, true) => 30,
            count($cadena) > 1 => 29,
            default => 26,
        };
    }

    private function elegirCarta(array $vista): Carta
    {
        $enMano = array_map(Carta::de(...), $vista['misCartas']);
        usort($enMano, fn (Carta $una, Carta $otra) => $una->jerarquia() <=> $otra->jerarquia());

        $bazas = $vista['bazas'];
        $enJuego = array_filter($bazas[count($bazas) - 1]['jugadas'], fn (array $jugada) => $jugada[0] !== $vista['asiento']);

        // Le toca salir: con la más alta.
        if ($enJuego === []) {
            return $enMano[count($enMano) - 1];
        }

        $aGanar = max(array_map(fn (array $jugada) => Carta::de($jugada[1])->jerarquia(), $enJuego));

        foreach ($enMano as $carta) {
            if ($carta->jerarquia() > $aGanar) {
                return $carta;
            }
        }

        return $enMano[0];
    }

    /**
     * Las tres cartas que recibió: las que le quedan y las que ya jugó. Con ellas calcula su tanto.
     *
     * @return list<Carta>
     */
    private function lasTresCartas(array $vista): array
    {
        $ids = $vista['misCartas'];

        foreach ($vista['bazas'] as $baza) {
            foreach ($baza['jugadas'] as [$asiento, $carta]) {
                if ($asiento === $vista['asiento']) {
                    $ids[] = $carta;
                }
            }
        }

        return array_map(Carta::de(...), $ids);
    }

    private function laMasAlta(array $vista): int
    {
        return max([0, ...array_map(fn (string $id) => Carta::de($id)->jerarquia(), $vista['misCartas'])]);
    }

    private function bazasGanadas(array $vista): int
    {
        return count(array_filter($vista['bazas'], fn (array $baza) => $baza['cerrada'] && $baza['ganador'] === $vista['asiento']));
    }
}
