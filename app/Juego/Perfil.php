<?php

namespace App\Juego;

use App\Motor\Tanto;

/**
 * Cómo viene jugando el rival en esta partida, sacado de las manos que el bot recuerda.
 *
 * Son cuatro números. Cada uno arranca en lo que se supone de un rival cualquiera
 * (lo mismo que usa el Difícil) y se corrige con lo que se vio: en una partida hay
 * pocas manos, así que la suposición pesa como cuatro manos vistas y recién deja
 * de mandar cuando hay bastantes más.
 *
 * Nada de esto sale de cartas ocultas: el tanto del rival se sabe cuando lo dijo o
 * cuando jugó sus tres cartas, y cómo terminó cada mano lo vio toda la mesa.
 */
final class Perfil
{
    /** Cuántas manos vistas pesa lo que se supone antes de ver nada. */
    private const SUPUESTO = 4;

    /** Desde qué tanto se suele cantar envido, y por debajo de cuál cantarlo es mentir. */
    private const TANTO_PARA_CANTAR = 27;

    private const TANTO_DE_MENTIRA = 24;

    /** Lo que se supone de un rival cualquiera. */
    private const MIENTE_EL_ENVIDO = 0.15;

    private const SE_CALLA_TENIENDO = 0.25;

    private const TIENE_CUANDO_CANTA_TRUCO = 2 / 3;

    private const SE_VA = 0.4;

    private function __construct(
        /** De los envidos que abre, qué parte canta sin tanto. */
        public readonly float $mienteElEnvido,
        /** Con tanto para cantar, qué parte de las veces se lo calla. */
        public readonly float $seCallaTeniendo,
        /** De los trucos que cantó y se jugaron hasta el final, qué parte ganó. */
        public readonly float $tieneCuandoCantaTruco,
        /** De los cantos que le hizo el bot, qué parte no quiso. */
        public readonly float $seVa,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $manos  La vista del asiento del bot al cierre de cada mano anterior.
     */
    public static function de(array $manos): self
    {
        [$envidos, $mentidos, $conTanto, $callados, $trucos, $ganados, $apretado, $seFue] = [0, 0, 0, 0, 0, 0, 0, 0];

        foreach ($manos as $vista) {
            $lectura = new Lectura($vista);
            $envido = $vista['envido'];
            $cadena = $envido['cadena'];
            $suyo = self::tantoDelRival($lectura);

            // Los cantos se alternan: sabiendo quién hizo el último se sabe quién abrió.
            $abrio = match (true) {
                $cadena === [] => null,
                (count($cadena) - 1) % 2 === 0 => $envido['canto'],
                default => 1 - $envido['canto'],
            };

            if ($abrio === $lectura->rival && $suyo !== null) {
                [$exacto, $tanto] = $suyo;

                // Con un tope ("son buenas") solo se sabe algo si el tope ya es bajo.
                if ($exacto || $tanto < self::TANTO_DE_MENTIRA) {
                    $envidos++;
                    $mentidos += (int) ($tanto < self::TANTO_DE_MENTIRA);
                }

                if ($exacto && $tanto >= self::TANTO_PARA_CANTAR) {
                    $conTanto++;
                }
            }

            if ($cadena === [] && $vista['flor']['cantadas'] === [] && $suyo !== null && $suyo[0] && $suyo[1] >= self::TANTO_PARA_CANTAR) {
                $conTanto++;
                $callados++;
            }

            $truco = $vista['truco'];
            $motivo = $vista['cierre']['motivo'] ?? null;

            // El rival fue el último en cantar o subir, y la mano se jugó hasta el final.
            if ($truco['canto'] === $lectura->rival && $motivo === 'bazas') {
                $trucos++;
                $ganados += (int) ($vista['cierre']['ganador'] === $lectura->rival);
            }

            if ($truco['canto'] === $lectura->asiento) {
                $apretado++;
                $seFue += (int) ($motivo === 'no_quiero');
            }

            if ($cadena !== [] && $envido['canto'] === $lectura->asiento && $envido['estado'] !== null) {
                $apretado++;
                $seFue += (int) ($envido['estado'] === 'no_querido');
            }
        }

        return new self(
            self::corregido(self::MIENTE_EL_ENVIDO, $mentidos, $envidos),
            self::corregido(self::SE_CALLA_TENIENDO, $callados, $conTanto),
            self::corregido(self::TIENE_CUANDO_CANTA_TRUCO, $ganados, $trucos),
            self::corregido(self::SE_VA, $seFue, $apretado),
        );
    }

    /**
     * Cuánto creerle al truco del rival, comparado con lo que se le cree a uno cualquiera: 1 es lo
     * mismo, menos de 1 si canta sin tener, más si cuando canta siempre tiene. Entre la mitad y el doble.
     */
    public function creibleElTruco(): float
    {
        $tiene = min(0.95, max(0.05, $this->tieneCuandoCantaTruco));
        $supuesto = self::TIENE_CUANDO_CANTA_TRUCO;

        return min(2.0, max(0.5, ($tiene / (1 - $tiene)) / ($supuesto / (1 - $supuesto))));
    }

    /**
     * La suposición, corregida por lo que pasó en las veces que se pudo ver.
     */
    private static function corregido(float $supuesto, int $pasaron, int $veces): float
    {
        return (self::SUPUESTO * $supuesto + $pasaron) / (self::SUPUESTO + $veces);
    }

    /**
     * El tanto del rival en una mano cerrada, si se llegó a saber: lo dijo, o jugó sus tres cartas.
     *
     * @return array{0: bool, 1: int}|null Si es exacto (o un tope) y cuál es.
     */
    private static function tantoDelRival(Lectura $lectura): ?array
    {
        $jugadas = $lectura->jugadasDe($lectura->rival);

        if (count($jugadas) === 3) {
            return [true, Tanto::deEnvido($jugadas)];
        }

        return Deducciones::tantoDicho($lectura);
    }
}
