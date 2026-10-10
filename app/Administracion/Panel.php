<?php

namespace App\Administracion;

use App\Juego\Mesa;
use App\Models\AccionDeAdministracion;
use App\Models\Jugador;
use App\Models\Partida;
use Illuminate\Contracts\Queue\Factory as Colas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Lo que el panel de administración muestra: cómo está el sitio ahora. Solo lee.
 *
 * De una partida en curso dice quiénes juegan, cómo va el tanteo y cuándo se movió por última vez.
 * Nunca una carta: cada renglón se arma eligiendo esos datos uno por uno, y la vista de un asiento o
 * el estado del motor no salen de acá.
 */
final class Panel
{
    /** Cuántas partidas se listan de cada clase (quietas y en juego). Los totales cuentan todas. */
    public const PARTIDAS_A_LA_VISTA = 20;

    /** Cuántos jugadores devuelve una búsqueda por apodo. */
    public const JUGADORES_POR_BUSQUEDA = 20;

    /** Cuántas anotaciones del cuaderno se muestran. */
    public const ANOTACIONES_A_LA_VISTA = 30;

    public function __construct(
        private readonly Mesa $mesa,
        private readonly Colas $colas,
        private readonly FailedJobProviderInterface $fallidos,
    ) {}

    /**
     * Las partidas en curso, en dos listas: las que quedaron sin movimiento (que se pueden cerrar),
     * empezando por la que hace más que está quieta, y las que se están jugando, empezando por la
     * que se movió último. Cada lista trae hasta PARTIDAS_A_LA_VISTA; los dos totales cuentan todas.
     *
     * @return array{quietas: list<array<string, mixed>>, enJuego: list<array<string, mixed>>, cuantasQuietas: int, cuantasEnJuego: int}
     */
    public function partidas(): array
    {
        $quietas = $this->mesa->enCursoQuietas();
        $enJuego = $this->mesa->enCursoQueSeMueven();

        return [
            'quietas' => $this->filas($quietas, masViejasPrimero: true, quietas: true),
            'enJuego' => $this->filas($enJuego, masViejasPrimero: false, quietas: false),
            'cuantasQuietas' => (clone $quietas)->count(),
            'cuantasEnJuego' => (clone $enJuego)->count(),
        ];
    }

    /**
     * Cuántos trabajos esperan en la cola y cuáles fallaron. De un trabajo fallido se dice qué era, cuándo
     * falló y la clase del error, sin su mensaje: el mensaje de un error de la base trae la consulta
     * entera, y ahí puede venir el reparto de una partida que todavía se juega. El detalle queda en el
     * registro del servidor.
     *
     * @return array{esperan: int, fallidos: list<array{id: string, trabajo: string, error: string, cuando: Carbon}>}
     */
    public function cola(): array
    {
        $fallidos = [];

        foreach ($this->fallidos->all() as $fallido) {
            $fallidos[] = [
                'id' => (string) $fallido->id,
                'trabajo' => self::nombreDelTrabajo($fallido),
                'error' => preg_match('/^[\w\\\\]+/', (string) $fallido->exception, $clase) === 1 ? class_basename($clase[0]) : 'Error',
                'cuando' => Carbon::parse($fallido->failed_at),
            ];
        }

        return ['esperan' => (int) $this->colas->connection()->size(), 'fallidos' => $fallidos];
    }

    /**
     * Qué trabajo era uno que falló, por su nombre corto ("TurnoDelBot").
     */
    public static function nombreDelTrabajo(object $fallido): string
    {
        return class_basename((string) (json_decode((string) $fallido->payload, true)['displayName'] ?? 'Trabajo'));
    }

    /**
     * Lo que se llevaría la limpieza si corriera ahora: las salas que esperaron rival de más y los
     * invitados que no volvieron. Son las mismas consultas que usa la limpieza.
     *
     * @return array{salas: int, invitados: int}
     */
    public function porLimpiar(): array
    {
        return [
            'salas' => $this->mesa->salasVencidas()->count(),
            'invitados' => (new Jugador)->prunable()->count(),
        ];
    }

    /**
     * Los jugadores cuyo apodo contiene ese texto. Los de ejemplo no entran: sus apodos los puso el sitio.
     * Del jugador se muestra lo que ya es público (el apodo) y si tiene cuenta; el email no.
     *
     * @return list<array{id: int, apodo: string, conCuenta: bool, neutro: bool}>
     */
    public function jugadores(string $apodo): array
    {
        $texto = trim($apodo);

        if ($texto === '') {
            return [];
        }

        // El texto se busca tal cual: un "%" o un "_" escritos en la búsqueda no son comodines. Se los
        // marca con "!" y se le dice a la base que esa es la marca, porque MySQL y SQLite no usan la misma.
        $textual = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $texto);

        return Jugador::query()
            ->where('de_ejemplo', false)
            ->whereRaw("apodo like ? escape '!'", ["%{$textual}%"])
            ->orderBy('apodo')
            ->limit(self::JUGADORES_POR_BUSQUEDA)
            ->get()
            ->map(fn (Jugador $jugador) => [
                'id' => $jugador->id,
                'apodo' => $jugador->apodo,
                'conCuenta' => $jugador->email !== null,
                // Un apodo que ya es de los sorteados no tiene nada que ocultar.
                'neutro' => preg_match('/^(Jugador|Invitado) \d+$/', $jugador->apodo) === 1,
            ])
            ->all();
    }

    /**
     * Las últimas anotaciones del cuaderno, de la más nueva a la más vieja.
     *
     * @return list<array{quien: string, detalle: string, cuando: Carbon}>
     */
    public function cuaderno(): array
    {
        return AccionDeAdministracion::query()
            ->with('administrador')
            ->latest('creada_en')
            ->latest('id')
            ->limit(self::ANOTACIONES_A_LA_VISTA)
            ->get()
            ->map(fn (AccionDeAdministracion $accion) => [
                'quien' => $accion->administrador?->apodo ?? 'Alguien que ya no está',
                'detalle' => $accion->detalle,
                'cuando' => $accion->creada_en,
            ])
            ->all();
    }

    /**
     * @param  Builder<Partida>  $partidas
     * @return list<array<string, mixed>>
     */
    private function filas(Builder $partidas, bool $masViejasPrimero, bool $quietas): array
    {
        return (clone $partidas)
            ->withMax('eventos', 'creado_en')
            ->with(['jugador', 'invitado'])
            ->orderBy('eventos_max_creado_en', $masViejasPrimero ? 'asc' : 'desc')
            ->orderBy('id')
            ->limit(self::PARTIDAS_A_LA_VISTA)
            ->get()
            ->map(fn (Partida $partida) => $this->fila($partida, $quietas))
            ->all();
    }

    /**
     * Un renglón de la lista de partidas. Lo único que sale del motor son dos números: el tanteo y
     * la mano que se juega.
     *
     * Si la partida no se puede volver a pasar por el motor (un evento dañado, una regla que cambió), el
     * renglón sale igual, sin tanteo: es justo la partida que hay que poder cerrar desde acá.
     *
     * @return array{id: int, uno: string, otro: string, contraElBot: bool, enSerie: bool, tanteo: array{0: int, 1: int}|null, mano: int|null, movida: Carbon, quieta: bool}
     */
    private function fila(Partida $partida, bool $quieta): array
    {
        try {
            $motor = $this->mesa->reconstruir($partida);
            $tanteo = [$motor->tanteo()[Mesa::JUGADOR], $motor->tanteo()[Mesa::BOT]];
            $mano = (int) $motor->aArray()['numeroDeMano'];
        } catch (Throwable $falla) {
            report($falla);
            $tanteo = $mano = null;
        }

        return [
            'id' => $partida->id,
            'uno' => $partida->jugador?->apodo ?? 'Alguien que ya no está',
            'otro' => $partida->entre_personas
                ? ($partida->invitado?->apodo ?? 'Alguien que ya no está')
                : 'Bot '.mb_strtolower($partida->nivel_bot->nombre()),
            'contraElBot' => ! $partida->entre_personas,
            'enSerie' => $partida->serie_id !== null,
            'tanteo' => $tanteo,
            'mano' => $mano,
            'movida' => Carbon::parse($partida->getAttribute('eventos_max_creado_en') ?? $partida->created_at),
            'quieta' => $quieta,
        ];
    }
}
