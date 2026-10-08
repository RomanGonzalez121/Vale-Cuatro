<?php

namespace App\Juego;

use App\Jobs\TurnoDelBot;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
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

    /** Los segundos que el bot espera antes de cada jugada. La cola en base de datos cuenta segundos enteros. */
    public const DEMORA_DEL_BOT = 1;

    /** Cuánto espera una sala a que se siente alguien antes de cerrarse sola. */
    public const MINUTOS_DE_SALA = 30;

    /**
     * Cada partida juega contra el bot de su nivel. Un bot fijo sirve para los tests, que necesitan mirar qué recibe.
     */
    public function __construct(private readonly ?Bot $botFijo = null) {}

    /**
     * La partida en curso del jugador. Si no tiene ninguna, crea una contra ese nivel y reparte la primera mano.
     * Con una partida sin terminar se retoma esa, con su nivel, aunque se pida otro.
     */
    public function abrir(Jugador $jugador, ?Nivel $nivel = null): Partida
    {
        return DB::transaction(function () use ($jugador, $nivel) {
            // Se bloquea al jugador: dos toques seguidos en "Jugar" no pueden abrir dos partidas.
            Jugador::query()->whereKey($jugador->getKey())->lockForUpdate()->first();

            // Una sola partida sin terminar por persona: también cuenta una sala que todavía espera rival.
            $enCurso = $this->abiertaDe($jugador);

            if ($enCurso !== null) {
                return $enCurso;
            }

            // El primer mano se sortea acá, con el azar seguro: el motor lo recibe como dato.
            $partida = Partida::create([
                'jugador_id' => $jugador->getKey(),
                'primer_mano' => Azar::seguro()->entero(self::JUGADOR, self::BOT),
                'puntos' => 30,
                'nivel_bot' => $nivel ?? Nivel::porDefecto(),
            ]);

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
     * La última partida del jugador, esté en curso o no. La mesa la consulta para enterarse de
     * las jugadas del bot o del rival, y la última de todas puede ser la que cerró la partida.
     */
    public function ultimaDe(Jugador $jugador): ?Partida
    {
        return $this->delJugador($jugador)->latest('id')->first();
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
     * Lo que ve un jugador: la vista de su asiento del motor, que no trae las cartas del rival, y el
     * número del último evento. Sin decir el asiento vale la partida contra el bot, donde es el 0.
     *
     * @return array<string, mixed>
     */
    public function vista(Partida $partida, ?int $asiento = null): array
    {
        $asiento = $this->asientoPara($partida, $asiento);
        $motor = $this->sinJugar($partida);
        $ultimo = 0;

        // El estado y el número salen de la misma lectura: si el rival juega justo ahora, no pueden quedar desparejos.
        foreach ($partida->eventos()->get() as $evento) {
            $motor = $this->aplicarEvento($motor, $evento);
            $ultimo = $evento->numero;
        }

        return $this->paso($partida, $motor, $ultimo, $asiento);
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

        $motor = $this->sinJugar($partida);
        $pasos = [];

        foreach ($partida->eventos()->get() as $evento) {
            $motor = $this->aplicarEvento($motor, $evento);

            // Un abandono no cambia lo que hay en la mesa: no es un paso para mostrar.
            if ($evento->numero > $desde && $evento->tipo !== EventoDePartida::ABANDONO) {
                $pasos[] = $this->paso($partida, $motor, $evento->numero, $asiento);
            }
        }

        return $pasos;
    }

    /**
     * Un jugador hace algo desde su asiento. Si el motor lo acepta se guarda, y si después le toca al bot se
     * le deja el turno en la cola. Devuelve el paso de esa jugada, visto desde ese asiento: las del bot o del
     * rival llegan por pasosDesde().
     *
     * @return list<array<string, mixed>>
     *
     * @throws AccionInvalida si el reglamento no lo permite: en ese caso no se guarda nada.
     */
    public function actuar(Partida $partida, Accion $accion, ?int $asiento = null): array
    {
        $asiento = $this->asientoPara($partida, $asiento);

        return $this->conLaPartida($partida, function (Partida $partida, Motor $motor) use ($accion, $asiento) {
            $motor = $motor->aplicar($asiento, $accion);
            $evento = $this->guardar($partida, EventoDePartida::ACCION, $asiento, $accion->aArray());
            $this->despuesDeJugar($partida, $motor);

            return [$this->paso($partida, $motor, $evento, $asiento)];
        });
    }

    /**
     * Reparte la mano siguiente. Solo vale entre dos manos.
     *
     * @return list<array<string, mixed>>
     *
     * @throws AccionInvalida si la mano todavía se está jugando.
     */
    public function repartir(Partida $partida, ?int $asiento = null): array
    {
        $asiento = $this->asientoPara($partida, $asiento);

        return $this->conLaPartida($partida, fn (Partida $partida, Motor $motor) => $this->repartirEn($partida, $motor, $asiento));
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
     */
    public function crearSala(Jugador $jugador): Partida
    {
        return DB::transaction(function () use ($jugador) {
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
        return Partida::query()
            ->where('estado', Partida::ESPERANDO)
            ->where('created_at', '<', now()->subMinutes(self::MINUTOS_DE_SALA))
            ->update(['estado' => Partida::ABANDONADA, 'terminada_en' => now()]);
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

            $this->guardar($partida, EventoDePartida::ACCION, self::BOT, $accion->aArray());
            $this->despuesDeJugar($partida, $motor);

            return true;
        });
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
        $this->despuesDeJugar($partida, $motor);

        return [$this->paso($partida, $motor, $evento, $asiento)];
    }

    /**
     * Lo que sigue a cualquier jugada guardada: cerrar la partida si alguien llegó a los puntos,
     * o dejarle el turno al bot si ahora le toca a él (solo contra el bot).
     */
    private function despuesDeJugar(Partida $partida, Motor $motor): void
    {
        if ($motor->fase() === Fase::Terminada) {
            $this->cerrar($partida, Partida::TERMINADA, $motor->ganador());

            return;
        }

        if (! $partida->entre_personas && $this->leTocaAlBot($motor)) {
            // El job sale recién cuando la jugada quedó guardada, y con una demora para que parezca que piensa.
            TurnoDelBot::dispatch($partida->getKey())
                ->delay(now()->addSeconds(self::DEMORA_DEL_BOT))
                ->afterCommit();
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
            EventoDePartida::ACCION => $motor->aplicar($evento->asiento, Accion::desdeArray($evento->datos)),
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
    private function paso(Partida $partida, Motor $motor, int $evento, int $asiento): array
    {
        return [...$motor->vistaPara($asiento), 'partida' => $partida->getKey(), 'evento' => $evento];
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
    private function conLaPartida(Partida $partida, Closure $hacer): array
    {
        return DB::transaction(function () use ($partida, $hacer) {
            $bloqueada = Partida::query()->whereKey($partida->getKey())->lockForUpdate()->firstOrFail();

            if (! $bloqueada->enCurso()) {
                throw new AccionInvalida('La partida ya terminó.');
            }

            return $hacer($bloqueada, $this->reconstruir($bloqueada));
        });
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

        return $numero;
    }

    private function cerrar(Partida $partida, string $estado, ?int $ganador): void
    {
        $partida->estado = $estado;
        $partida->ganador = $ganador;
        $partida->terminada_en = now();
        $partida->save();
    }
}
