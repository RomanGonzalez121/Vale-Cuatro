<?php

namespace App\Juego;

use App\Events\RevanchaActualizada;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Revancha;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * La revancha: al terminar una partida, cualquiera de los dos la pide y el otro contesta.
 *
 * Contra el bot no hay nada que esperar: el bot quiere siempre y la partida nueva se crea al pedirla.
 * Entre dos personas el pedido queda a la vista del otro durante un minuto. Si lo quiere, nace la partida
 * nueva, con los mismos dos en los mismos asientos y el mano alternado. Si no lo quiere, si no contesta
 * o si quien lo pidió se arrepiente, no se crea nada.
 *
 * Cada jugador la pide una sola vez por partida, y un "no quiero" la cierra para los dos: no se insiste.
 * Si los dos la piden, la segunda vale como aceptar la primera.
 *
 * Todo pasa con la fila de la partida vieja bloqueada: dos toques a la vez (pedir y pedir, aceptar y
 * cancelar) no pueden crear dos partidas ni aceptar un pedido que ya no está.
 */
final class Revanchas
{
    /** Cuánto queda a la vista un pedido antes de vencerse solo. */
    public const SEGUNDOS_PARA_CONTESTAR = 60;

    /** Se puede pedir. */
    public const DISPONIBLE = 'disponible';

    /** La pidió quien pregunta y espera respuesta. */
    public const PEDIDA = 'pedida';

    /** Se la pidieron a quien pregunta. */
    public const TE_PIDEN = 'te_piden';

    /** Ya existe la partida nueva: se sigue en la mesa. */
    public const ACEPTADA = 'aceptada';

    public const RECHAZADA = 'rechazada';

    /** Quien pregunta ya la pidió y no salió: se venció, la canceló o ya no se podía jugar. */
    public const AGOTADA = 'agotada';

    /** No hay revancha posible de esta partida, o alguno de los dos ya está en otra. */
    public const NO_DISPONIBLE = 'no_disponible';

    public function __construct(private readonly Mesa $mesa) {}

    /**
     * En qué está la revancha de una partida, vista desde un asiento.
     *
     * @return array{estado: string, restan: int|null, por: string|null}
     */
    public function estado(Partida $partida, int $asiento): array
    {
        if (! $this->admite($partida)) {
            return $this->en(self::NO_DISPONIBLE);
        }

        if ($this->laQueSiguio($partida) !== null) {
            return $this->en(self::ACEPTADA);
        }

        $pedidos = $this->pedidos($partida);
        $rechazado = $pedidos->firstWhere('estado', Revancha::RECHAZADA);

        if ($rechazado !== null) {
            // El pedido lo rechaza el otro asiento: si era de quien pregunta, se lo rechazó el rival.
            return $this->en(self::RECHAZADA, por: $rechazado->asiento === $asiento ? 'rival' : 'vos');
        }

        $suyo = $pedidos->firstWhere('asiento', 1 - $asiento);

        if ($suyo?->vigente()) {
            return $this->en(self::TE_PIDEN, $this->restan($suyo));
        }

        $propio = $pedidos->firstWhere('asiento', $asiento);

        if ($propio?->vigente()) {
            return $this->en(self::PEDIDA, $this->restan($propio));
        }

        if ($propio !== null) {
            // Por quién no salió: la canceló quien la pidió, el otro no contestó a tiempo, o ninguno de los dos
            // (se quiso cuando alguno ya estaba en otra partida).
            return $this->en(self::AGOTADA, por: match ($propio->estado) {
                Revancha::CANCELADA => 'vos',
                Revancha::CAIDA => null,
                default => 'rival',
            });
        }

        return $this->en($this->estanLibres($partida) ? self::DISPONIBLE : self::NO_DISPONIBLE);
    }

    /**
     * Un jugador pide la revancha. Contra el bot se crea en el momento. Entre personas queda el pedido,
     * salvo que el otro ya la hubiera pedido: ahí los dos la quieren y se crea.
     *
     * @return array{estado: string, restan: int|null, por: string|null}
     */
    public function pedir(Partida $partida, int $asiento): array
    {
        return $this->conLaPartida($partida, $asiento, function (Partida $partida, string $estado) use ($asiento) {
            if ($estado === self::TE_PIDEN) {
                $this->aceptarEl($this->pedidoDe($partida, 1 - $asiento), $partida);

                return;
            }

            if ($estado !== self::DISPONIBLE) {
                return;
            }

            if (! $partida->entre_personas) {
                $this->mesa->revancha($partida);

                return;
            }

            Revancha::create([
                'partida_id' => $partida->getKey(),
                'asiento' => $asiento,
                'vence_en' => now()->addSeconds(self::SEGUNDOS_PARA_CONTESTAR)->startOfSecond(),
            ]);
        });
    }

    /**
     * Quien recibió el pedido lo quiere: nace la partida nueva. Si el pedido ya no está (se venció, se
     * canceló) o alguno de los dos entró a otra partida mientras tanto, no se crea nada.
     *
     * @return array{estado: string, restan: int|null, por: string|null}
     */
    public function aceptar(Partida $partida, int $asiento): array
    {
        return $this->conLaPartida($partida, $asiento, function (Partida $partida, string $estado) use ($asiento) {
            if ($estado === self::TE_PIDEN) {
                $this->aceptarEl($this->pedidoDe($partida, 1 - $asiento), $partida);
            }
        });
    }

    /**
     * Quien recibió el pedido no lo quiere. Queda dicho para los dos, y ya no se puede volver a pedir.
     *
     * @return array{estado: string, restan: int|null, por: string|null}
     */
    public function rechazar(Partida $partida, int $asiento): array
    {
        return $this->conLaPartida($partida, $asiento, function (Partida $partida, string $estado) use ($asiento) {
            if ($estado === self::TE_PIDEN) {
                $this->cambiar($this->pedidoDe($partida, 1 - $asiento), Revancha::RECHAZADA);
            }
        });
    }

    /**
     * Quien la pidió se arrepiente, o se va de la pantalla: el pedido deja de estar a la vista del otro.
     *
     * @return array{estado: string, restan: int|null, por: string|null}
     */
    public function cancelar(Partida $partida, int $asiento): array
    {
        return $this->conLaPartida($partida, $asiento, function (Partida $partida, string $estado) use ($asiento) {
            if ($estado === self::PEDIDA) {
                $this->cambiar($this->pedidoDe($partida, $asiento), Revancha::CANCELADA);
            }
        });
    }

    /**
     * Si de esta partida se puede jugar una revancha: tiene que haber llegado al final (de una que alguien
     * abandonó no hay revancha) y, si era de una serie, la serie tiene que estar cerrada. Mientras la serie
     * sigue no hay nada que pedir: la partida siguiente se reparte sola.
     */
    private function admite(Partida $partida): bool
    {
        if ($partida->estado !== Partida::TERMINADA) {
            return false;
        }

        // En un torneo no hay revancha: quien pierde queda afuera, y a quien gana lo espera otro rival.
        if ($partida->torneo_id !== null) {
            return false;
        }

        if ($partida->entre_personas && $partida->invitado_id === null) {
            return false;
        }

        return $partida->serie === null || $partida->serie->cerrada();
    }

    /**
     * La partida que se jugó después de esta entre los mismos dos, si ya existe.
     */
    private function laQueSiguio(Partida $partida): ?Partida
    {
        return Partida::query()->where('anterior_id', $partida->getKey())->first();
    }

    /**
     * Ninguno de los dos tiene otra partida sin terminar: cada persona juega una sola a la vez.
     */
    private function estanLibres(Partida $partida): bool
    {
        foreach ($this->jugadores($partida) as $jugador) {
            if ($this->mesa->abiertaDe($jugador) !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Las personas sentadas en la partida, siempre en el mismo orden (por número). Con $bloqueadas, sus filas
     * quedan tomadas hasta que termine la transacción.
     *
     * @return Collection<int, Jugador>
     */
    private function jugadores(Partida $partida, bool $bloqueadas = false): Collection
    {
        return Jugador::query()
            ->whereKey(array_filter([$partida->jugador_id, $partida->invitado_id]))
            ->orderBy('id')
            ->when($bloqueadas, fn ($jugadores) => $jugadores->lockForUpdate())
            ->get();
    }

    /**
     * El pedido que hizo ese asiento sobre la partida.
     */
    private function pedidoDe(Partida $partida, int $asiento): Revancha
    {
        return Revancha::query()->where('partida_id', $partida->getKey())->where('asiento', $asiento)->sole();
    }

    /**
     * @return Collection<int, Revancha>
     */
    private function pedidos(Partida $partida): Collection
    {
        return Revancha::query()->where('partida_id', $partida->getKey())->get();
    }

    private function aceptarEl(Revancha $pedido, Partida $partida): void
    {
        // Entre que se pidió y se contestó, alguno pudo entrar a otra partida: ahí ya no hay revancha, y el
        // pedido deja de estar a la vista para que nadie se quede contestando algo que no puede salir.
        if (! $this->estanLibres($partida)) {
            $this->cambiar($pedido, Revancha::CAIDA);

            return;
        }

        $this->cambiar($pedido, Revancha::ACEPTADA);
        $this->mesa->revancha($partida);
    }

    private function cambiar(Revancha $pedido, string $estado): void
    {
        $pedido->estado = $estado;
        $pedido->save();
    }

    private function restan(Revancha $pedido): int
    {
        return max(0, $pedido->vence_en->getTimestamp() - now()->getTimestamp());
    }

    /**
     * @return array{estado: string, restan: int|null, por: string|null}
     */
    private function en(string $estado, ?int $restan = null, ?string $por = null): array
    {
        return ['estado' => $estado, 'restan' => $restan, 'por' => $por];
    }

    /**
     * Corre algo con la partida vieja y sus dos jugadores bloqueados, avisa al otro si hay con quién, y
     * devuelve cómo quedó la revancha para quien hizo el pedido. Lo que corre recibe en qué estaba la
     * revancha para ese asiento, ya leído con todo bloqueado.
     *
     * Los jugadores se bloquean siempre en el mismo orden (por número), y abrir una partida o una sala
     * también bloquea al jugador: así una revancha y un "Jugar" a la vez no pueden terminar en dos partidas abiertas.
     *
     * @param  callable(Partida, string): void  $hacer
     * @return array{estado: string, restan: int|null, por: string|null}
     */
    private function conLaPartida(Partida $partida, int $asiento, callable $hacer): array
    {
        return DB::transaction(function () use ($partida, $asiento, $hacer) {
            $bloqueada = Partida::query()->whereKey($partida->getKey())->lockForUpdate()->firstOrFail();

            $this->jugadores($bloqueada, bloqueadas: true);

            $antes = $this->estado($bloqueada, $asiento);
            $hacer($bloqueada, $antes['estado']);
            $despues = $this->estado($bloqueada, $asiento);

            // El otro se entera por un aviso sin datos: su mesa pregunta en qué quedó la revancha.
            if ($bloqueada->entre_personas && $despues['estado'] !== $antes['estado']) {
                RevanchaActualizada::avisar($bloqueada->getKey());
            }

            return $despues;
        });
    }
}
