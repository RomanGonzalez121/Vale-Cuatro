<?php

namespace App\Juego;

use App\Events\PartidaActualizada;
use App\Jobs\ResolverPlazo;
use App\Jobs\TurnoDelBot;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Serie;
use App\Motor\Accion;
use App\Motor\AccionInvalida;
use App\Motor\Azar;
use App\Motor\Carta;
use App\Motor\Fase;
use App\Motor\Mazo;
use App\Motor\Partida as Motor;
use App\Motor\TipoDeAccion;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * La mesa contra el bot: une la partida guardada con el motor de reglas.
 *
 * En la base no se guarda el estado de la partida sino su historia: cada
 * reparto con sus cartas y cada acción. El estado se reconstruye aplicando esa
 * historia al motor. Todo lo que cambia una partida pasa por acá, dentro de
 * una transacción que bloquea su fila, para que dos pedidos a la vez (o un
 * pedido y el job del bot) no puedan guardar la misma jugada dos veces.
 *
 * El bot no juega dentro del pedido del jugador: su turno sale de un job en
 * cola (TurnoDelBot), una acción por vez.
 */
final class Mesa
{
    public const JUGADOR = 0;

    public const BOT = 1;

    /** Cuánto espera una sala a que se siente alguien antes de cerrarse sola. */
    public const MINUTOS_DE_SALA = 30;

    /** Entre personas: cuánto tiene quien debe jugar o contestar antes de que el servidor lo mande al mazo. */
    public const SEGUNDOS_DE_TURNO = 45;

    /**
     * Entre personas: lo que tiene para su turno quien abrió la sala mientras todavía no llegó a la mesa. El rival
     * puede sentarse cuando esa persona está mandando el link desde otra aplicación: no se le hace perder una mano
     * que no vio, pero tampoco se deja al invitado esperando para siempre.
     */
    public const SEGUNDOS_DE_LLEGADA = 180;

    /** Entre personas: cuánto se espera, con la mano cerrada, antes de repartir la siguiente. Tocar la mesa lo apura. */
    public const SEGUNDOS_PARA_REPARTIR = 6;

    /** Entre personas: con tantos vencimientos seguidos, sin ninguna jugada propia en el medio, se pierde la partida. */
    public const VENCIMIENTOS_PARA_PERDER = 3;

    /**
     * Cada partida juega contra el bot de su nivel. Un bot fijo sirve para los tests, que necesitan mirar qué recibe.
     */
    public function __construct(private readonly ?Bot $botFijo = null) {}

    /**
     * La partida en curso del jugador. Si no tiene ninguna, crea una contra ese nivel y reparte la primera mano.
     * Con una partida sin terminar se retoma esa, con su nivel, aunque se pida otro.
     *
     * Con $enSerie, la partida nueva es la primera de una serie al mejor de tres contra ese nivel.
     */
    public function abrir(Jugador $jugador, ?Nivel $nivel = null, bool $enSerie = false): Partida
    {
        return DB::transaction(function () use ($jugador, $nivel, $enSerie) {
            // Se bloquea al jugador: dos toques seguidos en "Jugar" no pueden abrir dos partidas.
            Jugador::query()->whereKey($jugador->getKey())->lockForUpdate()->first();

            // Una sola partida sin terminar por persona: también cuenta una sala que todavía espera rival.
            $enCurso = $this->abiertaDe($jugador);

            if ($enCurso !== null) {
                return $enCurso;
            }

            // El primer mano se sortea acá, con el azar seguro: el motor lo recibe como dato.
            $partida = new Partida([
                'jugador_id' => $jugador->getKey(),
                'primer_mano' => Azar::seguro()->entero(self::JUGADOR, self::BOT),
                'puntos' => 30,
                'nivel_bot' => $nivel ?? Nivel::porDefecto(),
            ]);

            // La serie no se asigna en masa: solo la decide el código de la mesa.
            $partida->serie_id = $enSerie ? Serie::create()->getKey() : null;
            $partida->save();

            $this->repartirEn($partida, $this->reconstruir($partida));

            return $partida;
        });
    }

    /**
     * La partida que el jugador tiene en juego: la suya contra el bot, o la que comparte con otra persona,
     * sea que la creó o que se sentó con el link.
     */
    public function enCursoDe(Jugador $jugador): ?Partida
    {
        return $this->delJugador($jugador)->where('estado', Partida::EN_CURSO)->latest('id')->first();
    }

    /**
     * La partida sin terminar del jugador, sea que ya se juega o que todavía espera rival.
     * Una persona tiene una sola a la vez, contra el bot o contra otra persona.
     */
    public function abiertaDe(Jugador $jugador): ?Partida
    {
        return $this->delJugador($jugador)->whereIn('estado', [Partida::ESPERANDO, Partida::EN_CURSO])->latest('id')->first();
    }

    /**
     * La última partida que el jugador tuvo en la mesa, esté en curso o no: sirve para contarle cómo terminó.
     *
     * No es la del número más alto. Una sala recibe su número cuando se abre y se empieza a jugar después:
     * quien se sienta en ella puede tener una partida más nueva, contra el bot, que abandonó para entrar.
     * La última es la que se tocó por última vez.
     */
    public function ultimaDe(Jugador $jugador): ?Partida
    {
        return $this->delJugador($jugador)->latest('updated_at')->latest('id')->first();
    }

    /**
     * Una partida del jugador, por su número: la que su mesa dice estar mostrando. Null si no existe o
     * si el jugador no ocupa un asiento en ella.
     */
    public function deJugador(Jugador $jugador, int $partidaId): ?Partida
    {
        return $this->delJugador($jugador)->whereKey($partidaId)->first();
    }

    /**
     * Las partidas en las que el jugador ocupa un asiento.
     *
     * @return Builder<Partida>
     */
    private function delJugador(Jugador $jugador): Builder
    {
        return Partida::query()->where(
            fn (Builder $consulta) => $consulta->where('jugador_id', $jugador->getKey())->orWhere('invitado_id', $jugador->getKey()),
        );
    }

    /**
     * Desde qué asiento se mira o se juega. Si no se dice, vale solo en una partida contra el bot, donde
     * el jugador es el asiento 0: en una partida entre personas hay que decirlo, porque suponer uno
     * dejaría a un jugador mirando o moviendo las cartas del otro.
     */
    private function asientoPara(Partida $partida, ?int $asiento): int
    {
        if ($asiento === null) {
            if ($partida->entre_personas) {
                throw new LogicException('En una partida entre personas hay que decir desde qué asiento se mira o se juega.');
            }

            return self::JUGADOR;
        }

        // Contra el bot, el único asiento de una persona es el 0: el 1 es del bot y lo mueve su turno.
        $validos = $partida->entre_personas ? [self::JUGADOR, self::BOT] : [self::JUGADOR];

        if (! in_array($asiento, $validos, true)) {
            throw new InvalidArgumentException("El asiento {$asiento} no es de una persona en esta partida.");
        }

        return $asiento;
    }

    /**
     * El estado de la partida, armado de cero con sus eventos.
     */
    public function reconstruir(Partida $partida): Motor
    {
        $motor = $this->sinJugar($partida);

        foreach ($partida->eventos()->get() as $evento) {
            $motor = $this->aplicarEvento($motor, $evento);
        }

        return $motor;
    }

    /**
     * La partida contada de nuevo: después de cada evento, en orden, ese evento y cómo quedó el motor.
     * Lo usa la repetición del historial, que así no guarda nada: sale de los mismos eventos que el juego.
     *
     * @return iterable<int, array{0: EventoDePartida, 1: Motor}>
     */
    public function pasoAPaso(Partida $partida): iterable
    {
        $motor = $this->sinJugar($partida);

        foreach ($partida->eventos()->get() as $evento) {
            $motor = $this->aplicarEvento($motor, $evento);

            yield [$evento, $motor];
        }
    }

    /**
     * Lo que ve un jugador: la vista de su asiento del motor, que no trae las cartas del rival, y el
     * número del último evento. Sin decir el asiento vale la partida contra el bot, donde es el 0.
     *
     * @return array<string, mixed>
     */
    public function vista(Partida $partida, ?int $asiento = null): array
    {
        $asiento = $this->asientoPara($partida, $asiento);

        return $this->enUnaFoto($partida, function () use ($partida, $asiento) {
            $motor = $this->sinJugar($partida);
            $ultimo = null;

            // El estado y el número salen de la misma lectura: si el rival juega justo ahora, no pueden quedar desparejos.
            foreach ($partida->eventos()->get() as $evento) {
                $motor = $this->aplicarEvento($motor, $evento);
                $ultimo = $evento;
            }

            return $this->paso($partida, $motor, $ultimo->numero ?? 0, $asiento, $ultimo);
        });
    }

    /**
     * Lo que pasó después de un evento: la vista de ese asiento tras cada evento posterior, en orden.
     * Así se entera la mesa de las jugadas del bot o del rival, que juegan aparte.
     *
     * @return list<array<string, mixed>>
     */
    public function pasosDesde(Partida $partida, int $desde, ?int $asiento = null): array
    {
        $asiento = $this->asientoPara($partida, $asiento);

        // Casi todas las consultas llegan antes de que el otro juegue: ahí no hace falta reconstruir nada.
        if ((int) $partida->eventos()->max('numero') <= $desde) {
            return [];
        }

        return $this->enUnaFoto($partida, function () use ($partida, $desde, $asiento) {
            $motor = $this->sinJugar($partida);
            $pasos = [];

            foreach ($partida->eventos()->get() as $evento) {
                $motor = $this->aplicarEvento($motor, $evento);

                // Un abandono no cambia lo que hay en la mesa: no es un paso para mostrar.
                if ($evento->numero > $desde && $evento->tipo !== EventoDePartida::ABANDONO) {
                    $pasos[] = $this->paso($partida, $motor, $evento->numero, $asiento, $evento);
                }
            }

            return $pasos;
        });
    }

    /**
     * Lee la partida y sus eventos de una misma foto de la base. La fila trae el plazo que corre y los eventos
     * dicen a quién le toca: leídos en momentos distintos, si alguien juega en el medio, la mesa recibiría el turno
     * nuevo con lo que le quedaba al plazo viejo y la cuenta regresiva arrancaría casi consumida.
     *
     * @template T
     *
     * @param  Closure(): T  $leer
     * @return T
     */
    private function enUnaFoto(Partida $partida, Closure $leer): mixed
    {
        return DB::transaction(function () use ($partida, $leer) {
            // Dentro de la transacción, la primera lectura fija la foto: la fila primero y los eventos después.
            $partida->refresh();

            return $leer();
        });
    }

    /**
     * Un jugador hace algo desde su asiento. Si el motor lo acepta se guarda, y si después le toca al bot se
     * le deja el turno en la cola. Devuelve el paso de esa jugada, visto desde ese asiento: las del bot o del
     * rival llegan por pasosDesde().
     *
     * Con $desde, la mesa dice cuál fue el último evento que mostró: si después pasó algo (entre personas, el
     * servidor resolvió un plazo), la jugada se rechaza. Sin eso, un canto tocado mirando una mano podía entrar
     * en la siguiente, que la persona todavía no vio.
     *
     * @return list<array<string, mixed>>
     *
     * @throws AccionInvalida si el reglamento no lo permite o la mesa quedó atrasada: en ese caso no se guarda nada.
     */
    public function actuar(Partida $partida, Accion $accion, ?int $asiento = null, ?int $desde = null): array
    {
        $asiento = $this->asientoPara($partida, $asiento);

        return $this->conLaPartida($partida, function (Partida $partida, Motor $motor) use ($accion, $asiento) {
            $motor = $motor->aplicar($asiento, $accion);
            $evento = $this->guardar($partida, EventoDePartida::ACCION, $asiento, $accion->aArray());
            $this->despuesDeJugar($partida, $motor, $evento);

            return [$this->paso($partida, $motor, $evento, $asiento)];
        }, $desde);
    }

    /**
     * Reparte la mano siguiente. Solo vale entre dos manos. Con $desde, igual que en actuar(): no se reparte
     * sobre una mano que la mesa todavía no mostró.
     *
     * @return list<array<string, mixed>>
     *
     * @throws AccionInvalida si la mano todavía se está jugando o la mesa quedó atrasada.
     */
    public function repartir(Partida $partida, ?int $asiento = null, ?int $desde = null): array
    {
        $asiento = $this->asientoPara($partida, $asiento);

        return $this->conLaPartida($partida, fn (Partida $partida, Motor $motor) => $this->repartirEn($partida, $motor, $asiento), $desde);
    }

    /**
     * Un jugador deja la partida: queda cerrada como perdida para él y ganada para el otro asiento,
     * con todo lo jugado intacto.
     */
    public function abandonar(Partida $partida, ?int $asiento = null): void
    {
        $asiento = $this->asientoPara($partida, $asiento);

        $this->conLaPartida($partida, function (Partida $partida) use ($asiento) {
            $this->guardar($partida, EventoDePartida::ABANDONO, $asiento, []);
            $this->cerrar($partida, Partida::ABANDONADA, 1 - $asiento);

            return [];
        });
    }

    /**
     * Abre una sala para jugar con otra persona: queda esperando, con un link para mandarle.
     * Si el jugador ya tiene una partida sin terminar (esta misma sala u otra), devuelve esa:
     * un toque repetido no abre dos salas, igual que con la partida contra el bot.
     *
     * Con $enSerie, lo que se abre es una serie al mejor de tres: la sala es su primera partida.
     */
    public function crearSala(Jugador $jugador, bool $enSerie = false): Partida
    {
        return DB::transaction(function () use ($jugador, $enSerie) {
            // Se bloquea al jugador: dos toques seguidos no pueden abrir dos salas.
            Jugador::query()->whereKey($jugador->getKey())->lockForUpdate()->first();

            $abierta = $this->abiertaDe($jugador);

            if ($abierta !== null) {
                return $abierta;
            }

            $sala = new Partida([
                'jugador_id' => $jugador->getKey(),
                'puntos' => 30,
                'entre_personas' => true,
                'codigo' => Partida::codigoNuevo(),
                'nivel_bot' => null,
            ]);

            // El estado no se asigna en masa: solo lo cambia el código de la mesa.
            $sala->estado = Partida::ESPERANDO;
            $sala->serie_id = $enSerie ? Serie::create()->getKey() : null;
            $sala->save();

            return $sala;
        });
    }

    /**
     * Quien abrió el link se sienta en el asiento 1: se sortea quién es mano y se reparte la primera mano.
     * Volver a abrir el link de una partida que ya es suya no hace nada (recargar, o quien abrió la sala tocando su
     * propio link), y devuelve la partida como está.
     *
     * @throws SalaNoDisponible si ya tiene rival, ya terminó o el jugador tiene otra partida sin terminar.
     */
    public function sentarse(string $codigo, Jugador $jugador): Partida
    {
        return DB::transaction(function () use ($codigo, $jugador) {
            // Se bloquea al jugador y después a la sala: dos toques, o dos personas a la vez, no pueden sentarse dos veces.
            Jugador::query()->whereKey($jugador->getKey())->lockForUpdate()->first();

            $partida = Partida::query()->where('codigo', $codigo)->where('entre_personas', true)->lockForUpdate()->firstOrFail();

            if ($partida->asientoDe($jugador) !== null) {
                return $partida;
            }

            if (! $partida->esperando()) {
                throw new SalaNoDisponible($partida->enCurso() ? 'Esa partida ya tiene sus dos jugadores.' : 'Esa partida ya terminó.');
            }

            if ($this->abiertaDe($jugador) !== null) {
                throw new SalaNoDisponible('Tenés otra partida sin terminar. Terminala o abandonala antes de entrar a esta.');
            }

            $partida->invitado_id = $jugador->getKey();
            $partida->primer_mano = Azar::seguro()->entero(self::JUGADOR, self::BOT);
            $partida->estado = Partida::EN_CURSO;
            $partida->save();

            $this->repartirEn($partida, $this->reconstruir($partida), self::BOT);

            return $partida;
        });
    }

    /**
     * Quien abrió la sala la cierra antes de que se siente alguien. Devuelve false si justo se sentó
     * otra persona: ahí la partida ya empezó y no se cancela, se abandona desde la mesa.
     */
    public function cancelarSala(Partida $partida): bool
    {
        return DB::transaction(function () use ($partida) {
            $bloqueada = Partida::query()->whereKey($partida->getKey())->lockForUpdate()->firstOrFail();

            if (! $bloqueada->esperando()) {
                return false;
            }

            $this->cerrar($bloqueada, Partida::ABANDONADA, null);

            return true;
        });
    }

    /**
     * Cierra las salas que esperaron más de MINUTOS_DE_SALA sin que se sentara nadie. Devuelve cuántas.
     * Una sola consulta con el estado en la condición: si alguien se sienta justo ahora, esa sala no se toca.
     */
    public function cerrarSalasVencidas(): int
    {
        $cerradas = Partida::query()
            ->where('estado', Partida::ESPERANDO)
            ->where('created_at', '<', now()->subMinutes(self::MINUTOS_DE_SALA))
            ->update(['estado' => Partida::ABANDONADA, 'terminada_en' => now()]);

        // Una sala al mejor de tres que nadie ocupó no llegó a ser una serie: se va. Se buscan las series sin
        // ninguna partida viva ni jugada, y no "las de las salas que se cerraron": así, si alguien se sentó
        // justo ahora, su serie no se toca. De paso se van las que quedaron sin ninguna partida: al borrarse
        // un invitado que no volvió se borran sus partidas, y la serie que las agrupaba queda sola.
        Serie::query()
            ->where(fn (Builder $sinNada) => $sinNada
                ->whereDoesntHave('partidas')
                ->orWhere(fn (Builder $abierta) => $abierta
                    ->whereNull('terminada_en')
                    ->whereDoesntHave('partidas', fn (Builder $partidas) => $partidas->where(
                        fn (Builder $viva) => $viva->whereIn('estado', [Partida::ESPERANDO, Partida::EN_CURSO])->orWhereNotNull('ganador'),
                    ))))
            ->delete();

        return $cerradas;
    }

    /**
     * La revancha de una partida que ya terminó: otra entre los mismos dos, en los mismos asientos, contra
     * el mismo nivel si era contra el bot, y ya repartida. Si la anterior cerró una serie, la revancha es
     * otra serie igual. Quién puede pedirla y cuándo lo decide Revanchas: esto solo la crea.
     */
    public function revancha(Partida $anterior): Partida
    {
        return DB::transaction(function () use ($anterior) {
            $serie = $anterior->serie === null ? null : Serie::create(['al_mejor_de' => $anterior->serie->al_mejor_de]);

            return $this->siguiente($anterior, $serie);
        });
    }

    /**
     * La partida que sigue a otra entre los mismos dos: la próxima de una serie, o una revancha.
     * Cada uno conserva su asiento, y arranca siendo mano quien no lo fue al empezar la anterior.
     */
    private function siguiente(Partida $anterior, ?Serie $serie): Partida
    {
        $partida = new Partida([
            'jugador_id' => $anterior->jugador_id,
            'invitado_id' => $anterior->invitado_id,
            'primer_mano' => 1 - $anterior->primer_mano,
            'puntos' => $anterior->puntos,
            'entre_personas' => $anterior->entre_personas,
            // El código es el del link de invitación. Acá nadie lo usa, pero toda partida entre personas tiene el suyo.
            'codigo' => $anterior->entre_personas ? Partida::codigoNuevo() : null,
            'nivel_bot' => $anterior->nivel_bot,
        ]);

        $partida->serie_id = $serie?->getKey();
        $partida->anterior_id = $anterior->getKey();
        $partida->save();

        $this->repartirEn($partida, $this->reconstruir($partida));

        return $partida;
    }

    /**
     * Lo que le pasa a una serie cuando se cierra una de sus partidas: si alguien ya ganó las que hacen
     * falta, se cierra; si no, se reparte la partida siguiente en el momento, sin que nadie la pida.
     *
     * Quien abandona una partida pierde la serie entera: no va a estar para la siguiente, y el otro no
     * se queda esperando una partida que no empieza. Vale igual para quien deja vencer sus turnos.
     */
    private function seguirLaSerie(Partida $partida): void
    {
        $serie = $partida->serie;

        if ($serie === null || $serie->cerrada()) {
            return;
        }

        // Una sala que se cerró sin rival no llegó a ser una serie.
        if ($partida->ganador === null) {
            $serie->delete();

            return;
        }

        $abandonada = $partida->estado === Partida::ABANDONADA;
        $marcador = $serie->marcador();

        if (! $abandonada && max($marcador) < $serie->necesarias()) {
            $this->siguiente($partida, $serie);

            return;
        }

        $serie->ganador = $abandonada ? $partida->ganador : ($marcador[self::JUGADOR] > $marcador[self::BOT] ? self::JUGADOR : self::BOT);
        $serie->terminada_en = now();
        $serie->save();
    }

    /**
     * El bot juega una sola acción, si le toca. Lo llama el job de la cola.
     *
     * Antes de jugar vuelve a mirar la partida con la fila bloqueada. Si el job llega repetido o
     * tarde (la partida terminó, el bot ya jugó, le toca al jugador) no hace nada. Devuelve si jugó.
     */
    public function turnoDelBot(int $partidaId): bool
    {
        return DB::transaction(function () use ($partidaId) {
            $partida = Partida::query()->whereKey($partidaId)->lockForUpdate()->first();

            // Entre personas no hay bot: el asiento 1 es de alguien y juega por su cuenta.
            if ($partida === null || ! $partida->enCurso() || $partida->entre_personas) {
                return false;
            }

            $motor = $this->reconstruir($partida);

            if (! $this->leTocaAlBot($motor)) {
                return false;
            }

            [$accion, $motor] = $this->jugadaDelBot($partida, $motor);

            $evento = $this->guardar($partida, EventoDePartida::ACCION, self::BOT, $accion->aArray());
            $this->despuesDeJugar($partida, $motor, $evento);

            return true;
        });
    }

    /**
     * Resuelve lo que una partida entre personas estaba esperando, si ya se cumplió el plazo:
     *
     * - con la mano cerrada, reparte la siguiente;
     * - si a alguien le tocaba jugar o contestar y no lo hizo, el servidor lo manda al mazo (la jugada que el
     *   reglamento ya tiene: con un canto sin contestar vale como no querer y perder la mano). Con
     *   VENCIMIENTOS_PARA_PERDER seguidos, sin ninguna jugada propia en el medio, pierde la partida.
     *
     * Lo llama el trabajo de la cola y también la red de seguridad de la mesa. Con $evento, no hace nada si desde
     * entonces pasó algo (alguien jugó o apuró el reparto). Antes del plazo tampoco: por eso es seguro llamarlo de más.
     * Devuelve si resolvió algo.
     */
    public function resolverPlazo(int $partidaId, ?int $evento = null): bool
    {
        return DB::transaction(function () use ($partidaId, $evento) {
            $partida = Partida::query()->whereKey($partidaId)->lockForUpdate()->first();

            if ($partida === null || ! $partida->enCurso() || ! $partida->entre_personas || $partida->plazo_vence_en === null) {
                return false;
            }

            if (now()->getTimestamp() < $partida->plazo_vence_en->getTimestamp()) {
                return false;
            }

            if ($evento !== null && (int) $partida->eventos()->max('numero') !== $evento) {
                return false;
            }

            $motor = $this->reconstruir($partida);

            if ($motor->fase() === Fase::PorRepartir) {
                $this->repartirEn($partida, $motor);

                return true;
            }

            $asiento = $this->quienTieneElTurno($motor);

            if ($asiento === null) {
                return false;
            }

            if ($this->vencimientosSeguidos($partida, $asiento) + 1 >= self::VENCIMIENTOS_PARA_PERDER) {
                $this->guardar($partida, EventoDePartida::ABANDONO, $asiento, ['motivo' => 'vencimientos']);
                $this->cerrar($partida, Partida::ABANDONADA, 1 - $asiento);

                return true;
            }

            $accion = $this->accionPorVencimiento($motor, $asiento);
            $motor = $motor->aplicar($asiento, $accion);
            $vencimiento = $this->guardar($partida, EventoDePartida::VENCIMIENTO, $asiento, $accion->aArray());
            $this->despuesDeJugar($partida, $motor, $vencimiento);

            return true;
        });
    }

    /**
     * Quien abrió la sala tiene la mesa a la vista por primera vez (o cualquiera de los dos, en una partida que
     * sigue a otra). Hasta ahora su turno corría con el plazo de espera (SEGUNDOS_DE_LLEGADA); desde acá corre
     * con el de siempre, y si justo le toca, arranca de cero.
     *
     * Queda como un evento de la partida: así el rival se entera por el mismo camino que de una jugada, y los
     * números de evento siguen diciendo qué vio cada mesa. Llamarlo de más no hace nada. Devuelve si anotó algo.
     */
    public function llegar(Partida $partida, int $asiento): bool
    {
        return DB::transaction(function () use ($partida, $asiento) {
            $bloqueada = Partida::query()->whereKey($partida->getKey())->lockForUpdate()->firstOrFail();

            if (! $bloqueada->enCurso() || ! $this->faltaLlegar($bloqueada, $asiento)) {
                return false;
            }

            $motor = $this->reconstruir($bloqueada);
            $evento = $this->guardar($bloqueada, EventoDePartida::LLEGADA, $asiento, []);

            if ($motor->fase() === Fase::Jugando && $this->quienTieneElTurno($motor) === $asiento) {
                // Le toca a quien acaba de llegar: su turno de verdad empieza ahora.
                $this->esperarPlazo($bloqueada, $motor, $evento);
            } elseif ($bloqueada->plazo_vence_en !== null) {
                // Lo que se espera no cambia de hora. Pero el trabajo que lo resuelve lleva el número del último
                // evento, que acaba de cambiar: se deja otro a la misma hora, y el anterior ya no hace nada.
                ResolverPlazo::dispatch($bloqueada->getKey(), $evento)->delay($bloqueada->plazo_vence_en)->afterCommit();
            }

            return true;
        });
    }

    /**
     * Si a ese asiento todavía le falta llegar a la mesa. En una partida que nace de una sala solo le puede
     * faltar a quien la abrió, que espera en otra pantalla: quien se sienta con el link entra directo a la mesa.
     * En la que sigue a otra (la siguiente de una serie, o una revancha) le puede faltar a cualquiera de los
     * dos: se reparte sola y cada uno llega cuando deja de mirar el final de la anterior. Cuenta como haber
     * llegado avisarlo, haber jugado o que ya se le haya vencido un turno: la espera larga es una sola.
     */
    public function faltaLlegar(Partida $partida, int $asiento): bool
    {
        if (! $partida->entre_personas || ($asiento !== self::JUGADOR && ! $partida->esContinuacion())) {
            return false;
        }

        return ! $partida->eventos()->getQuery()
            ->where('asiento', $asiento)
            ->whereIn('tipo', [EventoDePartida::LLEGADA, EventoDePartida::ACCION, EventoDePartida::VENCIMIENTO])
            ->exists();
    }

    /**
     * Cuántas veces seguidas dejó vencer su turno un asiento, contando desde su última jugada propia hacia atrás.
     * Lo que hace el otro asiento en el medio no corta la cuenta: cada uno lleva la suya.
     */
    private function vencimientosSeguidos(Partida $partida, int $asiento): int
    {
        $tipos = $partida->eventos()->getQuery()->reorder('numero', 'desc')
            ->where('asiento', $asiento)
            ->whereIn('tipo', [EventoDePartida::ACCION, EventoDePartida::VENCIMIENTO])
            // Más de los que hacen perder la partida no hace falta contar.
            ->limit(self::VENCIMIENTOS_PARA_PERDER)
            ->pluck('tipo');

        $seguidos = 0;

        foreach ($tipos as $tipo) {
            if ($tipo !== EventoDePartida::VENCIMIENTO) {
                break;
            }

            $seguidos++;
        }

        return $seguidos;
    }

    /**
     * Lo que se hace por quien se quedó sin tiempo: irse al mazo. Si en ese momento el mazo no está entre sus
     * acciones, no querer, y si tampoco, lo primero que el motor le permita: la partida no puede quedar trabada.
     */
    private function accionPorVencimiento(Motor $motor, int $asiento): Accion
    {
        $validas = $motor->accionesPara($asiento);

        foreach ([TipoDeAccion::Mazo, TipoDeAccion::NoQuiero] as $preferida) {
            foreach ($validas as $valida) {
                if ($valida->tipo === $preferida) {
                    return $valida;
                }
            }
        }

        return $validas[0];
    }

    /**
     * La red de seguridad: si el turno del bot no salió de la cola (el proceso que la atiende está
     * caído o atrasado), la mesa lo pide y el bot juega en el momento todo lo que le toque.
     * Usa el mismo camino que el job, así que si el job llega después no encuentra nada que hacer.
     */
    public function despertarAlBot(Partida $partida): void
    {
        // Ninguna mano necesita tantas jugadas seguidas de un mismo lado: es solo un tope.
        for ($jugadas = 0; $jugadas < 20; $jugadas++) {
            if (! $this->turnoDelBot($partida->getKey())) {
                return;
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function repartirEn(Partida $partida, Motor $motor, int $asiento = self::JUGADOR): array
    {
        // El mazo se mezcla con el azar seguro y las cartas repartidas quedan en el evento.
        $motor = $motor->repartir(Mazo::mezcladoCon(Azar::seguro()));
        $evento = $this->guardar($partida, EventoDePartida::REPARTO, null, ['manos' => $motor->aArray()['cartas']]);
        $this->despuesDeJugar($partida, $motor, $evento);

        return [$this->paso($partida, $motor, $evento, $asiento)];
    }

    /**
     * Lo que sigue a cualquier jugada guardada: cerrar la partida si alguien llegó a los puntos,
     * o dejarle el turno al bot si ahora le toca a él (solo contra el bot).
     */
    private function despuesDeJugar(Partida $partida, Motor $motor, int $evento): void
    {
        if ($motor->fase() === Fase::Terminada) {
            $this->cerrar($partida, Partida::TERMINADA, $motor->ganador());

            return;
        }

        if ($partida->entre_personas) {
            $this->esperarPlazo($partida, $motor, $evento);

            return;
        }

        if ($this->leTocaAlBot($motor)) {
            // El job sale recién cuando la jugada quedó guardada. No lleva demora: el bot juega apenas le toca, y
            // la pausa para que parezca que piensa la pone la mesa del navegador, según el ritmo que eligió quien juega.
            TurnoDelBot::dispatch($partida->getKey())->afterCommit();
        }
    }

    /**
     * Entre personas, deja anotado cuándo se resuelve solo lo que la partida está esperando (que alguien
     * juegue, o que se reparta la mano siguiente) y deja en la cola el trabajo que lo resuelve entonces.
     *
     * El plazo se guarda en segundos enteros y el trabajo sale exactamente a esa hora: así la base y la cola
     * miden lo mismo y un redondeo no puede hacer que el trabajo llegue un segundo antes y no encuentre nada.
     */
    private function esperarPlazo(Partida $partida, Motor $motor, int $evento): void
    {
        $conTurno = $motor->fase() === Fase::Jugando ? $this->quienTieneElTurno($motor) : null;

        $segundos = match (true) {
            $motor->fase() === Fase::PorRepartir => self::SEGUNDOS_PARA_REPARTIR,
            $conTurno === null => null,
            // A quien abrió la sala y todavía no llegó a la mesa se lo espera más, una sola vez.
            $this->faltaLlegar($partida, $conTurno) => self::SEGUNDOS_DE_LLEGADA,
            default => self::SEGUNDOS_DE_TURNO,
        };

        $vence = $segundos === null ? null : now()->addSeconds($segundos)->startOfSecond();

        $partida->plazo_vence_en = $vence;
        $partida->save();

        if ($vence !== null) {
            ResolverPlazo::dispatch($partida->getKey(), $evento)->delay($vence)->afterCommit();
        }
    }

    private function leTocaAlBot(Motor $motor): bool
    {
        return $motor->fase() === Fase::Jugando && $motor->accionesPara(self::BOT) !== [];
    }

    private function sinJugar(Partida $partida): Motor
    {
        return Motor::nueva(2, $partida->primer_mano, $partida->puntos);
    }

    private function aplicarEvento(Motor $motor, EventoDePartida $evento): Motor
    {
        return match ($evento->tipo) {
            EventoDePartida::REPARTO => $motor->conManos(array_map(
                fn (array $mano) => array_map(Carta::de(...), $mano),
                $evento->datos['manos'],
            )),
            // Un vencimiento es la jugada que hizo el servidor por quien se quedó sin tiempo: para el motor, una acción más.
            EventoDePartida::ACCION, EventoDePartida::VENCIMIENTO => $motor->aplicar($evento->asiento, Accion::desdeArray($evento->datos)),
            // Un abandono cierra la partida sin cambiar lo que se jugó.
            default => $motor,
        };
    }

    /**
     * Un paso es la vista de un asiento después de un evento, con el número de ese evento y el de
     * la partida. Con esos dos números la mesa pregunta qué pasó después, y el servidor sabe si
     * la pestaña que pregunta sigue mostrando la última partida.
     *
     * Es lo único que sale hacia un navegador: la vista de un asiento nunca trae las cartas del otro.
     *
     * @return array<string, mixed>
     */
    private function paso(Partida $partida, Motor $motor, int $evento, int $asiento, ?EventoDePartida $guardado = null): array
    {
        $paso = [...$motor->vistaPara($asiento), 'partida' => $partida->getKey(), 'evento' => $evento];

        // Una llegada no cambia la mesa: el motor sigue con los hechos de la jugada anterior, que ya se contaron.
        if ($guardado?->tipo === EventoDePartida::LLEGADA) {
            $paso['hechos'] = [];
        }

        // Entre personas, cuántos segundos faltan para que el servidor resuelva la espera: el turno o el reparto.
        // La mesa lo usa para la cuenta regresiva. Y si esta jugada la hizo el servidor porque a alguien se le
        // venció el turno, a qué asiento: así la mesa lo cuenta como lo que fue y no como un mazo elegido.
        // Contra el bot no hay plazo y el paso queda como siempre.
        if ($partida->entre_personas) {
            $paso['restan'] = $this->restan($partida, $motor);
            $paso['vencio'] = $guardado?->tipo === EventoDePartida::VENCIMIENTO ? $guardado->asiento : null;
        }

        return $paso;
    }

    /**
     * Los segundos que faltan para que se resuelva la espera actual, o null si no hay nada que esperar.
     */
    private function restan(Partida $partida, Motor $motor): ?int
    {
        $espera = $motor->fase() === Fase::PorRepartir || ($motor->fase() === Fase::Jugando && $this->quienTieneElTurno($motor) !== null);

        if (! $espera || $partida->plazo_vence_en === null) {
            return null;
        }

        return max(0, $partida->plazo_vence_en->getTimestamp() - now()->getTimestamp());
    }

    /**
     * El asiento al que le toca jugar o contestar, o null si nadie (la mano está cerrada o terminó). Es uno solo.
     */
    private function quienTieneElTurno(Motor $motor): ?int
    {
        foreach ([self::JUGADOR, self::BOT] as $asiento) {
            if ($motor->accionesPara($asiento) !== []) {
                return $asiento;
            }
        }

        return null;
    }

    /**
     * Lo que juega el bot. Si alguna vez fallara (un error suyo, o una jugada que el motor no acepta),
     * la partida no puede quedar trabada: se anota el error y juega algo válido y sin riesgo.
     *
     * @return array{0: Accion, 1: Motor}
     */
    private function jugadaDelBot(Partida $partida, Motor $motor): array
    {
        try {
            $bot = $this->botFijo ?? $partida->nivel_bot->bot(Azar::seguro());

            if ($bot instanceof Recuerda) {
                $bot->recordar($this->manosCerradas($partida));
            }

            $accion = $bot->decidir($motor->vistaPara(self::BOT));

            return [$accion, $motor->aplicar(self::BOT, $accion)];
        } catch (Throwable $falla) {
            report($falla);
        }

        // Una carta si le toca jugar; si le cantaron, no quiere.
        $validas = $motor->accionesPara(self::BOT);
        $cartas = array_values(array_filter($validas, fn (Accion $valida) => $valida->tipo === TipoDeAccion::Jugar));
        $accion = $cartas[0] ?? Accion::de(TipoDeAccion::NoQuiero);

        return [$accion, $motor->aplicar(self::BOT, $accion)];
    }

    /**
     * Lo que el bot vio al cerrarse cada mano anterior, para el bot que lleva la cuenta de la partida.
     * Sale de los mismos eventos que el estado y es la vista de su asiento: no trae nada que no haya visto.
     *
     * @return list<array<string, mixed>>
     */
    private function manosCerradas(Partida $partida): array
    {
        $motor = $this->sinJugar($partida);
        $manos = [];

        foreach ($partida->eventos()->get() as $evento) {
            // Un reparto nuevo deja atrás una mano cerrada.
            if ($evento->tipo === EventoDePartida::REPARTO && $motor->cierre() !== null) {
                $manos[] = $motor->vistaPara(self::BOT);
            }

            $motor = $this->aplicarEvento($motor, $evento);
        }

        return $manos;
    }

    /**
     * Corre algo con la partida bloqueada y ya reconstruida.
     *
     * @param  Closure(Partida, Motor): list<array<string, mixed>>  $hacer
     * @return list<array<string, mixed>>
     */
    private function conLaPartida(Partida $partida, Closure $hacer, ?int $desde = null): array
    {
        return DB::transaction(function () use ($partida, $hacer, $desde) {
            $bloqueada = Partida::query()->whereKey($partida->getKey())->lockForUpdate()->firstOrFail();

            if (! $bloqueada->enCurso()) {
                throw new AccionInvalida('La partida ya terminó.');
            }

            // Con la fila bloqueada nadie más puede sumar un evento: lo que la mesa mostró es lo último, o no lo es.
            // Que el otro haya llegado a la mesa en el medio no cuenta: una llegada no cambia nada de lo que se ve.
            if ($desde !== null && $this->pasoAlgoDesde($bloqueada, $desde)) {
                throw new AccionInvalida('La mesa cambió mientras jugabas.');
            }

            return $hacer($bloqueada, $this->reconstruir($bloqueada));
        });
    }

    /**
     * Si después de ese evento pasó algo en la mesa: una jugada, un reparto, un turno vencido. También si el
     * número no es de esta partida (uno más alto que el último).
     */
    private function pasoAlgoDesde(Partida $partida, int $desde): bool
    {
        if ($desde > (int) $partida->eventos()->max('numero')) {
            return true;
        }

        return $partida->eventos()->getQuery()->where('numero', '>', $desde)->where('tipo', '!=', EventoDePartida::LLEGADA)->exists();
    }

    /**
     * Guarda un evento y devuelve su número.
     *
     * @param  array<string, mixed>  $datos
     */
    private function guardar(Partida $partida, string $tipo, ?int $asiento, array $datos): int
    {
        $numero = (int) $partida->eventos()->max('numero') + 1;

        $partida->eventos()->create([
            'numero' => $numero,
            'tipo' => $tipo,
            'asiento' => $asiento,
            'datos' => $datos,
            'creado_en' => now(),
        ]);

        // Entre personas, el otro se entera por un aviso que sale recién cuando se confirma la transacción.
        // Contra el bot no hace falta: la mesa le pregunta al servidor mientras juega.
        if ($partida->entre_personas) {
            PartidaActualizada::avisar($partida->getKey(), $numero);
        }

        return $numero;
    }

    private function cerrar(Partida $partida, string $estado, ?int $ganador): void
    {
        $partida->estado = $estado;
        $partida->ganador = $ganador;
        $partida->terminada_en = now();
        // Una partida cerrada no espera nada.
        $partida->plazo_vence_en = null;
        $partida->save();

        // Lo que esta partida le cuenta al ranking se anota acá, con todos sus eventos ya guardados.
        // Si eso fallara, la partida se cierra igual: el ranking se puede rehacer desde los eventos
        // (ranking:recalcular) y una partida no puede quedar abierta por un error de la tabla.
        try {
            (new Estadisticas)->anotar($partida, $this);
        } catch (Throwable $falla) {
            report($falla);
        }

        $this->seguirLaSerie($partida);
    }
}
