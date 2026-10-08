<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Avisa a los dos jugadores de una partida entre personas que hay novedades.
 *
 * Es solo un aviso: lleva el número del último evento y nada más. Ninguna carta viaja por el
 * WebSocket. Quien lo recibe pide lo que pasó por HTTP, donde el servidor arma la vista de su
 * asiento y nunca trae las cartas del otro.
 *
 * Se manda con avisar(), no con dispatch(): sale recién cuando la transacción que guardó el evento
 * se confirmó (si saliera antes, quien lo recibe pediría el evento y todavía no lo encontraría), y si
 * Reverb no responde se anota el error y la jugada sigue su camino. El aviso es una comodidad: la mesa
 * y la sala también preguntan por su cuenta, así que sin él se ve la jugada del otro unos segundos después.
 */
class PartidaActualizada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public readonly int $partida, public readonly int $evento) {}

    /**
     * Manda el aviso cuando se confirma lo guardado, sin que un fallo del tiempo real pueda romper la jugada.
     */
    public static function avisar(int $partida, int $evento): void
    {
        DB::afterCommit(function () use ($partida, $evento) {
            try {
                event(new self($partida, $evento));
            } catch (Throwable $falla) {
                report($falla);
            }
        });
    }

    /**
     * Un canal privado por partida: solo entran sus dos jugadores (ver routes/channels.php).
     *
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("partida.{$this->partida}")];
    }

    public function broadcastAs(): string
    {
        return 'partida.actualizada';
    }

    /**
     * Lo único que viaja: el número del último evento.
     *
     * @return array{evento: int}
     */
    public function broadcastWith(): array
    {
        return ['evento' => $this->evento];
    }
}
