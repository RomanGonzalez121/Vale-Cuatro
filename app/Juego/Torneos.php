<?php

namespace App\Juego;

use App\Models\CruceDeTorneo;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Torneo;
use App\Motor\Azar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * El torneo relámpago contra bots: eliminación directa, de cuatro u ocho.
 *
 * Una persona juega sus partidas en la mesa de siempre, contra el bot que le toque. Las partidas entre
 * bots no las mira nadie: se juegan sobre el motor, con los mismos bots, y de ellas se guarda el
 * resultado. Todo su azar sale de la semilla del torneo, así que la misma semilla da siempre el mismo
 * sorteo y los mismos resultados entre bots.
 *
 * Los bots son los jugadores de ejemplo del ranking, cada uno con su nivel. Acá no hacen falta sus
 * filas de la base: alcanzan el apodo y el nivel, que quedan copiados en el torneo.
 *
 * Las llaves avanzan solas (avanzar): cuando la persona termina su partida de una ronda se resuelven
 * las demás de esa ronda y se arma la siguiente. Si quedó afuera, lo que falta se juega de corrido
 * hasta que hay un campeón.
 */
final class Torneos
{
    /** Los tamaños que se ofrecen. */
    public const LUGARES = [4, 8];

    /** A cuántos puntos va cada partida: corta, para que un torneo se juegue en una sentada. */
    public const PUNTOS = 15;

    public function __construct(private readonly Mesa $mesa) {}

    /**
     * Arma un torneo para ese jugador: sortea los bots y las llaves. Si ya tiene uno sin terminar,
     * devuelve ese: una persona juega un torneo a la vez.
     *
     * @param  int|null  $semilla  Para los tests. Sin ella se sortea una con el azar seguro.
     */
    public function crear(Jugador $jugador, int $lugares, ?int $semilla = null): Torneo
    {
        if (! in_array($lugares, self::LUGARES, true)) {
            throw new InvalidArgumentException('Un torneo es de '.implode(' o de ', self::LUGARES).' jugadores.');
        }

        return DB::transaction(function () use ($jugador, $lugares, $semilla) {
            // Se bloquea al jugador: dos toques seguidos no pueden armar dos torneos.
            Jugador::query()->whereKey($jugador->getKey())->lockForUpdate()->first();

            $enCurso = $this->enCursoDe($jugador);

            if ($enCurso !== null) {
                return $enCurso;
            }

            $semilla ??= Azar::seguro()->entero(1, 0x7FFFFFFF);

            $torneo = Torneo::create([
                'jugador_id' => $jugador->getKey(),
                'lugares' => $lugares,
                'puntos' => self::PUNTOS,
                'semilla' => $semilla,
                'inscriptos' => $this->sortear($jugador, $lugares, $semilla),
            ]);

            // Todas las llaves de entrada. La primera ronda sale del sorteo: el lugar 0 contra el 1, el 2
            // contra el 3. Las demás esperan vacías a los ganadores.
            for ($ronda = 1; $ronda <= $torneo->rondas(); $ronda++) {
                for ($orden = 0; $orden < $lugares / 2 ** $ronda; $orden++) {
                    $torneo->cruces()->create([
                        'ronda' => $ronda,
                        'orden' => $orden,
                        'uno' => $ronda === 1 ? $orden * 2 : null,
                        'dos' => $ronda === 1 ? $orden * 2 + 1 : null,
                    ]);
                }
            }

            return $torneo;
        });
    }

    /**
     * El torneo que el jugador tiene sin terminar.
     */
    public function enCursoDe(Jugador $jugador): ?Torneo
    {
        return Torneo::query()->where('jugador_id', $jugador->getKey())->where('estado', Torneo::EN_CURSO)->latest('id')->first();
    }

    /**
     * Un torneo del jugador, por su número. Null si no existe o no es suyo.
     */
    public function deJugador(Jugador $jugador, int $torneoId): ?Torneo
    {
        return Torneo::query()->where('jugador_id', $jugador->getKey())->whereKey($torneoId)->first();
    }

    /**
     * La persona se sienta a jugar su partida de la ronda: se abre contra el bot que le tocó, a los
     * puntos del torneo. Si ya la había empezado, devuelve esa.
     *
     * @throws TorneoNoDisponible si no tiene nada por jugar ahí, o tiene otra partida sin terminar.
     */
    public function jugar(Torneo $torneo): Partida
    {
        // Antes que nada, las llaves al día: si su partida anterior acaba de cerrarse, primero se anota.
        $this->avanzar($torneo->getKey());

        return DB::transaction(function () use ($torneo) {
            $torneo = Torneo::query()->whereKey($torneo->getKey())->lockForUpdate()->firstOrFail();
            $cruce = $torneo->enCurso() ? $this->cruceDeLaPersona($torneo) : null;

            if ($cruce === null) {
                throw new TorneoNoDisponible($torneo->enCurso() ? 'Ya no tenés partidas por jugar en este torneo.' : 'Ese torneo ya terminó.');
            }

            if ($cruce->partida?->enCurso()) {
                return $cruce->partida;
            }

            $rival = $cruce->rivalDe($torneo->lugarDeLaPersona());
            $partida = $this->mesa->abrirEnTorneo($torneo->jugador, $torneo->nivelDe($rival), $torneo->puntos, $torneo->getKey());

            $cruce->partida_id = $partida->getKey();
            $cruce->save();

            return $partida;
        });
    }

    /**
     * La persona deja el torneo. Si está en medio de una partida, la abandona (y la pierde); si estaba
     * entre dos rondas, su rival pasa sin jugar. Lo que falta del torneo se resuelve solo.
     */
    public function abandonar(Torneo $torneo): void
    {
        DB::transaction(function () use ($torneo) {
            $torneo = Torneo::query()->whereKey($torneo->getKey())->lockForUpdate()->firstOrFail();
            $cruce = $torneo->enCurso() ? $this->cruceDeLaPersona($torneo) : null;

            if ($cruce === null) {
                return;
            }

            if ($cruce->partida?->enCurso()) {
                $this->mesa->abandonar($cruce->partida);

                return;
            }

            // Una partida ya cerrada se anota en avanzar(). Si no la empezó, el rival pasa sin jugar.
            if ($cruce->partida === null) {
                $cruce->ganador = $cruce->rivalDe($torneo->lugarDeLaPersona());
                $cruce->por_abandono = true;
                $cruce->save();
            }
        });

        $this->avanzar($torneo->getKey());
    }

    /**
     * Pone las llaves al día. Ronda por ronda: si la persona todavía tiene su partida por jugar o
     * jugándose, se detiene ahí. Si ya la cerró (o quedó afuera antes), se juegan las partidas entre
     * bots de esa ronda, los ganadores pasan a la siguiente y sigue. Con la final resuelta hay campeón.
     *
     * Se puede llamar de más: con las llaves al día no hace nada. La llama un trabajo de la cola cuando
     * se cierra una partida del torneo, y también la pantalla de las llaves, por si ese trabajo no corrió.
     */
    public function avanzar(int $torneoId): void
    {
        DB::transaction(function () use ($torneoId) {
            // Con la fila bloqueada, dos pedidos a la vez no pueden jugar dos veces la misma partida entre bots.
            $torneo = Torneo::query()->whereKey($torneoId)->lockForUpdate()->first();

            if ($torneo === null || ! $torneo->enCurso()) {
                return;
            }

            $persona = $torneo->lugarDeLaPersona();
            $rondas = $torneo->cruces()->get()->groupBy('ronda');

            for ($ronda = 1; $ronda <= $torneo->rondas(); $ronda++) {
                /** @var Collection<int, CruceDeTorneo> $cruces */
                $cruces = $rondas[$ronda]->keyBy('orden');
                $suyo = $cruces->first(fn (CruceDeTorneo $cruce) => ! $cruce->resuelto() && $cruce->loJuega($persona));

                // Los resultados de una ronda se conocen cuando la persona termina la suya: se juegan a la par.
                if ($suyo !== null && ! $this->anotarLaDeLaPersona($suyo, $persona)) {
                    return;
                }

                foreach ($cruces as $cruce) {
                    if (! $cruce->resuelto()) {
                        $this->simular($torneo, $cruce);
                    }
                }

                if ($ronda === $torneo->rondas()) {
                    $torneo->campeon = $cruces[0]->ganador;
                    $torneo->estado = Torneo::TERMINADO;
                    $torneo->terminado_en = now();
                    $torneo->save();

                    return;
                }

                // Los ganadores pasan de a pares: los de los cruces 0 y 1 se encuentran en el 0 de la ronda siguiente.
                $siguientes = $rondas[$ronda + 1]->keyBy('orden');

                foreach ($cruces as $cruce) {
                    $siguiente = $siguientes[intdiv($cruce->orden, 2)];
                    $siguiente->{$cruce->orden % 2 === 0 ? 'uno' : 'dos'} = $cruce->ganador;
                    $siguiente->save();
                }
            }
        });
    }

    /**
     * El cruce que la persona tiene por jugar o jugando. Null si quedó afuera o el torneo terminó.
     */
    public function cruceDeLaPersona(Torneo $torneo): ?CruceDeTorneo
    {
        $lugar = $torneo->lugarDeLaPersona();

        return $torneo->cruces()->get()->first(fn (CruceDeTorneo $cruce) => ! $cruce->resuelto() && $cruce->completo() && $cruce->loJuega($lugar));
    }

    /**
     * El cruce en el que se jugó (o se juega) esa partida, si es de un torneo.
     */
    public function cruceDe(Partida $partida): ?CruceDeTorneo
    {
        if ($partida->torneo_id === null) {
            return null;
        }

        return CruceDeTorneo::query()->where('torneo_id', $partida->torneo_id)->where('partida_id', $partida->getKey())->with('torneo.jugador')->first();
    }

    /**
     * La semilla de la partida entre bots de un cruce: sale de la del torneo y del lugar del cruce en las llaves.
     */
    public function semillaDe(Torneo $torneo, CruceDeTorneo $cruce): int
    {
        return ($torneo->semilla + $cruce->ronda * 1000 + $cruce->orden) % 0x7FFFFFFF;
    }

    /**
     * Quién ocupa cada lugar: la persona y los bots, mezclados. Los bots salen de los jugadores de
     * ejemplo del ranking, elegidos con la semilla.
     *
     * @return list<array{apodo: string|null, nivel: int|null}>
     */
    private function sortear(Jugador $jugador, int $lugares, int $semilla): array
    {
        $azar = Azar::deSemilla($semilla);
        $bots = [];

        foreach (JugadoresDeEjemplo::lista() as $apodo => $nivel) {
            // Si la persona eligió el apodo de uno de ellos, ese no entra: no puede haber dos con el mismo nombre.
            if ($apodo !== $jugador->apodo) {
                $bots[] = ['apodo' => $apodo, 'nivel' => $nivel->value];
            }
        }

        $inscriptos = array_slice($this->mezclar($bots, $azar), 0, $lugares - 1);
        // El lugar de la persona no guarda nada: es el dueño del torneo.
        $inscriptos[] = ['apodo' => null, 'nivel' => null];

        return $this->mezclar($inscriptos, $azar);
    }

    /**
     * Mezcla de Fisher y Yates con el azar que le pasen: con semilla, el orden sale siempre igual.
     *
     * @template T
     *
     * @param  list<T>  $lista
     * @return list<T>
     */
    private function mezclar(array $lista, Azar $azar): array
    {
        for ($i = count($lista) - 1; $i > 0; $i--) {
            $j = $azar->entero(0, $i);
            [$lista[$i], $lista[$j]] = [$lista[$j], $lista[$i]];
        }

        return $lista;
    }

    /**
     * Anota en las llaves cómo le fue a la persona en su partida. Devuelve false si todavía no hay nada
     * que anotar: no la empezó o la está jugando.
     *
     * Pasa solo quien gana su partida. Si la abandonó, o quedó tanto tiempo sin jugarse que la cerró la
     * administración, pasa el bot.
     */
    private function anotarLaDeLaPersona(CruceDeTorneo $cruce, int $persona): bool
    {
        $partida = $cruce->partida;

        if ($partida === null || $partida->estaAbierta()) {
            return false;
        }

        $tanteo = $this->mesa->reconstruir($partida)->tanteo();
        $esUno = $cruce->uno === $persona;

        $cruce->ganador = $partida->ganador === Mesa::JUGADOR ? $persona : $cruce->rivalDe($persona);
        $cruce->puntos_uno = $tanteo[$esUno ? Mesa::JUGADOR : Mesa::BOT];
        $cruce->puntos_dos = $tanteo[$esUno ? Mesa::BOT : Mesa::JUGADOR];
        $cruce->por_abandono = $partida->estado !== Partida::TERMINADA;
        $cruce->save();

        return true;
    }

    /**
     * Juega la partida entre los dos bots de un cruce y anota cómo salió.
     */
    private function simular(Torneo $torneo, CruceDeTorneo $cruce): void
    {
        [, , $ganador, $tanteo] = (new Simulacion)->enElMotor(
            $torneo->nivelDe($cruce->uno),
            $torneo->nivelDe($cruce->dos),
            $this->semillaDe($torneo, $cruce),
            $torneo->puntos,
        );

        $cruce->ganador = $ganador === 0 ? $cruce->uno : $cruce->dos;
        $cruce->puntos_uno = $tanteo[0];
        $cruce->puntos_dos = $tanteo[1];
        $cruce->save();
    }
}
