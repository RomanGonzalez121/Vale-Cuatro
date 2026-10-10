<?php

namespace App\Juego;

use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Motor\Azar;
use App\Motor\Fase;
use App\Motor\Mazo;
use App\Motor\Partida as Motor;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Una partida entera jugada por dos bots, sin nadie mirando.
 *
 * Se juega sobre el motor, con las mismas reglas y los mismos bots que una partida de verdad: cada bot
 * decide con la vista de su asiento y nunca ve las cartas del otro. Lo que sale se guarda como cualquier
 * partida, una fila y sus eventos, así que todo lo que lee partidas (las estadísticas del ranking) la
 * lee igual que a las demás.
 *
 * Todo el azar sale de la semilla: la misma semilla da siempre la misma partida, carta por carta.
 * No pasa por la mesa porque la mesa es para personas: lleva plazos, trabajos en cola y avisos en vivo
 * que acá no tienen a quién avisarle.
 */
final class Simulacion
{
    /** Cada cuánto se da por hecha una jugada, para fechar los eventos. No cambia nada del juego. */
    private const SEGUNDOS_POR_JUGADA = 4;

    /** Ninguna partida necesita tantas jugadas: es el tope que evita un bucle si algo estuviera mal. */
    private const TOPE_DE_JUGADAS = 5000;

    /**
     * Juega la partida y la guarda ya terminada. $uno ocupa el asiento 0 y $dos el 1, cada uno con el
     * bot de su nivel. $empieza es la fecha que lleva: una partida simulada puede haber "pasado" antes.
     */
    public function jugar(Jugador $uno, Nivel $nivelDeUno, Jugador $dos, Nivel $nivelDeDos, int $semilla, CarbonInterface $empieza, Mesa $mesa): Partida
    {
        [$primerMano, $eventos, $ganador] = $this->enElMotor($nivelDeUno, $nivelDeDos, $semilla);

        return DB::transaction(function () use ($uno, $dos, $primerMano, $eventos, $ganador, $empieza, $mesa) {
            $termina = $empieza->copy()->addSeconds(count($eventos) * self::SEGUNDOS_POR_JUGADA);

            $partida = new Partida([
                'jugador_id' => $uno->getKey(),
                'invitado_id' => $dos->getKey(),
                'primer_mano' => $primerMano,
                'puntos' => 30,
                'entre_personas' => true,
                'nivel_bot' => null,
            ]);

            // Lo que no se asigna en masa: una partida simulada nace terminada y con su fecha.
            $partida->estado = Partida::TERMINADA;
            $partida->simulada = true;
            $partida->ganador = $ganador;
            $partida->terminada_en = $termina;
            $partida->created_at = $empieza;
            $partida->updated_at = $termina;
            $partida->save();

            $filas = [];

            foreach ($eventos as $indice => [$tipo, $asiento, $datos]) {
                $filas[] = [
                    'partida_id' => $partida->getKey(),
                    'numero' => $indice + 1,
                    'tipo' => $tipo,
                    'asiento' => $asiento,
                    'datos' => json_encode($datos, JSON_THROW_ON_ERROR),
                    'creado_en' => $empieza->copy()->addSeconds($indice * self::SEGUNDOS_POR_JUGADA),
                ];
            }

            // De a tandas: una partida larga son unos cientos de eventos.
            foreach (array_chunk($filas, 200) as $tanda) {
                EventoDePartida::query()->insert($tanda);
            }

            (new Estadisticas)->anotar($partida, $mesa);

            return $partida;
        });
    }

    /**
     * La partida sobre el motor, sin tocar la base: quién fue mano, los eventos en orden, quién ganó y
     * cómo quedó el tanteo. $puntos es a cuánto se juega: 30, o los 15 de una partida de torneo.
     *
     * @return array{0: int, 1: list<array{0: string, 1: int|null, 2: array<string, mixed>}>, 2: int, 3: array{0: int, 1: int}}
     */
    public function enElMotor(Nivel $nivelDeUno, Nivel $nivelDeDos, int $semilla, int $puntos = 30): array
    {
        $azar = Azar::deSemilla($semilla);
        // Cada bot tiene su propio azar, que también sale de la semilla de la partida.
        $bots = [$nivelDeUno->bot(Azar::deSemilla($semilla * 7 + 1)), $nivelDeDos->bot(Azar::deSemilla($semilla * 7 + 2))];

        $primerMano = $azar->entero(Mesa::JUGADOR, Mesa::BOT);
        $motor = Motor::nueva(2, $primerMano, $puntos);
        $eventos = [];
        // Lo que cada asiento vio al cerrarse cada mano, para el bot que lleva la cuenta: igual que en la mesa.
        $cerradas = [[], []];

        while ($motor->fase() !== Fase::Terminada) {
            if (count($eventos) >= self::TOPE_DE_JUGADAS) {
                throw new RuntimeException("La partida simulada con la semilla {$semilla} no termina.");
            }

            if ($motor->fase() === Fase::PorRepartir) {
                if ($motor->cierre() !== null) {
                    $cerradas[0][] = $motor->vistaPara(0);
                    $cerradas[1][] = $motor->vistaPara(1);
                }

                $motor = $motor->repartir(Mazo::mezcladoCon($azar));
                $eventos[] = [EventoDePartida::REPARTO, null, ['manos' => $motor->aArray()['cartas']]];

                continue;
            }

            $asiento = $motor->accionesPara(Mesa::JUGADOR) !== [] ? Mesa::JUGADOR : Mesa::BOT;

            if ($bots[$asiento] instanceof Recuerda) {
                $bots[$asiento]->recordar($cerradas[$asiento]);
            }

            $accion = $bots[$asiento]->decidir($motor->vistaPara($asiento));
            // Si el bot quisiera algo que no vale, el motor lo rechaza acá y la partida no se guarda.
            $motor = $motor->aplicar($asiento, $accion);
            $eventos[] = [EventoDePartida::ACCION, $asiento, $accion->aArray()];
        }

        return [$primerMano, $eventos, (int) $motor->ganador(), $motor->tanteo()];
    }
}
