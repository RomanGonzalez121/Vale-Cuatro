<?php

namespace App\Avisos;

use App\Jobs\AvisarAlJugador;
use App\Juego\Mesa;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\SuscripcionDeAviso;
use App\Motor\Fase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Los avisos que llegan al celular cuando no se está mirando la mesa: "Te toca", "Te cantaron truco" y
 * "Tu rival se sentó". Solo existen entre personas: un bot juega en el momento y no hay a quién esperar.
 *
 * Un aviso es una comodidad, nunca la única señal: lo que dice ya está en la mesa. Por eso nada de acá
 * puede romper una jugada: si algo falla se anota el error y la partida sigue.
 *
 * A quién se le avisa se decide dos veces. Al pasar el turno: si esa persona no pidió avisos o tiene la
 * mesa a la vista, no se encola nada. Y unos segundos después, al ir a mandarlo: si en ese rato jugó,
 * volvió a la mesa o la partida se cerró, tampoco se manda.
 */
final class Avisos
{
    /** Los navegadores de un mismo jugador que reciben avisos. Uno de más reemplaza al más viejo. */
    public const NAVEGADORES_POR_JUGADOR = 5;

    /**
     * Los servicios de avisos de los navegadores: el de Chrome y los que derivan de él, el de Firefox, el
     * de Windows y el de Apple. El sitio manda cada aviso a la dirección que le dio el navegador, así que
     * esa dirección no puede ser cualquiera: si lo fuera, alguien podría hacer que el servidor le hable a
     * la máquina que quiera.
     */
    private const SERVICIOS = ['fcm.googleapis.com', 'updates.push.services.mozilla.com', '.notify.windows.com', '.push.apple.com'];

    private const CANTOS = [
        'envido' => 'envido',
        'real_envido' => 'real envido',
        'falta_envido' => 'falta envido',
        'contraflor' => 'contraflor',
        'contraflor_al_resto' => 'contraflor al resto',
    ];

    private const NIVEL_DE_TRUCO = ['', 'truco', 'retruco', 'vale cuatro'];

    public function __construct(private readonly Presencia $presencia, private readonly Cartero $cartero, private readonly Claves $claves) {}

    /**
     * Lo que una página (la mesa, la sala) necesita para ofrecer los avisos: la clave pública del sitio y
     * adónde pedir cada cosa. Si las claves no se pudieran leer ni generar, la página se ve igual, sin avisos.
     *
     * @return array{clave: string, suscribir: string, baja: string, vista: string, token: string}|null
     */
    public function paraLaPagina(): ?array
    {
        try {
            return [
                'clave' => $this->claves->publica(),
                'suscribir' => route('avisos.suscribir'),
                'baja' => route('avisos.baja'),
                'vista' => route('avisos.vista'),
                'token' => csrf_token(),
            ];
        } catch (Throwable $falla) {
            report($falla);

            return null;
        }
    }

    // Suscribirse y darse de baja

    /**
     * Anota el navegador de un jugador para mandarle avisos. Si ese navegador ya estaba anotado (a su
     * nombre o al de otro, porque cambió de cuenta), pasa a ser de quien lo pide ahora.
     *
     * @throws InvalidArgumentException si la dirección no es de un servicio de avisos conocido.
     */
    public function suscribir(Jugador $jugador, string $destino, string $p256dh, string $auth): SuscripcionDeAviso
    {
        if (! self::esDeUnServicioConocido($destino)) {
            throw new InvalidArgumentException('Esa dirección no es de un servicio de avisos.');
        }

        $suscripcion = SuscripcionDeAviso::query()->updateOrCreate(
            ['huella' => SuscripcionDeAviso::huellaDe($destino)],
            ['jugador_id' => $jugador->getKey(), 'destino' => $destino, 'p256dh' => $p256dh, 'auth' => $auth],
        );

        // Se quedan los más nuevos.
        SuscripcionDeAviso::query()
            ->where('jugador_id', $jugador->getKey())
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->get()
            ->slice(self::NAVEGADORES_POR_JUGADOR)
            ->each->delete();

        return $suscripcion;
    }

    public function darDeBaja(Jugador $jugador, string $destino): void
    {
        SuscripcionDeAviso::query()
            ->where('jugador_id', $jugador->getKey())
            ->where('huella', SuscripcionDeAviso::huellaDe($destino))
            ->delete();
    }

    public function tieneAvisos(int $jugadorId): bool
    {
        return SuscripcionDeAviso::query()->where('jugador_id', $jugadorId)->exists();
    }

    public static function esDeUnServicioConocido(string $destino): bool
    {
        $partes = parse_url($destino);

        if (($partes['scheme'] ?? null) !== 'https' || ! isset($partes['host']) || isset($partes['user']) || isset($partes['port'])) {
            return false;
        }

        $host = strtolower($partes['host']);

        foreach (self::SERVICIOS as $servicio) {
            // Con punto adelante vale cualquier nombre que termine así; sin punto, tiene que ser ese mismo.
            if (str_starts_with($servicio, '.') ? str_ends_with($host, $servicio) : $host === $servicio) {
                return true;
            }
        }

        return false;
    }

    // Cuándo se avisa

    /**
     * Se guardó algo en una partida entre personas: una jugada, un reparto, un turno vencido. Si con eso
     * le toca a alguien que pidió avisos y no tiene la mesa a la vista, se encola su aviso.
     *
     * Sale recién cuando se confirma lo guardado, y un error de acá nunca llega a la jugada.
     */
    public function partidaCambio(Partida $partida, int $evento, string $tipo): void
    {
        if (! in_array($tipo, [EventoDePartida::ACCION, EventoDePartida::VENCIMIENTO, EventoDePartida::REPARTO], true)) {
            return;
        }

        $partidaId = (int) $partida->getKey();

        DB::afterCommit(function () use ($partidaId, $evento, $tipo) {
            try {
                $this->avisarAQuienLeToca($partidaId, $evento, $tipo);
            } catch (Throwable $falla) {
                report($falla);
            }
        });
    }

    /**
     * Alguien se sentó en una sala: se le avisa a quien la abrió, que puede estar en otra aplicación
     * mandando el link.
     */
    public function seSentoElRival(Partida $partida): void
    {
        $anfitrion = (int) $partida->jugador_id;
        $apodo = $partida->invitado?->apodo ?? 'Tu rival';
        $partidaId = (int) $partida->getKey();

        DB::afterCommit(function () use ($anfitrion, $apodo, $partidaId) {
            try {
                $this->encolar($anfitrion, [
                    'titulo' => "{$apodo} se sentó",
                    'cuerpo' => 'Empieza la partida: te esperan en la mesa.',
                    'etiqueta' => "partida-{$partidaId}",
                    'url' => route('mesa', absolute: false),
                ], $partidaId);
            } catch (Throwable $falla) {
                report($falla);
            }
        });
    }

    private function avisarAQuienLeToca(int $partidaId, int $evento, string $tipo): void
    {
        $partida = Partida::query()->find($partidaId);

        // Solo entre dos personas sentadas. De a cuatro, hoy los otros tres son bots.
        if ($partida === null || ! $partida->enCurso() || ! $partida->entre_personas || $partida->deACuatro() || $partida->invitado_id === null) {
            return;
        }

        $jugadores = [Mesa::JUGADOR => (int) $partida->jugador_id, Mesa::BOT => (int) $partida->invitado_id];

        // Lo más común es que ninguno de los dos haya pedido avisos: ahí no hay nada que averiguar.
        if (! SuscripcionDeAviso::query()->whereIn('jugador_id', $jugadores)->exists()) {
            return;
        }

        $motor = app(Mesa::class)->reconstruir($partida);

        if ($motor->fase() !== Fase::Jugando) {
            return;
        }

        $esperado = collect(array_keys($jugadores))->first(fn (int $asiento) => $motor->accionesPara($asiento) !== []);

        if ($esperado === null) {
            return;
        }

        $vista = $motor->vistaPara($esperado);

        // El primer reparto es el de recién sentados: de eso ya se avisó con "se sentó".
        if ($tipo === EventoDePartida::REPARTO && $vista['numeroDeMano'] === 1) {
            return;
        }

        $rival = ($esperado === Mesa::JUGADOR ? $partida->invitado : $partida->jugador)?->apodo ?? 'Tu rival';

        $this->encolar($jugadores[$esperado], self::textoDelTurno($vista, $rival, $partidaId), $partidaId, $evento);
    }

    /**
     * Lo que dice el aviso de un turno. Nunca nombra una carta: se lee en la pantalla bloqueada del celular.
     *
     * @param  array<string, mixed>  $vista
     * @return array{titulo: string, cuerpo: string, etiqueta: string, url: string}
     */
    public static function textoDelTurno(array $vista, string $rival, int $partidaId): array
    {
        $canto = match ($vista['pendiente']['canto'] ?? null) {
            'truco' => self::NIVEL_DE_TRUCO[$vista['truco']['nivel']] ?? null,
            'envido' => self::CANTOS[(string) end($vista['envido']['cadena'])] ?? 'envido',
            'contraflor' => self::CANTOS[(string) $vista['flor']['contra']] ?? 'contraflor',
            default => null,
        };

        return [
            'titulo' => $canto === null ? 'Te toca jugar' : "{$rival} te cantó {$canto}",
            'cuerpo' => $canto === null ? "{$rival} te espera en la mesa." : 'Contestá en la mesa.',
            'etiqueta' => "partida-{$partidaId}",
            'url' => route('mesa', absolute: false),
        ];
    }

    /**
     * Encola el aviso si esa persona pidió avisos y no tiene la mesa a la vista. Sale con unos segundos de
     * demora: si en ese rato juega o vuelve a la mesa, no se manda.
     *
     * @param  array{titulo: string, cuerpo: string, etiqueta: string, url: string}  $aviso
     */
    private function encolar(int $jugadorId, array $aviso, int $partidaId, ?int $evento = null): void
    {
        if (! $this->tieneAvisos($jugadorId) || $this->presencia->aLaVista($jugadorId)) {
            return;
        }

        AvisarAlJugador::dispatch($jugadorId, $aviso, $partidaId, $evento)->delay(now()->addSeconds((int) config('avisos.gracia')));
    }

    // Mandarlo

    /**
     * Manda el aviso a todos los navegadores del jugador, si todavía corresponde. Lo corre el trabajo en cola.
     *
     * @param  array{titulo: string, cuerpo: string, etiqueta: string, url: string}  $aviso
     * @return int Cuántos navegadores lo recibieron.
     */
    public function entregar(int $jugadorId, array $aviso, int $partidaId, ?int $evento): int
    {
        $partida = Partida::query()->find($partidaId);

        if ($partida === null || ! $partida->enCurso() || $this->presencia->aLaVista($jugadorId)) {
            return 0;
        }

        // Si después pasó algo en la mesa (jugó, se le venció el turno, se repartió), este aviso quedó viejo:
        // de lo nuevo, si hace falta, avisa otro trabajo.
        if ($evento !== null && $partida->eventos()->getQuery()->where('numero', '>', $evento)->whereNotIn('tipo', [EventoDePartida::LLEGADA, EventoDePartida::SENA])->exists()) {
            return 0;
        }

        $entregados = 0;

        foreach (SuscripcionDeAviso::query()->where('jugador_id', $jugadorId)->get() as $suscripcion) {
            $resultado = $this->cartero->entregar($suscripcion, $aviso);

            if ($resultado === Cartero::VENCIDA) {
                $suscripcion->delete();
            }

            $entregados += (int) ($resultado === Cartero::ENTREGADO);
        }

        return $entregados;
    }
}
