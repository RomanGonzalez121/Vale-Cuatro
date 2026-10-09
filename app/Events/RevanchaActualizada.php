<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Avisa a los dos jugadores de una partida terminada que algo cambió con su revancha: alguien la pidió,
 * la contestó o la canceló.
 *
 * Es un aviso sin datos, por el mismo canal privado de la partida. Quien lo recibe pregunta por HTTP en
 * qué quedó. Igual que el aviso de las jugadas, sale recién cuando se confirma lo guardado, y si el
 * tiempo real no responde se anota el error y todo sigue: la mesa también pregunta por su cuenta.
 */
class RevanchaActualizada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public readonly int $partida) {}

    public static function avisar(int $partida): void
    {
        DB::afterCommit(function () use ($partida) {
            try {
                event(new self($partida));
            } catch (Throwable $falla) {
                report($falla);
            }
        });
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("partida.{$this->partida}")];
    }

    public function broadcastAs(): string
    {
        return 'revancha.actualizada';
    }

    /**
     * No viaja nada: ni quién la pidió ni qué contestó.
     *
     * @return array{}
     */
    public function broadcastWith(): array
    {
        return [];
    }
}
