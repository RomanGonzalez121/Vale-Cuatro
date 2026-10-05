<?php

namespace App\Juego;

use App\Motor\Accion;
use App\Motor\Carta;
use App\Motor\Envido;
use App\Motor\TipoDeAccion;

/**
 * El bot Intermedio: juega bien y de frente. Usa toda la escala de cantos
 * cuando tiene con qué, y nunca miente ni calcula probabilidades.
 *
 * Con flor, la canta. Canta envido con 27 o más y real envido con 31 o más.
 * Canta truco con una brava bien acompañada y lo sube con cartas mejores.
 * Con las cartas, gana la baza con la más baja que alcance; si no puede ganar,
 * tira la más baja; y cuando le toca salir, sale con la más alta.
 */
final class BotIntermedio implements Bot
{
    private const BRAVA = 11;

    private const UN_TRES = 10;

    private const UN_DOS = 9;

    /** Lo que se puede cantar en el truco y hasta qué escalón llega cada canto. */
    private const ESCALONES = [
        [TipoDeAccion::Truco, 1],
        [TipoDeAccion::Retruco, 2],
        [TipoDeAccion::ValeCuatro, 3],
    ];

    public function decidir(array $vista): Accion
    {
        $lectura = new Lectura($vista);

        // La flor se canta siempre que se pueda: en su turno o contestando un envido o un truco.
        if ($lectura->puede(TipoDeAccion::Flor)) {
            return Accion::de(TipoDeAccion::Flor);
        }

        return match ($lectura->pendiente()) {
            'contraflor' => $this->contestarLaContraflor($lectura),
            'envido' => $this->contestarElEnvido($lectura),
            // El envido está primero: si tiene tanto, lo canta antes de contestar el truco.
            'truco' => $this->cantarElEnvido($lectura) ?? $this->contestarElTruco($lectura),
            default => $this->enSuTurno($lectura),
        };
    }

    private function enSuTurno(Lectura $lectura): Accion
    {
        if ($lectura->puede(TipoDeAccion::Contraflor) && $lectura->tantoDeFlor() >= 30) {
            return Accion::de(TipoDeAccion::Contraflor);
        }

        return $this->cantarElEnvido($lectura)
            ?? $this->cantarElTruco($lectura)
            ?? Accion::jugar($this->elegirCarta($lectura));
    }

    private function contestarLaContraflor(Lectura $lectura): Accion
    {
        // Al resto se juega mucho más: pide una flor más alta.
        $hacenFalta = $lectura->vista['flor']['contra'] === TipoDeAccion::ContraflorAlResto->value ? 34 : 30;

        return Accion::de($lectura->tantoDeFlor() >= $hacenFalta ? TipoDeAccion::Quiero : TipoDeAccion::NoQuiero);
    }

    private function cantarElEnvido(Lectura $lectura): ?Accion
    {
        if (! $lectura->puede(TipoDeAccion::Envido)) {
            return null;
        }

        return match (true) {
            $lectura->tanto() >= 31 => Accion::de(TipoDeAccion::RealEnvido),
            $lectura->tanto() >= 27 => Accion::de(TipoDeAccion::Envido),
            default => null,
        };
    }

    private function contestarElEnvido(Lectura $lectura): Accion
    {
        $tanto = $lectura->tanto();

        if ($tanto >= 32 && $lectura->puede(TipoDeAccion::RealEnvido)) {
            return Accion::de(TipoDeAccion::RealEnvido);
        }

        return Accion::de($tanto >= $this->tantoParaQuerer($lectura->vista['envido']['cadena']) ? TipoDeAccion::Quiero : TipoDeAccion::NoQuiero);
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

    private function cantarElTruco(Lectura $lectura): ?Accion
    {
        $llega = $this->hastaDondeCanta($lectura);

        foreach (self::ESCALONES as [$canto, $escalon]) {
            if ($llega >= $escalon && $lectura->puede($canto)) {
                return Accion::de($canto);
            }
        }

        return null;
    }

    private function contestarElTruco(Lectura $lectura): Accion
    {
        $cantado = $lectura->vista['truco']['nivel'];

        // Si sus cartas dan para más de lo que le cantaron, sube en vez de querer.
        if ($this->hastaDondeCanta($lectura) > $cantado) {
            foreach (self::ESCALONES as [$canto, $escalon]) {
                if ($escalon === $cantado + 1 && $lectura->puede($canto)) {
                    return Accion::de($canto);
                }
            }
        }

        // Un truco se quiere con una brava, o con un 2 o un 3 si ya ganó una baza o el canto recién empieza.
        $quiere = $lectura->masAlta() >= self::BRAVA
            || ($lectura->masAlta() >= self::UN_DOS && ($lectura->bazasGanadas() > 0 || $cantado === 1));

        return Accion::de($quiere ? TipoDeAccion::Quiero : TipoDeAccion::NoQuiero);
    }

    /**
     * Hasta qué escalón canta por su cuenta con las cartas que tiene: 0 nada, 1 truco, 2 retruco y 3 vale cuatro.
     */
    private function hastaDondeCanta(Lectura $lectura): int
    {
        $masAlta = $lectura->masAlta();
        $ganoUna = $lectura->bazasGanadas() > 0;
        $bravas = $lectura->cuantasDesde(self::BRAVA);

        return match (true) {
            // Uno de los dos anchos y una baza ganada: le falta una sola y casi nadie se la saca.
            $ganoUna && $masAlta >= 13 => 3,
            $bravas >= 2, $ganoUna && $masAlta >= 12 => 2,
            $bravas >= 1 && ($ganoUna || $lectura->cuantasDesde(self::UN_DOS) >= 2), $ganoUna && $masAlta >= self::UN_TRES => 1,
            default => 0,
        };
    }

    private function elegirCarta(Lectura $lectura): Carta
    {
        $enMano = $lectura->enMano();
        $aGanar = $lectura->enLaMesaDe($lectura->rival);

        // Le toca salir: con la más alta.
        if ($aGanar === null) {
            return $enMano[count($enMano) - 1];
        }

        foreach ($enMano as $carta) {
            if ($carta->leGanaA($aGanar)) {
                return $carta;
            }
        }

        return $enMano[0];
    }
}
