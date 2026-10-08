<?php

namespace App\Juego;

use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Las partidas cerradas de un jugador, contadas para la lista del historial.
 *
 * De cada partida la base guarda poco: de quién es, cuándo cerró y quién ganó. El tanteo final, las manos
 * que se jugaron y lo que duró no están en ninguna columna: salen de volver a pasar sus eventos por el
 * motor, igual que la repetición. Una partida cerrada no cambia más, así que la cuenta da siempre lo mismo.
 */
final class Historial
{
    /** Cuántas partidas entran en una página de la lista. */
    public const POR_PAGINA = 12;

    public function __construct(private readonly Mesa $mesa) {}

    /**
     * Las partidas cerradas del jugador, de la más nueva a la más vieja. Una sala que se canceló sin que
     * se sentara nadie no es una partida: no entra.
     *
     * Con $desde entran solo las que cerraron de ahí en adelante: es el caso de quien juega sin cuenta, que
     * ve lo de su sesión. Las demás siguen en la base (también son del rival); lo que cambia es qué se muestra.
     *
     * @return Builder<Partida>
     */
    public function cerradasDe(Jugador $jugador, ?Carbon $desde = null): Builder
    {
        return Partida::query()
            ->where(fn (Builder $consulta) => $consulta->where('jugador_id', $jugador->getKey())->orWhere('invitado_id', $jugador->getKey()))
            ->whereIn('estado', [Partida::TERMINADA, Partida::ABANDONADA])
            ->whereNotNull('ganador')
            ->when($desde !== null, fn (Builder $consulta) => $consulta->where('terminada_en', '>=', $desde))
            ->with(['jugador', 'invitado'])
            ->latest('terminada_en')
            ->latest('id');
    }

    /**
     * Una página de la lista, con cada partida ya resumida.
     */
    public function pagina(Jugador $jugador, ?Carbon $desde = null): Paginator
    {
        return $this->cerradasDe($jugador, $desde)
            ->simplePaginate(self::POR_PAGINA)
            ->through(fn (Partida $partida) => $this->resumen($partida, (int) $partida->asientoDe($jugador)));
    }

    /**
     * La partida cerrada del jugador con ese número, o null si no existe, no es suya, todavía se juega o
     * quedó fuera de lo que se le muestra. De una partida en curso no hay repetición: ahí hay cartas que
     * todavía no se jugaron.
     */
    public function cerradaDe(Jugador $jugador, int $partidaId, ?Carbon $desde = null): ?Partida
    {
        return $this->cerradasDe($jugador, $desde)->whereKey($partidaId)->first();
    }

    /**
     * Lo que la lista dice de una partida, visto desde un asiento.
     *
     * @return array{id: int, gano: bool, vos: int, ellos: int, rival: string, bot: bool, cierre: ?string, cuando: Carbon, dia: string, hora: string, manos: int, minutos: int}
     */
    public function resumen(Partida $partida, int $asiento): array
    {
        $estado = $this->mesa->reconstruir($partida)->aArray();
        $empezo = $partida->eventos()->min('creado_en');
        $cuando = $partida->terminada_en ?? $partida->updated_at;

        return [
            'id' => $partida->getKey(),
            'gano' => $partida->ganador === $asiento,
            'vos' => $estado['tanteo'][$asiento],
            'ellos' => $estado['tanteo'][1 - $asiento],
            'rival' => $this->rival($partida, $asiento),
            'bot' => ! $partida->entre_personas,
            'cierre' => $this->comoCerro($partida, $asiento),
            'cuando' => $cuando,
            'dia' => $this->dia($cuando),
            'hora' => $cuando->format('H:i'),
            'manos' => $estado['numeroDeMano'],
            // Una partida que dura menos de un minuto se cuenta como de uno: "0 minutos" no dice nada.
            'minutos' => max(1, (int) round(Carbon::parse($empezo)->diffInSeconds($cuando, true) / 60)),
        ];
    }

    /**
     * Contra quién se jugó, dicho como en la mesa: "Bot intermedio" o el apodo de la otra persona.
     */
    private function rival(Partida $partida, int $asiento): string
    {
        if (! $partida->entre_personas) {
            return 'Bot '.mb_strtolower($partida->nivel_bot->nombre());
        }

        return ($asiento === Mesa::JUGADOR ? $partida->invitado : $partida->jugador)?->apodo ?? 'Alguien que ya no está';
    }

    /**
     * Si la partida no llegó al final, por qué se cerró. Una que terminó a los puntos no necesita aclaración.
     */
    private function comoCerro(Partida $partida, int $asiento): ?string
    {
        if ($partida->estado !== Partida::ABANDONADA) {
            return null;
        }

        $abandono = $partida->eventos()->getQuery()->where('tipo', EventoDePartida::ABANDONO)->reorder('numero', 'desc')->first();
        $porVencimientos = ($abandono?->datos['motivo'] ?? null) === 'vencimientos';

        if ($abandono?->asiento === $asiento) {
            return $porVencimientos ? 'Se te venció el turno '.Mesa::VENCIMIENTOS_PARA_PERDER.' veces.' : 'La abandonaste.';
        }

        return $porVencimientos ? 'Tu rival dejó de jugar.' : 'Tu rival la abandonó.';
    }

    /**
     * El día como se dice hablando: "Hoy", "Ayer" o la fecha.
     */
    private function dia(Carbon $cuando): string
    {
        return match (true) {
            $cuando->isToday() => 'Hoy',
            $cuando->isYesterday() => 'Ayer',
            default => $cuando->locale('es')->isoFormat($cuando->isCurrentYear() ? 'D [de] MMMM' : 'D [de] MMMM [de] YYYY'),
        };
    }
}
