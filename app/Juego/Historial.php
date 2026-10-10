<?php

namespace App\Juego;

use App\Models\CruceDeTorneo;
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

    /**
     * Lo que ya se contó de cada serie en este pedido: una serie tiene varias partidas en la misma lista.
     *
     * @var array<int, array{marcador: array{0: int, 1: int}, partidas: list<int>, cortada: bool}>
     */
    private array $series = [];

    /**
     * Los cruces de torneo ya buscados en este pedido, por partida.
     *
     * @var array<int, CruceDeTorneo|null>
     */
    private array $cruces = [];

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
            ->with(['jugador', 'invitado', 'serie', 'torneo.jugador'])
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
     * @return array{id: int, gano: bool, vos: int, ellos: int, hasta: int, rival: string, bot: bool, torneo: ?string, cierre: ?string, cuando: Carbon, dia: string, hora: string, manos: int, minutos: int, serie: ?array{id: int, numero: int, vos: int, ellos: int, sigue: bool, gano: ?bool, completa: bool}, revancha: bool}
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
            // A cuántos puntos se jugó: 30, o los 15 de una partida de torneo.
            'hasta' => $partida->puntos,
            'rival' => $this->rival($partida, $asiento),
            'bot' => ! $partida->entre_personas,
            // Si fue de un torneo, qué partido: "Semifinal". En ese caso el rival es un bot con apodo.
            'torneo' => $this->partidoDeTorneo($partida),
            'cierre' => $this->comoCerro($partida, $asiento),
            'cuando' => $cuando,
            'dia' => self::dia($cuando),
            'hora' => $cuando->format('H:i'),
            'manos' => $estado['numeroDeMano'],
            // Una partida que dura menos de un minuto se cuenta como de uno: "0 minutos" no dice nada.
            'minutos' => max(1, (int) round(Carbon::parse($empezo)->diffInSeconds($cuando, true) / 60)),
            'serie' => $serie = $this->serie($partida, $asiento),
            // Una revancha es la que sigue a otra sin ser la continuación de una serie: la primera de una serie nueva también lo es.
            'revancha' => $partida->esContinuacion() && ($serie === null || $serie['numero'] === 1),
        ];
    }

    /**
     * Si la partida es de una serie al mejor de tres: cuál es, qué número de partida fue y cómo quedó la
     * serie vista desde ese asiento. "sigue" dice si todavía se juega. "gano" es null mientras sigue, y
     * también si se cerró sin ganador (la cerró la administración). "completa" dice si
     * se jugó hasta que alguien ganó las que hacían falta. Si alguno se fue en el medio no lo es, aunque la
     * partida abandonada le haya dejado dos al otro: ese marcador no se jugó.
     *
     * @return array{id: int, numero: int, vos: int, ellos: int, sigue: bool, gano: ?bool, completa: bool}|null
     */
    private function serie(Partida $partida, int $asiento): ?array
    {
        $serie = $partida->serie;

        if ($serie === null) {
            return null;
        }

        $contada = $this->series[$serie->id] ??= [
            'marcador' => $serie->marcador(),
            'partidas' => $serie->partidas()->pluck('id')->all(),
            'cortada' => $serie->cortada(),
        ];

        return [
            'id' => $serie->id,
            'numero' => (int) array_search($partida->getKey(), $contada['partidas'], true) + 1,
            'vos' => $contada['marcador'][$asiento],
            'ellos' => $contada['marcador'][1 - $asiento],
            'sigue' => ! $serie->cerrada(),
            // Sin ganador mientras sigue, y también si se cerró sin que la ganara nadie.
            'gano' => $serie->ganador === null ? null : $serie->ganador === $asiento,
            'completa' => $serie->cerrada() && $serie->ganador !== null && ! $contada['cortada'],
        ];
    }

    /**
     * Contra quién se jugó, dicho como en la mesa: "Bot intermedio" o el apodo de la otra persona.
     */
    private function rival(Partida $partida, int $asiento): string
    {
        if (! $partida->entre_personas) {
            $cruce = $this->cruceDeTorneo($partida);

            // En un torneo el bot tiene apodo: es uno de los jugadores de ejemplo del ranking.
            return $cruce === null
                ? 'Bot '.mb_strtolower($partida->nivel_bot->nombre())
                : $partida->torneo->apodoDe($cruce->rivalDe($partida->torneo->lugarDeLaPersona()));
        }

        return ($asiento === Mesa::JUGADOR ? $partida->invitado : $partida->jugador)?->apodo ?? 'Alguien que ya no está';
    }

    /**
     * Qué partido de un torneo fue, o null si la partida no es de un torneo.
     */
    private function partidoDeTorneo(Partida $partida): ?string
    {
        $cruce = $this->cruceDeTorneo($partida);

        return $cruce === null ? null : $partida->torneo->nombreDePartido($cruce->ronda);
    }

    /**
     * El cruce de las llaves en el que se jugó esa partida, si es de un torneo.
     */
    private function cruceDeTorneo(Partida $partida): ?CruceDeTorneo
    {
        if ($partida->torneo === null) {
            return null;
        }

        return $this->cruces[$partida->getKey()] ??= CruceDeTorneo::query()->where('torneo_id', $partida->torneo_id)->where('partida_id', $partida->getKey())->first();
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
    public static function dia(Carbon $cuando): string
    {
        return match (true) {
            $cuando->isToday() => 'Hoy',
            $cuando->isYesterday() => 'Ayer',
            default => $cuando->locale('es')->isoFormat($cuando->isCurrentYear() ? 'D [de] MMMM' : 'D [de] MMMM [de] YYYY'),
        };
    }
}
