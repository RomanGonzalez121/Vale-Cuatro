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
use Illuminate\Support\Facades\DB;
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

            $enCurso = $this->enCursoDe($jugador);

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

    public function enCursoDe(Jugador $jugador): ?Partida
    {
        return Partida::query()
            ->where('jugador_id', $jugador->getKey())
            ->where('estado', Partida::EN_CURSO)
            ->latest('id')
            ->first();
    }

    /**
     * La última partida del jugador, esté en curso o no. La mesa la consulta para enterarse de
     * las jugadas del bot, y la última de todas puede ser la que cerró la partida.
     */
    public function ultimaDe(Jugador $jugador): ?Partida
    {
        return Partida::query()->where('jugador_id', $jugador->getKey())->latest('id')->first();
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
     * Lo que ve el jugador: su vista del motor, que no trae las cartas del bot, y el número del último evento.
     *
     * @return array<string, mixed>
     */
    public function vista(Partida $partida): array
    {
        $motor = $this->sinJugar($partida);
        $ultimo = 0;

        // El estado y el número salen de la misma lectura: si el bot juega justo ahora, no pueden quedar desparejos.
        foreach ($partida->eventos()->get() as $evento) {
            $motor = $this->aplicarEvento($motor, $evento);
            $ultimo = $evento->numero;
        }

        return $this->paso($partida, $motor, $ultimo);
    }

    /**
     * Lo que pasó después de un evento: la vista del jugador tras cada evento posterior, en orden.
     * Así se entera la mesa de las jugadas del bot, que juega aparte.
     *
     * @return list<array<string, mixed>>
     */
    public function pasosDesde(Partida $partida, int $desde): array
    {
        // Casi todas las consultas llegan antes de que el bot juegue: ahí no hace falta reconstruir nada.
        if ((int) $partida->eventos()->max('numero') <= $desde) {
            return [];
        }

        $motor = $this->sinJugar($partida);
        $pasos = [];

        foreach ($partida->eventos()->get() as $evento) {
            $motor = $this->aplicarEvento($motor, $evento);

            // Un abandono no cambia lo que hay en la mesa: no es un paso para mostrar.
            if ($evento->numero > $desde && $evento->tipo !== EventoDePartida::ABANDONO) {
                $pasos[] = $this->paso($partida, $motor, $evento->numero);
            }
        }

        return $pasos;
    }

    /**
     * El jugador hace algo. Si el motor lo acepta se guarda, y si después le toca al bot se le
     * deja el turno en la cola. Devuelve el paso de esa jugada: las del bot llegan por pasosDesde().
     *
     * @return list<array<string, mixed>>
     *
     * @throws AccionInvalida si el reglamento no lo permite: en ese caso no se guarda nada.
     */
    public function actuar(Partida $partida, Accion $accion): array
    {
        return $this->conLaPartida($partida, function (Partida $partida, Motor $motor) use ($accion) {
            $motor = $motor->aplicar(self::JUGADOR, $accion);
            $evento = $this->guardar($partida, EventoDePartida::ACCION, self::JUGADOR, $accion->aArray());
            $this->despuesDeJugar($partida, $motor);

            return [$this->paso($partida, $motor, $evento)];
        });
    }

    /**
     * Reparte la mano siguiente. Solo vale entre dos manos.
     *
     * @return list<array<string, mixed>>
     *
     * @throws AccionInvalida si la mano todavía se está jugando.
     */
    public function repartir(Partida $partida): array
    {
        return $this->conLaPartida($partida, fn (Partida $partida, Motor $motor) => $this->repartirEn($partida, $motor));
    }

    /**
     * El jugador deja la partida: queda cerrada como perdida, con todo lo jugado intacto.
     */
    public function abandonar(Partida $partida): void
    {
        $this->conLaPartida($partida, function (Partida $partida) {
            $this->guardar($partida, EventoDePartida::ABANDONO, self::JUGADOR, []);
            $this->cerrar($partida, Partida::ABANDONADA, self::BOT);

            return [];
        });
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

            if ($partida === null || ! $partida->enCurso()) {
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
    private function repartirEn(Partida $partida, Motor $motor): array
    {
        // El mazo se mezcla con el azar seguro y las cartas repartidas quedan en el evento.
        $motor = $motor->repartir(Mazo::mezcladoCon(Azar::seguro()));
        $evento = $this->guardar($partida, EventoDePartida::REPARTO, null, ['manos' => $motor->aArray()['cartas']]);
        $this->despuesDeJugar($partida, $motor);

        return [$this->paso($partida, $motor, $evento)];
    }

    /**
     * Lo que sigue a cualquier jugada guardada: cerrar la partida si alguien llegó a los puntos,
     * o dejarle el turno al bot si ahora le toca a él.
     */
    private function despuesDeJugar(Partida $partida, Motor $motor): void
    {
        if ($motor->fase() === Fase::Terminada) {
            $this->cerrar($partida, Partida::TERMINADA, $motor->ganador());

            return;
        }

        if ($this->leTocaAlBot($motor)) {
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
     * Un paso es la vista del jugador después de un evento, con el número de ese evento y el de
     * la partida. Con esos dos números la mesa pregunta qué pasó después, y el servidor sabe si
     * la pestaña que pregunta sigue mostrando la última partida.
     *
     * @return array<string, mixed>
     */
    private function paso(Partida $partida, Motor $motor, int $evento): array
    {
        return [...$motor->vistaPara(self::JUGADOR), 'partida' => $partida->getKey(), 'evento' => $evento];
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
