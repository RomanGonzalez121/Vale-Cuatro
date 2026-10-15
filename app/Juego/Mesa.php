<?php

namespace App\Juego;

use App\Avisos\Avisos;
use App\Events\PartidaActualizada;
use App\Jobs\AvanzarTorneo;
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
use App\Motor\Tanto;
use App\Motor\TipoDeAccion;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
     * Cuánto tiene que pasar sin que se mueva nada para que una partida se considere quieta, y se pueda
     * cerrar desde el panel de administración. Contra el bot, un día: quien la dejó puede volver a la noche.
     * Entre personas, diez minutos: ahí los turnos vencen solos, así que tanto rato quieta es una falla.
     */
    public const HORAS_QUIETA_CONTRA_EL_BOT = 24;

    public const MINUTOS_QUIETA_ENTRE_PERSONAS = 10;

    /**
     * Cada partida juega contra el bot de su nivel. Un bot fijo sirve para los tests, que necesitan mirar qué recibe.
     */
    public function __construct(private readonly ?Bot $botFijo = null) {}

    /**
     * La partida en curso del jugador. Si no tiene ninguna, crea una contra ese nivel y reparte la primera mano.
     * Con una partida sin terminar se retoma esa, con su nivel, aunque se pida otro.
     *
     * Con $enSerie, la partida nueva es la primera de una serie al mejor de tres contra ese nivel.
     *
     * Con $asientos en 4 es de truco de a cuatro: el jugador con un bot de compañero contra dos bots,
     * los tres del mismo nivel. De a cuatro no hay series.
     */
    public function abrir(Jugador $jugador, ?Nivel $nivel = null, bool $enSerie = false, int $asientos = 2): Partida
    {
        return DB::transaction(function () use ($jugador, $nivel, $enSerie, $asientos) {
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
                'primer_mano' => Azar::seguro()->entero(0, $asientos - 1),
                'puntos' => 30,
                'asientos' => $asientos,
                'nivel_bot' => $nivel ?? Nivel::porDefecto(),
            ]);

            // La serie no se asigna en masa: solo la decide el código de la mesa.
            $partida->serie_id = $enSerie && $asientos === 2 ? Serie::create()->getKey() : null;
            $partida->save();

            $this->repartirEn($partida, $this->reconstruir($partida));

            return $partida;
        });
    }

    /**
     * La partida de una ronda de un torneo contra bots: contra el bot de ese nivel y a los puntos del
     * torneo, ya repartida. Qué torneo, qué rival y cuándo lo decide Torneos: esto solo la crea.
     *
     * Si el jugador ya está jugando la de ese torneo, devuelve esa.
     *
     * @throws TorneoNoDisponible si tiene otra partida sin terminar: una persona juega una sola a la vez.
     */
    public function abrirEnTorneo(Jugador $jugador, Nivel $nivel, int $puntos, int $torneoId): Partida
    {
        return DB::transaction(function () use ($jugador, $nivel, $puntos, $torneoId) {
            Jugador::query()->whereKey($jugador->getKey())->lockForUpdate()->first();

            $abierta = $this->abiertaDe($jugador);

            if ($abierta !== null && $abierta->torneo_id === $torneoId) {
                return $abierta;
            }

            if ($abierta !== null) {
                throw new TorneoNoDisponible('Tenés otra partida sin terminar. Terminala o abandonala antes de jugar la del torneo.');
            }

            $partida = new Partida([
                'jugador_id' => $jugador->getKey(),
                'primer_mano' => Azar::seguro()->entero(self::JUGADOR, self::BOT),
                'puntos' => $puntos,
                'nivel_bot' => $nivel,
            ]);

            // El torneo no se asigna en masa: solo lo decide el código de la mesa.
            $partida->torneo_id = $torneoId;
            $partida->save();

            $this->repartirEn($partida, $this->reconstruir($partida));

            return $partida;
        });
    }

    /**
     * Abre un desafío: una sola mano contra el bot, armada con las cartas, el tanteo y lo ya jugado que
     * dice su ficha. Acá no se sortea nada, así que el mismo desafío arranca siempre igual. La partida
     * se cierra sola cuando esa mano termina, y ahí se mira si se cumplió el objetivo.
     *
     * Con una partida sin terminar devuelve esa, sea la que sea: una persona juega una sola a la vez.
     *
     * @param  array<string, mixed>  $desafio  La ficha del catálogo (App\Juego\Desafios).
     * @param  int|null  $escalon  Si esa mano es un escalón de la escalera del club, cuál.
     */
    public function abrirDesafio(Jugador $jugador, array $desafio, ?int $escalon = null): Partida
    {
        return DB::transaction(function () use ($jugador, $desafio, $escalon) {
            Jugador::query()->whereKey($jugador->getKey())->lockForUpdate()->first();

            $abierta = $this->abiertaDe($jugador);

            if ($abierta !== null) {
                return $abierta;
            }

            $partida = new Partida([
                'jugador_id' => $jugador->getKey(),
                'primer_mano' => Desafios::mano($desafio),
                'puntos' => Desafios::PUNTOS,
                'nivel_bot' => Nivel::from($desafio['nivel']),
            ]);

            // Ni el desafío ni el escalón se asignan en masa: solo los decide el código de la mesa.
            $partida->desafio = $desafio['clave'];
            $partida->escalon = $escalon;
            $partida->save();

            // El tanteo de arranque queda guardado con el reparto: la partida se puede volver a contar
            // desde sus eventos aunque después la ficha cambie.
            $motor = Desafios::motor($desafio);
            $evento = $this->guardar($partida, EventoDePartida::REPARTO, null, ['manos' => Desafios::manos($desafio), 'tanteo' => Desafios::tanteo($desafio)]);

            foreach (Desafios::jugadas($desafio) as [$asiento, $accion]) {
                $motor = $motor->aplicar($asiento, $accion);
                $evento = $this->guardar($partida, EventoDePartida::ACCION, $asiento, $accion->aArray());
            }

            $this->despuesDeJugar($partida, $motor, $evento);

            return $partida;
        });
    }

    /**
     * La partida de un escalón de la escalera del club: contra el bot de ese nivel y a los puntos de la
     * escalera, ya repartida. Qué escalón y si está abierto lo decide quien llama: esto solo la crea.
     *
     * Con una partida sin terminar devuelve esa, sea la que sea.
     */
    public function abrirEnEscalera(Jugador $jugador, Nivel $nivel, int $escalon): Partida
    {
        return DB::transaction(function () use ($jugador, $nivel, $escalon) {
            Jugador::query()->whereKey($jugador->getKey())->lockForUpdate()->first();

            $abierta = $this->abiertaDe($jugador);

            if ($abierta !== null) {
                return $abierta;
            }

            $partida = new Partida([
                'jugador_id' => $jugador->getKey(),
                'primer_mano' => Azar::seguro()->entero(self::JUGADOR, self::BOT),
                'puntos' => Escalera::PUNTOS,
                'nivel_bot' => $nivel,
            ]);

            $partida->escalon = $escalon;
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
            $mano = ['manos' => [], 'hechas' => []];

            // El estado y el número salen de la misma lectura: si el rival juega justo ahora, no pueden quedar desparejos.
            foreach ($partida->eventos()->get() as $evento) {
                $motor = $this->aplicarEvento($motor, $evento);
                $mano = $this->seguirLaMano($mano, $evento);
                $ultimo = $evento;
            }

            return $this->paso($partida, $motor, $ultimo->numero ?? 0, $asiento, $ultimo, $mano);
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
            $mano = ['manos' => [], 'hechas' => []];

            foreach ($partida->eventos()->get() as $evento) {
                $motor = $this->aplicarEvento($motor, $evento);
                $mano = $this->seguirLaMano($mano, $evento);

                // Un abandono o un cierre no cambian lo que hay en la mesa: no son un paso para mostrar.
                if ($evento->numero > $desde && ! in_array($evento->tipo, [EventoDePartida::ABANDONO, EventoDePartida::CIERRE], true)) {
                    $pasos[] = $this->paso($partida, $motor, $evento->numero, $asiento, $evento, $mano);
                }
            }

            return $pasos;
        });
    }

    /**
     * Lleva la cuenta de la mano en juego mientras se recorren los eventos: un reparto la empieza de
     * cero y una seña se suma. Así cada paso lleva las señas de su momento, y no las de ahora.
     *
     * @param  array{manos: list<list<string>>, hechas: list<string>}  $mano
     * @return array{manos: list<list<string>>, hechas: list<string>}
     */
    private function seguirLaMano(array $mano, EventoDePartida $evento): array
    {
        return match ($evento->tipo) {
            EventoDePartida::REPARTO => ['manos' => $evento->datos['manos'], 'hechas' => []],
            EventoDePartida::SENA => [...$mano, 'hechas' => [...$mano['hechas'], $evento->datos['sena']]],
            default => $mano,
        };
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
     * De a cuatro: la persona le hace una seña a su compañero. No es una jugada: no cambia la mesa ni
     * le pasa el turno a nadie. Queda guardada, y su compañero bot la tiene en cuenta desde su próxima decisión.
     *
     * Solo se puede hacer la seña de algo que se tiene, una vez por mano, y mientras se sigue en la mano.
     *
     * @return list<array<string, mixed>>
     *
     * @throws AccionInvalida si esa seña no se puede hacer ahora: no se guarda nada.
     */
    public function sena(Partida $partida, string $sena): array
    {
        return $this->conLaPartida($partida, function (Partida $partida, Motor $motor) use ($sena) {
            if (! $partida->deACuatro()) {
                throw new AccionInvalida('Las señas son del truco de a cuatro.');
            }

            $vista = $motor->vistaPara(self::JUGADOR);

            if ($motor->fase() !== Fase::Jugando || in_array(self::JUGADOR, $vista['enMazo'], true)) {
                throw new AccionInvalida('Ahora no hay a quién hacerle una seña.');
            }

            $mano = $this->manoEnJuego($partida);

            if (! in_array($sena, $this->senasDe(self::JUGADOR, $mano)['tuyas'], true)) {
                throw new AccionInvalida('Esa seña no la podés hacer: es de algo que no tenés.');
            }

            if (in_array($sena, $mano['hechas'], true)) {
                throw new AccionInvalida('Esa seña ya la hiciste en esta mano.');
            }

            $evento = $this->guardar($partida, EventoDePartida::SENA, self::JUGADOR, ['sena' => $sena]);
            $paso = $this->paso($partida, $motor, $evento, self::JUGADOR, null, [...$mano, 'hechas' => [...$mano['hechas'], $sena]]);

            // No pasó nada en la mesa: los hechos de la última jugada ya se contaron.
            return [[...$paso, 'hechos' => []]];
        });
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
     * Desde cuándo una partida que no se movió se considera quieta: la hora de su último evento tiene
     * que ser anterior a esta.
     */
    public function limiteDeQuieta(Partida $partida): CarbonInterface
    {
        return $partida->entre_personas
            ? now()->subMinutes(self::MINUTOS_QUIETA_ENTRE_PERSONAS)
            : now()->subHours(self::HORAS_QUIETA_CONTRA_EL_BOT);
    }

    /**
     * Si una partida en curso quedó sin movimiento: hace rato que no se guarda ningún evento en ella.
     */
    public function estaQuieta(Partida $partida): bool
    {
        $ultimo = $partida->eventos()->max('creado_en') ?? $partida->created_at;

        return $partida->enCurso() && Carbon::parse($ultimo)->lt($this->limiteDeQuieta($partida));
    }

    /**
     * Las partidas en curso que quedaron sin movimiento, con la misma regla que estaQuieta(). No cuentan
     * las simuladas, que nacen ya cerradas.
     *
     * @return Builder<Partida>
     */
    public function enCursoQuietas(): Builder
    {
        return $this->enCursoDePersonas()->where(fn (Builder $partidas) => $this->soloQuietas($partidas));
    }

    /**
     * Las partidas en curso que se están jugando: todas las que no están quietas.
     *
     * @return Builder<Partida>
     */
    public function enCursoQueSeMueven(): Builder
    {
        return $this->enCursoDePersonas()->whereNot(fn (Builder $partidas) => $this->soloQuietas($partidas));
    }

    /**
     * @return Builder<Partida>
     */
    private function enCursoDePersonas(): Builder
    {
        return Partida::query()->where('estado', Partida::EN_CURSO)->where('simulada', false);
    }

    /**
     * Quieta es la que se creó antes del límite y no tiene ningún evento desde entonces. El límite
     * depende de contra quién se juega.
     *
     * @param  Builder<Partida>  $partidas
     */
    private function soloQuietas(Builder $partidas): void
    {
        $limites = [
            [false, now()->subHours(self::HORAS_QUIETA_CONTRA_EL_BOT)],
            [true, now()->subMinutes(self::MINUTOS_QUIETA_ENTRE_PERSONAS)],
        ];

        foreach ($limites as [$entrePersonas, $limite]) {
            $partidas->orWhere(fn (Builder $quieta) => $quieta
                ->where('entre_personas', $entrePersonas)
                ->where('created_at', '<', $limite)
                ->whereDoesntHave('eventos', fn (Builder $eventos) => $eventos->where('creado_en', '>=', $limite)));
        }
    }

    /**
     * Cierra una partida que quedó sin movimiento. Es lo que hace el panel de administración: agrega un
     * evento al final de su lista y no borra nada. No la gana nadie, así que no cuenta para el ranking,
     * y deja de ser la partida en curso de sus jugadores. Si era de una serie, la serie se cierra con ella.
     *
     * Devuelve false, sin tocar nada, si la partida ya no está en curso o volvió a moverse.
     */
    public function cerrarQuieta(Partida $partida): bool
    {
        return DB::transaction(function () use ($partida) {
            $bloqueada = Partida::query()->whereKey($partida->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->estaQuieta($bloqueada)) {
                return false;
            }

            $this->guardar($bloqueada, EventoDePartida::CIERRE, null, ['motivo' => 'sin_movimiento']);
            $this->cerrar($bloqueada, Partida::CERRADA, null);

            return true;
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

            // Quien abrió la sala puede estar en otra aplicación, mandando el link: se le avisa que ya llegó alguien.
            app(Avisos::class)->seSentoElRival($partida);

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
        $cerradas = $this->salasVencidas()->update(['estado' => Partida::ABANDONADA, 'terminada_en' => now()]);

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
     * Las salas que esperaron rival más de MINUTOS_DE_SALA: las que se lleva la limpieza.
     *
     * @return Builder<Partida>
     */
    public function salasVencidas(): Builder
    {
        return Partida::query()
            ->where('estado', Partida::ESPERANDO)
            ->where('created_at', '<', now()->subMinutes(self::MINUTOS_DE_SALA));
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
            // El mano pasa al asiento que sigue: con dos asientos, al otro.
            'primer_mano' => ($anterior->primer_mano + 1) % $anterior->asientos,
            'puntos' => $anterior->puntos,
            'asientos' => $anterior->asientos,
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

        // La cerró la administración porque quedó sin movimiento: la serie se cierra con ella, sin ganador.
        if ($partida->estado === Partida::CERRADA) {
            $serie->ganador = null;
            $serie->terminada_en = now();
            $serie->save();

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
            $asiento = $this->asientoDelBot($partida, $motor);

            if ($asiento === null) {
                return false;
            }

            [$accion, $motor] = $this->jugadaDelBot($partida, $motor, $asiento);

            $evento = $this->guardar($partida, EventoDePartida::ACCION, $asiento, $accion->aArray());
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
        // Un desafío es una sola mano: cuando se cierra, se cierra la partida. Quien "gana" es el jugador
        // si cumplió el objetivo y el bot si no, haya ganado esa mano o la haya perdido.
        if ($partida->esDesafio() && $motor->fase() !== Fase::Jugando) {
            $this->cerrar($partida, Partida::TERMINADA, $this->cumplioElObjetivo($partida) ? self::JUGADOR : self::BOT);

            return;
        }

        if ($motor->fase() === Fase::Terminada) {
            $this->cerrar($partida, Partida::TERMINADA, $motor->ganador());

            return;
        }

        if ($partida->entre_personas) {
            $this->esperarPlazo($partida, $motor, $evento);

            return;
        }

        if ($this->asientoDelBot($partida, $motor) !== null) {
            // El job sale recién cuando la jugada quedó guardada. No lleva demora: el bot juega apenas le toca, y
            // la pausa para que parezca que piensa la pone la mesa del navegador, según el ritmo que eligió quien juega.
            TurnoDelBot::dispatch($partida->getKey())->afterCommit();
        }
    }

    /**
     * Si en un desafío se cumplió el objetivo. No se le pregunta a la pantalla ni se mira lo que quedó en
     * memoria: sale de volver a pasar los eventos guardados por el motor, como el historial y el ranking.
     */
    private function cumplioElObjetivo(Partida $partida): bool
    {
        $desafio = Desafios::de((string) $partida->desafio);

        if ($desafio === null) {
            return false;
        }

        [$hechos, $final] = Objetivos::loQuePaso($this->pasoAPaso($partida));

        return Objetivos::cumple($desafio['objetivo'], $hechos, $final);
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

    /**
     * El asiento del bot al que le toca actuar, o null si no le toca a ninguno.
     *
     * Mano a mano es el asiento 1. De a cuatro son tres, y hay un caso que el motor deja abierto: un canto
     * al equipo lo puede contestar cualquiera de los dos. Si le cantaron al equipo de la persona, contesta
     * ella: su compañero bot espera, porque si no le sacaría siempre la decisión (decidido por Román).
     * Solo contesta él si la persona ya se fue al mazo. Entre los dos bots rivales contesta el primero.
     */
    private function asientoDelBot(Partida $partida, Motor $motor): ?int
    {
        if ($partida->entre_personas || $motor->fase() !== Fase::Jugando) {
            return null;
        }

        $pueden = array_values(array_filter(
            range(0, $partida->asientos - 1),
            fn (int $asiento) => $motor->accionesPara($asiento) !== [],
        ));

        return $pueden === [] || in_array(self::JUGADOR, $pueden, true) ? null : $pueden[0];
    }

    private function sinJugar(Partida $partida): Motor
    {
        return Motor::nueva($partida->asientos, $partida->primer_mano, $partida->puntos);
    }

    private function aplicarEvento(Motor $motor, EventoDePartida $evento): Motor
    {
        return match ($evento->tipo) {
            // Un desafío arranca desde un tanteo dado, que quedó guardado con su reparto.
            EventoDePartida::REPARTO => (isset($evento->datos['tanteo']) ? $motor->conTanteo($evento->datos['tanteo']) : $motor)->conManos(array_map(
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
    private function paso(Partida $partida, Motor $motor, int $evento, int $asiento, ?EventoDePartida $guardado = null, ?array $mano = null): array
    {
        $paso = [...$motor->vistaPara($asiento), 'partida' => $partida->getKey(), 'evento' => $evento];

        // Una llegada o una seña no cambian la mesa: el motor sigue con los hechos de la jugada anterior, que ya se contaron.
        if (in_array($guardado?->tipo, [EventoDePartida::LLEGADA, EventoDePartida::SENA], true)) {
            $paso['hechos'] = [];
        }

        // En un desafío, la jugada que cierra la mano cierra también la partida: ese paso dice si se cumplió
        // el objetivo. Antes de eso, y si se abandonó, no dice nada.
        if ($partida->esDesafio()) {
            $paso['superado'] = $motor->fase() !== Fase::Jugando && $partida->estado === Partida::TERMINADA ? $partida->ganador === self::JUGADOR : null;
        }

        // De a cuatro, las señas de la mano: las que le hace su compañero, las que puede hacer y las que ya hizo.
        // Las de los rivales no viajan: una seña es solo para el compañero.
        if ($partida->deACuatro()) {
            $paso['senas'] = $this->senasDe($asiento, $mano ?? $this->manoEnJuego($partida));
        }

        // Entre personas, cuántos segundos faltan para que el servidor resuelva la espera: el turno o el reparto.
        // La mesa lo usa para la cuenta regresiva. Y si esta jugada la hizo el servidor porque a alguien se le
        // venció el turno, a qué asiento: así la mesa lo cuenta como lo que fue y no como un mazo elegido.
        // Contra el bot no hay plazo y el paso queda como siempre.
        if ($partida->entre_personas) {
            $paso['restan'] = $this->restan($partida, $motor);
            $paso['vencio'] = $guardado?->tipo === EventoDePartida::VENCIMIENTO ? $guardado->asiento : null;
        }

        // El paso que termina la partida dice cuántos fósforos anotó y, si subió de nivel, qué destrabó.
        // Solo ese paso: es lo que cuenta el final de la partida.
        if (in_array('partida_terminada', array_column($paso['hechos'], 'tipo'), true) && ! $partida->estaAbierta()) {
            $paso['experiencia'] = (new Camino)->resumen($partida, $asiento, $this);
        }

        // Quien declaró en su perfil que está aprendiendo ve su tanto de envido ya sumado. Es una cuenta
        // hecha con sus propias cartas: no cambia qué puede jugar ni viaja en el paso de nadie más.
        if ($this->personaEn($partida, $asiento)?->experiencia?->veSuTanto()) {
            $paso['ayudas'] = ['tanto' => self::tantoDe($paso, $asiento)];
        }

        return $paso;
    }

    /**
     * La persona sentada en ese asiento, o null si ahí juega un bot.
     */
    private function personaEn(Partida $partida, int $asiento): ?Jugador
    {
        return match (true) {
            $asiento === self::JUGADOR => $partida->jugador,
            $partida->entre_personas && $asiento === self::BOT => $partida->invitado,
            default => null,
        };
    }

    /**
     * El tanto de envido de un asiento, con las tres cartas que le repartieron: las que le quedan y las
     * que ya tiró en esta mano. Null si todavía no hay cartas repartidas.
     *
     * @param  array<string, mixed>  $vista  La vista de ese mismo asiento.
     */
    private static function tantoDe(array $vista, int $asiento): ?int
    {
        $cartas = $vista['misCartas'];

        foreach ($vista['bazas'] as $baza) {
            foreach ($baza['jugadas'] as [$quien, $carta]) {
                if ($quien === $asiento) {
                    $cartas[] = $carta;
                }
            }
        }

        return count($cartas) === 3 ? Tanto::deEnvido(array_map(Carta::de(...), $cartas)) : null;
    }

    /**
     * Las señas como las ve un asiento: las de su compañero, las que él puede hacer con sus cartas y las
     * que ya hizo en esta mano. El compañero es un bot y hace siempre las suyas.
     *
     * @param  array{manos: list<list<string>>, hechas: list<string>}  $mano
     * @return array{companero: list<string>, tuyas: list<string>, hechas: list<string>}
     */
    private function senasDe(int $asiento, array $mano): array
    {
        $deLasCartas = fn (int $quien) => Senas::de(array_map(Carta::de(...), $mano['manos'][$quien] ?? []));

        return [
            'companero' => $mano['manos'] === [] ? [] : $deLasCartas(($asiento + 2) % 4),
            'tuyas' => $mano['manos'] === [] ? [] : $deLasCartas($asiento),
            'hechas' => $mano['hechas'],
        ];
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
    private function jugadaDelBot(Partida $partida, Motor $motor, int $asiento): array
    {
        try {
            // En un desafío el bot no juega al azar: con las mismas jugadas contesta siempre lo mismo.
            $azar = $partida->esDesafio()
                ? Desafios::azar((string) $partida->desafio, (int) $partida->eventos()->max('numero') + 1)
                : Azar::seguro();

            $bot = $this->botFijo ?? ($partida->deACuatro()
                ? $partida->nivel_bot->botDePareja($azar)
                : $partida->nivel_bot->bot($azar));

            if ($bot instanceof Recuerda) {
                $bot->recordar($this->manosCerradas($partida, $asiento));
            }

            if ($bot instanceof RecibeSenas && $partida->deACuatro()) {
                $bot->recibirSenas($this->senasQueRecibe($asiento, $this->manoEnJuego($partida)));
            }

            $accion = $bot->decidir($motor->vistaPara($asiento));

            return [$accion, $motor->aplicar($asiento, $accion)];
        } catch (Throwable $falla) {
            report($falla);
        }

        // Una carta si le toca jugar; si le cantaron, no quiere.
        $validas = $motor->accionesPara($asiento);
        $cartas = array_values(array_filter($validas, fn (Accion $valida) => $valida->tipo === TipoDeAccion::Jugar));
        $accion = $cartas[0] ?? Accion::de(TipoDeAccion::NoQuiero);

        return [$accion, $motor->aplicar($asiento, $accion)];
    }

    /**
     * Lo que hace falta saber de la mano en juego para las señas: las cartas que recibió cada asiento
     * y las señas que la persona ya hizo. Sale de los eventos: el último reparto y lo que vino después.
     *
     * @return array{manos: list<list<string>>, hechas: list<string>}
     */
    private function manoEnJuego(Partida $partida): array
    {
        $reparto = $partida->eventos()->where('tipo', EventoDePartida::REPARTO)->reorder('numero', 'desc')->first();

        if ($reparto === null) {
            return ['manos' => [], 'hechas' => []];
        }

        $hechas = $partida->eventos()->where('tipo', EventoDePartida::SENA)->where('numero', '>', $reparto->numero)->get()
            ->map(fn (EventoDePartida $evento) => $evento->datos['sena'])->all();

        return ['manos' => $reparto->datos['manos'], 'hechas' => $hechas];
    }

    /**
     * Las señas que un bot recibió de su compañero en la mano en juego.
     *
     * Si su compañero es la persona, son las que ella le hizo. Si es otro bot, son las que ese bot puede
     * hacer con sus cartas: los bots se las hacen siempre todas, y no hace falta guardarlas. En los dos
     * casos al bot le llega lo que dice la seña y nada más: nunca las cartas del compañero.
     *
     * @param  array{manos: list<list<string>>, hechas: list<string>}  $mano
     * @return list<string>
     */
    private function senasQueRecibe(int $asiento, array $mano): array
    {
        $companero = ($asiento + 2) % 4;

        return $companero === self::JUGADOR
            ? $mano['hechas']
            : Senas::de(array_map(Carta::de(...), $mano['manos'][$companero] ?? []));
    }

    /**
     * Lo que el bot vio al cerrarse cada mano anterior, para el bot que lleva la cuenta de la partida.
     * Sale de los mismos eventos que el estado y es la vista de su asiento: no trae nada que no haya visto.
     *
     * @return list<array<string, mixed>>
     */
    private function manosCerradas(Partida $partida, int $asiento = self::BOT): array
    {
        $motor = $this->sinJugar($partida);
        $manos = [];

        foreach ($partida->eventos()->get() as $evento) {
            // Un reparto nuevo deja atrás una mano cerrada.
            if ($evento->tipo === EventoDePartida::REPARTO && $motor->cierre() !== null) {
                $manos[] = $motor->vistaPara($asiento);
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

        return $partida->eventos()->getQuery()->where('numero', '>', $desde)
            ->whereNotIn('tipo', [EventoDePartida::LLEGADA, EventoDePartida::SENA])->exists();
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
            // Y a quien le toca ahora, si pidió avisos y no tiene la mesa a la vista, le llega uno al celular.
            app(Avisos::class)->partidaCambio($partida, $numero, $tipo);
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

        // Los fósforos de cada persona, para su nivel. Como el ranking, se pueden rehacer desde las partidas.
        try {
            (new Camino)->anotar($partida, $this);
        } catch (Throwable $falla) {
            report($falla);
        }

        // Si era un desafío o un escalón de la escalera, queda anotado en lo que el jugador lleva pasado.
        (new Progresos)->anotar($partida);

        $this->seguirLaSerie($partida);

        // Si era de un torneo, las llaves se ponen al día aparte, cuando esto ya quedó guardado: hay que
        // anotar el resultado y jugar las partidas entre bots de la ronda, y eso no puede demorar la jugada.
        if ($partida->torneo_id !== null) {
            AvanzarTorneo::dispatch($partida->torneo_id)->afterCommit();
        }
    }
}
