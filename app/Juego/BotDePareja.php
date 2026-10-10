<?php

namespace App\Juego;

use App\Motor\Accion;
use App\Motor\Azar;
use App\Motor\Carta;
use App\Motor\Envido;
use App\Motor\TipoDeAccion;

/**
 * El bot del truco de a cuatro, de Intermedio para arriba (el Fácil tira cualquier cosa, también de a cuatro).
 *
 * Juega en pareja: no le pisa la carta al compañero, sale bajo si el compañero le señaló una brava,
 * y para cantar o querer cuenta con lo que le dijeron las señas. Decide con la vista de su asiento y
 * con esas señas, y con nada más: nunca ve las cartas de nadie.
 *
 * Los niveles se distinguen igual que en el mano a mano:
 * - Intermedio: juega de frente, con reglas fijas. Si canta, tiene.
 * - Difícil: saca cuentas. Imagina muchas veces las cartas que no ve (CuentasDePareja) y con eso elige qué
 *   carta tirar y si un truco se canta, se quiere o se sube. Cada tanto miente.
 * - Ultra difícil: además lee a los rivales (si cantaron truco, supone que tienen con qué), se acuerda de
 *   cómo vienen contestando en la partida y arriesga según el tanteo.
 */
final class BotDePareja implements Bot, RecibeSenas, Recuerda
{
    private const BRAVA = 11;

    private const UN_TRES = 10;

    private const UN_DOS = 9;

    /** Cuántas cartas hay de cada jerarquía en el mazo, de la más alta a la más baja. */
    private const EN_EL_MAZO = [14 => 1, 13 => 1, 12 => 1, 11 => 1, 10 => 4, 9 => 4, 8 => 2, 7 => 4, 6 => 4, 5 => 4, 4 => 2, 3 => 4, 2 => 4, 1 => 4];

    /** Lo que se puede cantar en el truco y hasta qué escalón llega cada canto. */
    private const ESCALONES = [
        [TipoDeAccion::Truco, 1],
        [TipoDeAccion::Retruco, 2],
        [TipoDeAccion::ValeCuatro, 3],
    ];

    /** @var list<string> */
    private array $senas = [];

    /** De los trucos que cantó su equipo en la partida, cuántos hubo y cuántos no quisieron los rivales. */
    private int $trucosCantados = 0;

    private int $trucosNoQueridos = 0;

    /** La cuenta de esta decisión, para no sacarla dos veces: la carta que más gana y con qué probabilidad. */
    private ?array $laMejor = null;

    private readonly CuentasDePareja $cuentas;

    public function __construct(private readonly Nivel $nivel, private readonly Azar $azar)
    {
        $this->cuentas = new CuentasDePareja($azar);
    }

    public function recibirSenas(array $senas): void
    {
        $this->senas = $senas;
    }

    /**
     * Lo único que recuerda de las manos anteriores: cuántas veces los rivales no quisieron un truco de
     * su equipo. Con eso el Ultra decide cada cuánto mentir.
     */
    public function recordar(array $manos): void
    {
        $this->trucosCantados = 0;
        $this->trucosNoQueridos = 0;

        foreach ($manos as $mano) {
            $equipo = $mano['asiento'] % 2;

            if (($mano['truco']['nivel'] ?? 0) > 0 && ($mano['truco']['canto'] ?? null) === $equipo) {
                $this->trucosCantados++;
                $this->trucosNoQueridos += (int) (($mano['cierre']['motivo'] ?? null) === 'no_quiero' && ($mano['cierre']['ganador'] ?? null) === $equipo);
            }
        }
    }

    public function decidir(array $vista): Accion
    {
        $lectura = new LecturaDePareja($vista, $this->senas);
        $this->laMejor = null;

        // La flor se canta siempre que se pueda: en su turno o contestando un envido o un truco.
        if ($lectura->puede(TipoDeAccion::Flor)) {
            return Accion::de(TipoDeAccion::Flor);
        }

        return match ($lectura->pendiente()) {
            'contraflor' => $this->contestarLaContraflor($lectura),
            'envido' => $this->contestarElEnvido($lectura),
            // El envido está primero: si la pareja tiene tanto, lo canta antes de contestar el truco.
            'truco' => $this->cantarElEnvido($lectura) ?? $this->contestarElTruco($lectura),
            default => $this->enSuTurno($lectura),
        };
    }

    private function enSuTurno(LecturaDePareja $lectura): Accion
    {
        if ($lectura->puede(TipoDeAccion::Contraflor) && $lectura->tantoDeFlor() >= 30) {
            return Accion::de(TipoDeAccion::Contraflor);
        }

        return $this->cantarElEnvido($lectura)
            ?? $this->cantarElTruco($lectura)
            ?? Accion::jugar($this->elegirCarta($lectura));
    }

    private function contestarLaContraflor(LecturaDePareja $lectura): Accion
    {
        // Al resto se juega mucho más: pide una flor más alta. Quien contesta sin flor propia confía en la de su compañero.
        $hacenFalta = $lectura->vista['flor']['contra'] === TipoDeAccion::ContraflorAlResto->value ? 34 : 30;
        $tieneFlor = count(array_unique(array_map(fn (Carta $carta) => $carta->palo->value, $lectura->tresCartas()))) === 1;

        return Accion::de($tieneFlor && $lectura->tantoDeFlor() >= $hacenFalta ? TipoDeAccion::Quiero : TipoDeAccion::NoQuiero);
    }

    /**
     * El tanto con el que cuenta la pareja: el propio o, si el compañero le dijo que tiene buen envido, por lo menos ese.
     */
    private function tantoDeLaPareja(LecturaDePareja $lectura): int
    {
        return max($lectura->tanto(), $lectura->elCompaneroTieneEnvido() ? Senas::TANTO_PARA_EL_ENVIDO + 1 : 0);
    }

    private function cantarElEnvido(LecturaDePareja $lectura): ?Accion
    {
        if (! $lectura->puede(TipoDeAccion::Envido)) {
            return null;
        }

        $tanto = $this->tantoDeLaPareja($lectura);

        return match (true) {
            $lectura->tanto() >= 31 => Accion::de(TipoDeAccion::RealEnvido),
            $tanto >= 27 => Accion::de(TipoDeAccion::Envido),
            default => null,
        };
    }

    private function contestarElEnvido(LecturaDePareja $lectura): Accion
    {
        $tanto = $this->tantoDeLaPareja($lectura);

        if ($lectura->tanto() >= 32 && $lectura->puede(TipoDeAccion::RealEnvido)) {
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
            default => 27,
        };
    }

    private function cantarElTruco(LecturaDePareja $lectura): ?Accion
    {
        $llega = $this->hastaDondeCanta($lectura);

        // Con una mano pareja, que puede salir para cualquier lado, cada tanto canta truco igual. Solo al empezar
        // la mano, que es cuando se le puede creer, y nunca con las manos vacías: un rival que quiere lo cobra doble.
        if ($llega === 0
            && $this->sacaCuentas()
            && $lectura->bazasGanadas() === 0 && $lectura->bazasPerdidas() === 0
            && $lectura->vista['truco']['nivel'] === 0
            && $this->probabilidadDeGanar($lectura) >= 0.42
            && $this->miente()) {
            $llega = 1;
        }

        foreach (self::ESCALONES as [$canto, $escalon]) {
            if ($llega >= $escalon && $lectura->puede($canto)) {
                return Accion::de($canto);
            }
        }

        return null;
    }

    private function contestarElTruco(LecturaDePareja $lectura): Accion
    {
        $cantado = $lectura->vista['truco']['nivel'];

        // Si las cartas de la pareja dan para más de lo que le cantaron, sube en vez de querer.
        if ($this->hastaDondeCanta($lectura) > $cantado) {
            foreach (self::ESCALONES as [$canto, $escalon]) {
                if ($escalon === $cantado + 1 && $lectura->puede($canto)) {
                    return Accion::de($canto);
                }
            }
        }

        return Accion::de($this->quiereElTruco($lectura, $cantado) ? TipoDeAccion::Quiero : TipoDeAccion::NoQuiero);
    }

    private function quiereElTruco(LecturaDePareja $lectura, int $cantado): bool
    {
        $masAlta = $lectura->masAltaDeLaPareja();

        if ($this->nivel->value < Nivel::Dificil->value) {
            // De frente: con una brava en la pareja, o con un 2 o un 3 si ya ganaron una baza o el canto recién empieza.
            return $masAlta >= self::BRAVA
                || ($masAlta >= self::UN_DOS && ($lectura->bazasGanadas() > 0 || $cantado === 1));
        }

        // Sacando cuentas: no querer regala los puntos de lo ya cantado, así que alcanza con ganar una de cada tres.
        // Ganando cómodo pide un poco más; perdiendo por mucho, se la juega con menos.
        return $this->probabilidadDeGanar($lectura) >= 0.36 + 0.05 * $this->cuidado($lectura);
    }

    private function sacaCuentas(): bool
    {
        return $this->nivel->value >= Nivel::Dificil->value;
    }

    /**
     * La probabilidad de que la pareja gane la mano desde acá. Si le toca tirar, tirando la carta que más conviene.
     */
    private function probabilidadDeGanar(LecturaDePareja $lectura): float
    {
        if ($lectura->vista['turno'] === $lectura->asiento && $lectura->enMano() !== []) {
            return $this->laQueMasGana($lectura)[1];
        }

        return $this->cuentas->deGanarLaMano($lectura, $this->nivel === Nivel::UltraDificil);
    }

    /**
     * De las cartas que tiene, la que le da a la pareja más chances de ganar la mano, y esa probabilidad.
     * Si dos dan casi lo mismo se queda con la más baja: la alta puede hacer falta después.
     *
     * @return array{0: Carta, 1: float}
     */
    private function laQueMasGana(LecturaDePareja $lectura): array
    {
        if ($this->laMejor !== null) {
            return $this->laMejor;
        }

        $enMano = $lectura->enMano();
        // Dos cartas que valen lo mismo dan la misma cuenta: se prueba una de cada valor.
        $valores = array_values(array_unique(array_map(fn (Carta $carta) => $carta->jerarquia(), $enMano)));
        $cuentas = $this->cuentas->tirando($lectura, $valores, $this->nivel === Nivel::UltraDificil);
        $mejor = null;
        $gana = -1.0;

        // De la más baja a la más alta: una más alta solo le gana el lugar si da claramente más.
        foreach ($enMano as $carta) {
            if ($cuentas[$carta->jerarquia()] > $gana + 0.03) {
                [$mejor, $gana] = [$carta, $cuentas[$carta->jerarquia()]];
            }
        }

        return $this->laMejor = [$mejor, $gana];
    }

    /**
     * Hasta qué escalón canta por su cuenta con lo que tiene la pareja: 0 nada, 1 truco, 2 retruco y 3 vale cuatro.
     */
    private function hastaDondeCanta(LecturaDePareja $lectura): int
    {
        if ($this->sacaCuentas()) {
            // Con la probabilidad de ganar la mano: cuanto más segura, más alto canta. Ganando cómodo pide un poco más.
            $gana = $this->probabilidadDeGanar($lectura) - 0.05 * $this->cuidado($lectura);

            return match (true) {
                $gana >= 0.86 => 3,
                $gana >= 0.74 => 2,
                $gana >= 0.60 => 1,
                default => 0,
            };
        }

        $masAlta = $lectura->masAltaDeLaPareja();
        $ganaronUna = $lectura->bazasGanadas() > 0;
        $bravas = count(array_filter($lectura->vivas(), fn (int $viva) => $viva >= self::BRAVA))
            + (int) ($lectura->loQueDijoElCompanero() >= self::BRAVA);
        $buenas = count(array_filter($lectura->vivas(), fn (int $viva) => $viva >= self::UN_DOS))
            + (int) ($lectura->loQueDijoElCompanero() >= self::UN_DOS);

        $llega = match (true) {
            // Con una baza ganada y la carta que ya nadie puede superar, la mano es de la pareja.
            $ganaronUna && ($masAlta >= 13 || $this->manda($lectura)) => 3,
            $bravas >= 2, $ganaronUna && $masAlta >= 12 => 2,
            $bravas >= 1 && ($ganaronUna || $buenas >= 2), $ganaronUna && $masAlta >= self::UN_TRES => 1,
            default => 0,
        };

        // Ganando cómodo no arriesga de más; perdiendo por mucho, se anima a un escalón más con algo en la mano.
        return max(0, min(3, $llega - ($llega > 0 ? $this->cuidado($lectura) : 0)));
    }

    /**
     * Si tiene en la mano una carta que ya nadie puede superar: no quedó sin verse ninguna más alta.
     * Es la cuenta que saca el Difícil; el Intermedio no la hace.
     */
    private function manda(LecturaDePareja $lectura): bool
    {
        if ($this->nivel->value < Nivel::Dificil->value || $lectura->masAlta() === 0) {
            return false;
        }

        $vistas = array_count_values($lectura->vistas());

        foreach (self::EN_EL_MAZO as $jerarquia => $cuantas) {
            if ($jerarquia <= $lectura->masAlta()) {
                return true;
            }

            // Queda alguna de esa jerarquía sin verse: la puede tener un rival.
            if ($cuantas > ($vistas[$jerarquia] ?? 0)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cuánto se cuida según el tanteo: 1 si va ganando cómodo, -1 si va perdiendo por mucho y 0 si no.
     * Solo lo mira el Ultra.
     */
    private function cuidado(LecturaDePareja $lectura): int
    {
        if ($this->nivel !== Nivel::UltraDificil) {
            return 0;
        }

        $ventaja = $lectura->misPuntos() - $lectura->susPuntos();

        return match (true) {
            $ventaja >= 6 => 1,
            $ventaja <= -6 => -1,
            default => 0,
        };
    }

    /**
     * Si esta vez miente. El Intermedio nunca. El Difícil, una de cada seis. El Ultra según cómo vienen
     * contestando los rivales: si se van seguido miente más, y si quieren todo, casi nunca.
     */
    private function miente(): bool
    {
        $unaDeCada = match ($this->nivel) {
            Nivel::Dificil => 6,
            Nivel::UltraDificil => match (true) {
                $this->trucosCantados < 3 => 6,
                $this->trucosNoQueridos * 2 >= $this->trucosCantados => 3,
                $this->trucosNoQueridos * 5 <= $this->trucosCantados => 10,
                default => 6,
            },
            default => null,
        };

        return $unaDeCada !== null && $this->azar->entero(1, $unaDeCada) === 1;
    }

    private function elegirCarta(LecturaDePareja $lectura): Carta
    {
        if ($this->sacaCuentas()) {
            return $this->laQueMasGana($lectura)[0];
        }

        $enMano = $lectura->enMano();
        $masBaja = $enMano[0];
        $masAlta = $enMano[count($enMano) - 1];
        $deLaMesa = $lectura->laMasAltaDeLaMesa();

        // Le toca abrir la baza. Si el compañero le señaló algo mejor que lo suyo, sale bajo para que la gane él.
        if ($deLaMesa === null) {
            if ($lectura->loQueDijoElCompanero() >= self::BRAVA && $lectura->loQueDijoElCompanero() > $lectura->masAlta() && $lectura->elCompaneroTodaviaJuega()) {
                return $masBaja;
            }

            // Quien saca cuentas no gasta la brava para abrir si tiene otra carta buena con la que pelear la baza:
            // la guarda para cuando haga falta. Con una baza ya ganada sí la tira, que con esa cierra.
            if ($this->nivel->value >= Nivel::Dificil->value && count($enMano) >= 2 && $lectura->bazasGanadas() === 0) {
                $segunda = $enMano[count($enMano) - 2];

                if ($masAlta->jerarquia() >= self::BRAVA && $segunda->jerarquia() >= self::UN_DOS) {
                    return $segunda;
                }
            }

            return $masAlta;
        }

        $vaGanando = $lectura->vaGanando();

        if ($vaGanando !== null && $lectura->esDeMiEquipo($vaGanando)) {
            // Va ganando su compañero: no se la pisa. Solo la refuerza si es floja y todavía falta un rival.
            if ($lectura->rivalesQueFaltan() === 0 || $deLaMesa->jerarquia() >= self::BRAVA) {
                return $masBaja;
            }

            foreach ($enMano as $carta) {
                if ($carta->leGanaA($deLaMesa) && $carta->jerarquia() >= self::UN_DOS) {
                    return $carta;
                }
            }

            return $masBaja;
        }

        // Va ganando un rival, o va parda. Quien saca cuentas, si su compañero todavía juega y le señaló una carta
        // que alcanza, no gasta la suya: tira la más baja y deja que la gane él.
        if ($this->nivel->value >= Nivel::Dificil->value
            && $lectura->elCompaneroTodaviaJuega()
            && $lectura->loQueDijoElCompanero() > $deLaMesa->jerarquia()
            && $lectura->loQueDijoElCompanero() >= self::BRAVA
            && $lectura->rivalesQueFaltan() === 0) {
            return $masBaja;
        }

        // Si no, la más baja que alcance para ganarla.
        foreach ($enMano as $carta) {
            if ($carta->leGanaA($deLaMesa)) {
                return $carta;
            }
        }

        return $masBaja;
    }
}
