<?php

namespace App\Juego;

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
use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * La mesa contra el bot: une la partida guardada con el motor de reglas.
 *
 * En la base no se guarda el estado de la partida sino su historia: cada
 * reparto con sus cartas y cada acción. El estado se reconstruye aplicando esa
 * historia al motor. Todo lo que cambia una partida pasa por acá, dentro de
 * una transacción que bloquea su fila, para que dos pedidos a la vez no
 * puedan guardar la misma jugada dos veces.
 */
final class Mesa
{
    public const JUGADOR = 0;

    public const BOT = 1;

    public function __construct(private readonly Bot $bot) {}

    /**
     * La partida en curso del jugador. Si no tiene ninguna, crea una y reparte la primera mano.
     */
    public function abrir(Jugador $jugador): Partida
    {
        return DB::transaction(function () use ($jugador) {
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
     * El estado de la partida, armado de cero con sus eventos.
     */
    public function reconstruir(Partida $partida): Motor
    {
        $motor = Motor::nueva(2, $partida->primer_mano, $partida->puntos);

        foreach ($partida->eventos()->get() as $evento) {
            $motor = match ($evento->tipo) {
                EventoDePartida::REPARTO => $motor->conManos(array_map(
                    fn (array $mano) => array_map(Carta::de(...), $mano),
                    $evento->datos['manos'],
                )),
                EventoDePartida::ACCION => $motor->aplicar($evento->asiento, Accion::desdeArray($evento->datos)),
                // Un abandono cierra la partida sin cambiar lo que se jugó.
                default => $motor,
            };
        }

        return $motor;
    }

    /**
     * Lo que ve el jugador: su vista del motor, que no trae las cartas del bot.
     *
     * @return array<string, mixed>
     */
    public function vista(Partida $partida): array
    {
        return $this->reconstruir($partida)->vistaPara(self::JUGADOR);
    }

    /**
     * El jugador hace algo. Si el motor lo acepta se guarda, y después juega el bot
     * todo lo que le toque. Devuelve cada paso con la vista que le quedó al jugador.
     *
     * @return list<array<string, mixed>>
     *
     * @throws AccionInvalida si el reglamento no lo permite: en ese caso no se guarda nada.
     */
    public function actuar(Partida $partida, Accion $accion): array
    {
        return $this->conLaPartida($partida, function (Partida $partida, Motor $motor) use ($accion) {
            $motor = $motor->aplicar(self::JUGADOR, $accion);
            $this->guardar($partida, EventoDePartida::ACCION, self::JUGADOR, $accion->aArray());

            return $this->seguir($partida, $motor, [$motor->vistaPara(self::JUGADOR)]);
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
     * @return list<array<string, mixed>>
     */
    private function repartirEn(Partida $partida, Motor $motor): array
    {
        // El mazo se mezcla con el azar seguro y las cartas repartidas quedan en el evento.
        $motor = $motor->repartir(Mazo::mezcladoCon(Azar::seguro()));
        $this->guardar($partida, EventoDePartida::REPARTO, null, ['manos' => $motor->aArray()['cartas']]);

        return $this->seguir($partida, $motor, [$motor->vistaPara(self::JUGADOR)]);
    }

    /**
     * Juega el bot mientras le toque, y cierra la partida si alguien llegó a los puntos.
     *
     * @param  list<array<string, mixed>>  $pasos
     * @return list<array<string, mixed>>
     */
    private function seguir(Partida $partida, Motor $motor, array $pasos): array
    {
        // Ninguna mano necesita tantas jugadas seguidas de un mismo lado: si pasa, es un error y se corta.
        for ($jugadas = 0; $motor->fase() === Fase::Jugando && $motor->accionesPara(self::BOT) !== []; $jugadas++) {
            if ($jugadas === 20) {
                throw new LogicException('El bot no termina de jugar.');
            }

            $accion = $this->bot->decidir($motor->vistaPara(self::BOT));
            $motor = $motor->aplicar(self::BOT, $accion);

            $this->guardar($partida, EventoDePartida::ACCION, self::BOT, $accion->aArray());
            $pasos[] = $motor->vistaPara(self::JUGADOR);
        }

        if ($motor->fase() === Fase::Terminada) {
            $this->cerrar($partida, Partida::TERMINADA, $motor->ganador());
        }

        return $pasos;
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
     * @param  array<string, mixed>  $datos
     */
    private function guardar(Partida $partida, string $tipo, ?int $asiento, array $datos): void
    {
        $partida->eventos()->create([
            'numero' => (int) $partida->eventos()->max('numero') + 1,
            'tipo' => $tipo,
            'asiento' => $asiento,
            'datos' => $datos,
            'creado_en' => now(),
        ]);
    }

    private function cerrar(Partida $partida, string $estado, ?int $ganador): void
    {
        $partida->estado = $estado;
        $partida->ganador = $ganador;
        $partida->terminada_en = now();
        $partida->save();
    }
}
