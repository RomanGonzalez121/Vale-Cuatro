<?php

namespace App\Juego;

use App\Models\EventoDePartida;
use App\Models\Partida;
use App\Models\Resultado;
use App\Motor\Fase;
use Illuminate\Support\Facades\DB;

/**
 * Lo que cada partida cerrada le cuenta al ranking, sacado de sus eventos.
 *
 * Vuelve a pasar la partida por el motor, igual que la repetición, y se queda con tres cosas de cada
 * persona que la jugó: si la ganó, cuántos envidos se quisieron y cuántos de esos ganó. Eso se guarda
 * en un renglón por persona (Resultado) cuando la partida se cierra, para que abrir la tabla no sea
 * volver a jugar todas las partidas. El renglón no agrega nada que no esté en los eventos:
 * recalcular() borra todos y los escribe de nuevo desde ahí.
 */
final class Estadisticas
{
    /** Contra el bot, una partida cuenta desde este nivel: ganarle al Fácil no sube a nadie. */
    public const DESDE_EL_NIVEL = Nivel::Intermedio;

    /**
     * Si una partida entra en el ranking: tiene que estar cerrada con un ganador (una sala que nadie
     * ocupó no lo tiene), y ser entre personas o contra un bot de Intermedio para arriba.
     */
    public function cuenta(Partida $partida): bool
    {
        if ($partida->estaAbierta() || $partida->ganador === null) {
            return false;
        }

        return $partida->entre_personas || ($partida->nivel_bot?->value ?? 0) >= self::DESDE_EL_NIVEL->value;
    }

    /**
     * Lo que dicen los eventos de una partida sobre cada persona sentada. Vacío si la partida no cuenta.
     *
     * Abandonar es perder, siempre. Para el otro asiento, ganar así cuenta solo si ya iba en las buenas
     * (la mitad de los puntos de la partida, 15 de 30): si no, no se le anota nada. Así no sirve abrir
     * una sala, sentarse desde otra ventana y abandonar para regalarse partidas.
     *
     * El asiento del bot no tiene renglón, y tampoco el de alguien cuya fila de jugador ya no existe.
     *
     * @return list<array{jugador_id: int, gano: bool, envidos_jugados: int, envidos_ganados: int}>
     */
    public function de(Partida $partida, Mesa $mesa): array
    {
        if (! $this->cuenta($partida)) {
            return [];
        }

        $ganador = null;
        // Ganó porque el otro se fue, sin haber llegado a las buenas: esa victoria no se anota.
        $regalada = false;
        $jugados = 0;
        $ganados = [0, 0];
        $anterior = null;

        foreach ($mesa->pasoAPaso($partida) as [$evento, $motor]) {
            if ($evento->tipo === EventoDePartida::ABANDONO) {
                $ganador = 1 - $evento->asiento;
                $regalada = $motor->tanteo()[$ganador] < intdiv($partida->puntos, 2);
            }

            // El motor es inmutable: cada jugada devuelve otro. Si es el mismo de antes, este evento no pasó
            // por el motor (una llegada, un abandono) y sus hechos son los de la jugada anterior, ya contados.
            if ($motor === $anterior) {
                continue;
            }

            $anterior = $motor;

            foreach ($motor->hechos() as $hecho) {
                // Un envido cuenta cuando se quiso y se cantaron los tantos. El que no se quiso no se jugó.
                if ($hecho['tipo'] === 'tantos' && $hecho['canto'] === 'envido') {
                    $jugados++;
                    $ganados[$hecho['ganador']]++;
                }
            }

            if ($motor->fase() === Fase::Terminada) {
                $ganador = $motor->ganador();
            }
        }

        if ($ganador === null) {
            return [];
        }

        $personas = [Mesa::JUGADOR => $partida->jugador_id, Mesa::BOT => $partida->entre_personas ? $partida->invitado_id : null];
        $renglones = [];

        foreach ($personas as $asiento => $jugador) {
            if ($jugador !== null && ! ($regalada && $asiento === $ganador)) {
                $renglones[] = [
                    'jugador_id' => $jugador,
                    'gano' => $ganador === $asiento,
                    'envidos_jugados' => $jugados,
                    'envidos_ganados' => $ganados[$asiento],
                ];
            }
        }

        return $renglones;
    }

    /**
     * Guarda los renglones de una partida recién cerrada. Lo llama la mesa, dentro de la misma
     * transacción que la cierra. Anotarla dos veces deja lo mismo que anotarla una: la tabla lee los
     * renglones por la fecha de cierre de la partida, no por el orden en que se escribieron.
     */
    public function anotar(Partida $partida, Mesa $mesa): void
    {
        Resultado::query()->where('partida_id', $partida->getKey())->delete();

        foreach ($this->de($partida, $mesa) as $renglon) {
            Resultado::create([...$renglon, 'partida_id' => $partida->getKey(), 'terminada_en' => $partida->terminada_en]);
        }
    }

    /**
     * Borra todos los renglones y los escribe de nuevo desde los eventos. Devuelve cuántas partidas
     * quedaron contadas.
     */
    public function recalcular(Mesa $mesa): int
    {
        return DB::transaction(function () use ($mesa) {
            Resultado::query()->delete();

            $contadas = 0;
            $cerradas = Partida::query()
                ->whereIn('estado', [Partida::TERMINADA, Partida::ABANDONADA])
                ->whereNotNull('ganador')
                ->orderBy('terminada_en')
                ->orderBy('id');

            foreach ($cerradas->lazy(200) as $partida) {
                $this->anotar($partida, $mesa);
                $contadas += (int) $this->cuenta($partida);
            }

            return $contadas;
        });
    }
}
