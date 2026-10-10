<?php

namespace Tests\Feature;

use App\Juego\Bot;
use App\Juego\Mesa;
use App\Juego\Torneos;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Torneo;
use App\Motor\Accion;
use App\Motor\TipoDeAccion;

/**
 * Torneos para los tests. Las partidas de la persona van a un punto y contra un bot que hace lo que el
 * test le diga, así se cierran por la mesa de verdad en una o dos jugadas y gana quien el test quiera.
 * Las partidas entre bots se juegan como en un torneo real, a los puntos que tenga el torneo.
 *
 * Los tests que lo usan dejan la cola en pausa (Queue::fake): el bot se mueve cuando el test lo dice, y
 * las llaves se ponen al día llamando a avanzar().
 */
trait TorneosDePrueba
{
    private ?object $botDePrueba = null;

    /**
     * El bot que hace lo que el test le diga: irse al mazo, o tirar una carta.
     */
    private function botDePrueba(): object
    {
        return $this->botDePrueba ??= new class implements Bot
        {
            public bool $seVa = false;

            public function decidir(array $vista): Accion
            {
                $acciones = collect($vista['acciones']);

                return Accion::desdeArray($this->seVa ? ['tipo' => TipoDeAccion::Mazo->value] : $acciones->firstWhere('tipo', TipoDeAccion::Jugar->value));
            }
        };
    }

    private function mesaDePrueba(): Mesa
    {
        return new Mesa($this->botDePrueba());
    }

    private function torneos(): Torneos
    {
        return new Torneos($this->mesaDePrueba());
    }

    /**
     * Un torneo recién armado, con las partidas de la persona a un punto.
     */
    private function torneoCorto(Jugador $jugador, int $lugares = 4, int $semilla = 7): Torneo
    {
        $torneo = $this->torneos()->crear($jugador, $lugares, $semilla);
        $torneo->puntos = 1;
        $torneo->save();

        return $torneo;
    }

    /**
     * La persona juega su partida de la ronda y la gana o la pierde. Después las llaves se ponen al día.
     */
    private function jugarLaRonda(Torneo $torneo, bool $gana): Partida
    {
        $partida = $this->torneos()->jugar($torneo);

        $this->cerrarContraElBot($partida, $gana);
        $this->torneos()->avanzar($torneo->id);

        return $partida->fresh();
    }

    /**
     * Cierra una partida contra el bot de prueba a favor de quien se diga: el otro se va al mazo.
     */
    private function cerrarContraElBot(Partida $partida, bool $ganaLaPersona): void
    {
        $mesa = $this->mesaDePrueba();
        $this->botDePrueba()->seVa = $ganaLaPersona;

        // Si el bot es mano, juega primero: se va (y ahí terminó) o tira una carta.
        $mesa->turnoDelBot($partida->id);

        if (! $partida->fresh()->enCurso()) {
            return;
        }

        if (! $ganaLaPersona) {
            $mesa->actuar($partida, Accion::de(TipoDeAccion::Mazo));

            return;
        }

        $carta = collect($mesa->vista($partida)['acciones'])->firstWhere('tipo', TipoDeAccion::Jugar->value);

        $mesa->actuar($partida, Accion::desdeArray($carta));
        $mesa->turnoDelBot($partida->id);
    }
}
