<?php

namespace App\Juego;

use App\Motor\Accion;
use App\Motor\Azar;
use App\Motor\Carta;
use App\Motor\Envido;
use App\Motor\TipoDeAccion;

/**
 * El bot Difícil: calcula y miente.
 *
 * No ve las cartas del rival. Con las que no vio saca dos números: la
 * probabilidad de ganar el envido y la de ganar la mano (Probabilidades).
 * Con esos números decide por puntos esperados: quiere cuando aceptar le deja
 * más que no querer, canta cuando va ganando y elige la carta que más manos gana.
 * Cada tanto miente: canta sin tener, más seguido cuando el tanteo lo apura.
 */
final class BotDificil implements Bot
{
    /** Desde qué probabilidad de ganar el envido lo canta, y desde cuál lo sube. */
    private const CANTA_ENVIDO = 0.55;

    private const SUBE_EL_ENVIDO = 0.80;

    private const CANTA_LA_FALTA = 0.93;

    /** Desde qué probabilidad de ganar la mano canta cada escalón del truco. */
    private const CANTA_TRUCO = [
        TipoDeAccion::Truco->value => 0.60,
        TipoDeAccion::Retruco->value => 0.70,
        TipoDeAccion::ValeCuatro->value => 0.80,
    ];

    /** Contestando, sube en vez de querer si pasa de acá. */
    private const SUBE_EL_TRUCO = 0.70;

    /** Miente solo con cartas flojas: por debajo de estas probabilidades. */
    private const MIENTE_EL_ENVIDO_HASTA = 0.40;

    private const MIENTE_EL_TRUCO_HASTA = 0.30;

    /** Miente una de cada tantas veces que podría; apurado, el doble de seguido. */
    private const MIENTE_UNA_DE = 6;

    private const APURADO_UNA_DE = 3;

    /** Lo que vale la contraflor en el reglamento. */
    private const CONTRAFLOR = ['querida' => 6, 'no_querida' => 4, 'al_resto_no_querida' => 6];

    private const CANTA_CONTRAFLOR = 0.30;

    private const CANTA_AL_RESTO = 0.85;

    public function __construct(private readonly Azar $azar) {}

    public function decidir(array $vista): Accion
    {
        $lectura = new Lectura($vista);

        return $this->conLaFlor($lectura)
            ?? $this->contestando($lectura)
            ?? $this->cantandoElEnvido($lectura)
            ?? $this->cantandoElTruco($lectura)
            ?? Accion::jugar($this->laMejorCarta($lectura));
    }

    private function conLaFlor(Lectura $lectura): ?Accion
    {
        if ($lectura->puede(TipoDeAccion::Flor) && ! $this->callaLaFlor($lectura)) {
            return Accion::de(TipoDeAccion::Flor);
        }

        if ($lectura->pendiente() === 'contraflor') {
            return $this->contestarLaContraflor($lectura);
        }

        // El rival cantó flor y el bot también tiene: la contesta si le da, y si no la calla.
        if ($lectura->puede(TipoDeAccion::Contraflor)) {
            $gana = Probabilidades::deGanarLaFlor($lectura);

            return match (true) {
                $gana >= self::CANTA_AL_RESTO && $lectura->puede(TipoDeAccion::ContraflorAlResto) => Accion::de(TipoDeAccion::ContraflorAlResto),
                $gana >= self::CANTA_CONTRAFLOR => Accion::de(TipoDeAccion::Contraflor),
                default => null,
            };
        }

        return null;
    }

    /**
     * Calla la flor en un solo caso: le cantaron un envido que querido vale más de 3 y con sus
     * dos mejores cartas tiene 32 o 33. Ahí el envido le deja más que los 3 puntos de la flor.
     */
    private function callaLaFlor(Lectura $lectura): bool
    {
        return $lectura->pendiente() === 'envido'
            && $this->envidoQuerido($lectura) > 3
            && $lectura->tanto() >= 32;
    }

    private function contestarLaContraflor(Lectura $lectura): Accion
    {
        $gana = Probabilidades::deGanarLaFlor($lectura);
        $alResto = $lectura->vista['flor']['contra'] === TipoDeAccion::ContraflorAlResto->value;

        if (! $alResto && $gana >= self::CANTA_AL_RESTO && $lectura->puede(TipoDeAccion::ContraflorAlResto)) {
            return Accion::de(TipoDeAccion::ContraflorAlResto);
        }

        $querida = $alResto ? Envido::falta($lectura->vista['tanteo'], $lectura->paraGanar()) : self::CONTRAFLOR['querida'];
        $noQuerida = self::CONTRAFLOR[$alResto ? 'al_resto_no_querida' : 'no_querida'];

        return $this->quiereONo($lectura, $gana, $querida, $noQuerida);
    }

    private function contestando(Lectura $lectura): ?Accion
    {
        return match ($lectura->pendiente()) {
            'envido' => $this->contestarElEnvido($lectura),
            // El envido está primero: si le conviene, lo canta antes de contestar el truco. Acá no miente.
            'truco' => $this->cantandoElEnvido($lectura, puedeMentir: false) ?? $this->contestarElTruco($lectura),
            default => null,
        };
    }

    private function contestarElEnvido(Lectura $lectura): Accion
    {
        $gana = Probabilidades::deGanarElEnvido($lectura);

        if ($gana >= self::CANTA_LA_FALTA && $lectura->puede(TipoDeAccion::FaltaEnvido) && ! $lectura->puede(TipoDeAccion::RealEnvido)) {
            return Accion::de(TipoDeAccion::FaltaEnvido);
        }

        if ($gana >= self::SUBE_EL_ENVIDO && $lectura->puede(TipoDeAccion::RealEnvido)) {
            return Accion::de(TipoDeAccion::RealEnvido);
        }

        return $this->quiereONo($lectura, $gana, $this->envidoQuerido($lectura), Envido::noQuerido($lectura->vista['envido']['cadena']));
    }

    private function contestarElTruco(Lectura $lectura): Accion
    {
        $cantado = $lectura->vista['truco']['nivel'];

        // Quien canta truco suele tener con qué: cuanto más alto el canto, más se le cree.
        $gana = Probabilidades::creyendole(Probabilidades::deGanarLaMano($lectura), 1 + $cantado);
        $subida = [1 => TipoDeAccion::Retruco, 2 => TipoDeAccion::ValeCuatro][$cantado] ?? null;

        if ($subida !== null && $gana >= self::SUBE_EL_TRUCO && $lectura->puede($subida)) {
            return Accion::de($subida);
        }

        // Querido, la mano pasa a valer un punto más que si no se quiere.
        return $this->quiereONo($lectura, $gana, $cantado + 1, $cantado);
    }

    /**
     * Quiere cuando los puntos esperados de querer son más que los de no querer.
     * Querer deja +$querido si gana y -$querido si pierde; no querer deja -$noQuerido seguro.
     */
    private function quiereONo(Lectura $lectura, float $gana, int $querido, int $noQuerido): Accion
    {
        // Si no querer ya le da la partida al rival, no hay nada que cuidar.
        $quiere = $lectura->susPuntos() + $noQuerido >= $lectura->paraGanar()
            || (2 * $gana - 1) * $querido > -$noQuerido;

        return Accion::de($quiere ? TipoDeAccion::Quiero : TipoDeAccion::NoQuiero);
    }

    private function cantandoElEnvido(Lectura $lectura, bool $puedeMentir = true): ?Accion
    {
        if (! $lectura->puede(TipoDeAccion::Envido)) {
            return null;
        }

        $gana = Probabilidades::deGanarElEnvido($lectura);

        // Al rival le faltan dos puntos o menos: un envido común ya no cambia nada, así que va por la falta.
        if ($lectura->susPuntos() >= $lectura->paraGanar() - 2 && $gana >= 0.5) {
            return Accion::de(TipoDeAccion::FaltaEnvido);
        }

        if ($gana >= self::SUBE_EL_ENVIDO) {
            // Con mucho tanto a veces canta el envido común, para que no se lea siempre igual.
            return Accion::de($this->unaDe(3) ? TipoDeAccion::Envido : TipoDeAccion::RealEnvido);
        }

        if ($gana >= self::CANTA_ENVIDO) {
            return Accion::de(TipoDeAccion::Envido);
        }

        if ($puedeMentir && $gana <= self::MIENTE_EL_ENVIDO_HASTA && $this->miente($lectura)) {
            return Accion::de(TipoDeAccion::Envido);
        }

        return null;
    }

    private function cantandoElTruco(Lectura $lectura): ?Accion
    {
        $canto = null;

        foreach ([TipoDeAccion::Truco, TipoDeAccion::Retruco, TipoDeAccion::ValeCuatro] as $posible) {
            if ($lectura->puede($posible)) {
                $canto = $posible;
            }
        }

        // Si con lo que ya vale la mano le alcanza para ganar la partida, subir no le suma nada.
        if ($canto === null || $lectura->misPuntos() + $lectura->valorDeLaMano() >= $lectura->paraGanar()) {
            return null;
        }

        // Si perder esta mano ya es perder la partida, subir no le cuesta nada: canta siempre.
        if ($lectura->susPuntos() + $lectura->valorDeLaMano() >= $lectura->paraGanar()) {
            return Accion::de($canto);
        }

        $gana = Probabilidades::deGanarLaMano($lectura);

        // Si el último que cantó fue el rival, se le sigue creyendo.
        if ($lectura->vista['truco']['canto'] === $lectura->rival) {
            $gana = Probabilidades::creyendole($gana, 1 + $lectura->vista['truco']['nivel']);
        }

        if ($gana >= self::CANTA_TRUCO[$canto->value]) {
            return Accion::de($canto);
        }

        // La mentira del truco: lo canta con cartas flojas, esperando que no se lo quieran.
        if ($canto === TipoDeAccion::Truco && $gana <= self::MIENTE_EL_TRUCO_HASTA && $this->miente($lectura)) {
            return Accion::de($canto);
        }

        return null;
    }

    /**
     * De las cartas que tiene, la que gana la mano en más manos posibles del rival.
     * Entre dos que dan lo mismo, la más baja: la alta se guarda.
     */
    private function laMejorCarta(Lectura $lectura): Carta
    {
        $mejor = null;
        $conLaMejor = -1.0;

        foreach ($lectura->enMano() as $carta) {
            $gana = Probabilidades::deGanarLaMano($lectura, $carta);

            if ($gana > $conLaMejor + 0.000001) {
                [$mejor, $conLaMejor] = [$carta, $gana];
            }
        }

        return $mejor;
    }

    private function envidoQuerido(Lectura $lectura): int
    {
        return Envido::querido($lectura->vista['envido']['cadena'], $lectura->vista['tanteo'], $lectura->paraGanar());
    }

    /**
     * Si esta vez miente. Va apurado cuando al rival le faltan cinco puntos o menos y va ganando.
     */
    private function miente(Lectura $lectura): bool
    {
        $apurado = $lectura->susPuntos() >= $lectura->paraGanar() - 5 && $lectura->misPuntos() < $lectura->susPuntos();

        return $this->unaDe($apurado ? self::APURADO_UNA_DE : self::MIENTE_UNA_DE);
    }

    private function unaDe(int $tantas): bool
    {
        return $this->azar->entero(1, $tantas) === 1;
    }
}
