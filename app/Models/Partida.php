<?php

namespace App\Models;

use App\Juego\Nivel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Una partida guardada. Lo que se jugó no está acá: está en sus eventos.
 * Esta fila dice de quién es, cómo arrancó y en qué quedó.
 *
 * Contra el bot, el jugador ocupa el asiento 0 y el bot el 1. Entre dos personas,
 * quien la creó ocupa el asiento 0 y quien se sienta con el link, el 1.
 *
 * Una partida simulada tiene la misma forma que una entre dos personas (dos jugadores,
 * uno en cada asiento), pero la jugaron dos bots: sus jugadores son de ejemplo.
 *
 * De a cuatro contra bots, el jugador ocupa el asiento 0, su compañero bot el 2 (enfrente) y los
 * dos bots rivales el 1 y el 3. Los equipos van intercalados, igual que en el motor.
 *
 * Un desafío es una sola mano armada contra el bot, con un objetivo: se cierra cuando esa mano termina,
 * y "ganador" dice si el objetivo se cumplió (el asiento del jugador) o no (el del bot). Un escalón de
 * la escalera del club es una de esas manos o una partida entera contra el bot.
 *
 * @property int $id
 * @property int $jugador_id
 * @property int|null $invitado_id
 * @property string $estado
 * @property int|null $primer_mano
 * @property int $puntos
 * @property int $asientos
 * @property bool $entre_personas
 * @property bool $simulada
 * @property string|null $codigo
 * @property int|null $serie_id
 * @property int|null $anterior_id
 * @property int|null $torneo_id
 * @property int $fosforos_de_gira
 * @property string|null $desafio
 * @property int|null $escalon
 * @property string|null $gira
 * @property Serie|null $serie
 * @property Nivel|null $nivel_bot
 * @property int|null $ganador
 * @property Carbon|null $terminada_en
 * @property Carbon|null $plazo_vence_en
 */
#[Table('partidas')]
#[Fillable(['jugador_id', 'invitado_id', 'primer_mano', 'puntos', 'asientos', 'entre_personas', 'codigo', 'nivel_bot'])]
class Partida extends Model
{
    /** Entre personas: creada, con su link, y todavía sin rival sentado. */
    public const ESPERANDO = 'esperando';

    public const EN_CURSO = 'en_curso';

    public const TERMINADA = 'terminada';

    public const ABANDONADA = 'abandonada';

    /**
     * La cerró la administración porque había quedado sin movimiento. No la ganó ni la dejó nadie: por
     * eso no es ni "terminada" ni "abandonada", y no entra en el historial ni en el ranking.
     */
    public const CERRADA = 'cerrada';

    /** Cuántos caracteres tiene el código del link: sorteado, no se puede adivinar. */
    private const LARGO_DEL_CODIGO = 16;

    /** Una partida recién creada ya está en curso y tiene su nivel, sin tener que volver a leerla de la base. */
    protected $attributes = [
        'estado' => self::EN_CURSO,
        'nivel_bot' => Nivel::Intermedio->value,
        'entre_personas' => false,
        'simulada' => false,
        'asientos' => 2,
    ];

    /**
     * Un código para el link de invitación.
     */
    public static function codigoNuevo(): string
    {
        return Str::lower(Str::random(self::LARGO_DEL_CODIGO));
    }

    /**
     * Quien creó la partida: el asiento 0.
     *
     * @return BelongsTo<Jugador, $this>
     */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class);
    }

    /**
     * Quien se sentó con el link, en una partida entre personas: el asiento 1.
     *
     * @return BelongsTo<Jugador, $this>
     */
    public function invitado(): BelongsTo
    {
        return $this->belongsTo(Jugador::class, 'invitado_id');
    }

    /**
     * La serie al mejor de tres de la que es parte, si lo es.
     *
     * @return BelongsTo<Serie, $this>
     */
    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    /**
     * El torneo del que es parte, si lo es.
     *
     * @return BelongsTo<Torneo, $this>
     */
    public function torneo(): BelongsTo
    {
        return $this->belongsTo(Torneo::class);
    }

    /**
     * @return HasMany<EventoDePartida, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(EventoDePartida::class)->orderBy('numero');
    }

    public function enCurso(): bool
    {
        return $this->estado === self::EN_CURSO;
    }

    /**
     * Si es de truco de a cuatro: dos parejas, con los compañeros enfrentados.
     */
    public function deACuatro(): bool
    {
        return $this->asientos === 4;
    }

    /**
     * Si es un gallito: tres jugadores, el gallo contra los otros dos, y el gallo cambia en cada mano.
     */
    public function esGallito(): bool
    {
        return $this->asientos === 3;
    }

    /**
     * Si se juega por equipos de más de uno: de a cuatro o el gallito. Ahí las máquinas juegan en pareja.
     */
    public function enEquipos(): bool
    {
        return $this->deACuatro() || $this->esGallito();
    }

    public function esperando(): bool
    {
        return $this->estado === self::ESPERANDO;
    }

    /**
     * Sin terminar: esperando rival o jugándose.
     */
    public function estaAbierta(): bool
    {
        return $this->esperando() || $this->enCurso();
    }

    /**
     * Si es una mano armada con un objetivo: un desafío suelto, o un escalón de la escalera que es una mano.
     */
    public function esDesafio(): bool
    {
        return $this->desafio !== null;
    }

    /**
     * Si es de un desafío, de la escalera del club o de la gira de los clubes. Lo que se juega ahí se puede
     * repetir hasta que salga: no cuenta para el ranking ni tiene revancha.
     */
    public function esDePractica(): bool
    {
        return $this->desafio !== null || $this->escalon !== null || $this->gira !== null;
    }

    /**
     * Si sigue a otra entre los mismos dos: es la siguiente de una serie, o una revancha.
     */
    public function esContinuacion(): bool
    {
        return $this->anterior_id !== null;
    }

    /**
     * El asiento que ocupa un jugador en esta partida, o null si no es de ella.
     */
    public function asientoDe(Jugador $jugador): ?int
    {
        return match (true) {
            $jugador->getKey() === $this->jugador_id => 0,
            $this->entre_personas && $this->invitado_id !== null && $jugador->getKey() === $this->invitado_id => 1,
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Siempre enteros: asientoDe() los compara con ===, y una diferencia de tipo dejaría a un jugador sin asiento.
            'jugador_id' => 'integer',
            'invitado_id' => 'integer',
            'serie_id' => 'integer',
            'anterior_id' => 'integer',
            'torneo_id' => 'integer',
            'fosforos_de_gira' => 'integer',
            'premios_nuevos' => 'array',
            'escalon' => 'integer',
            'primer_mano' => 'integer',
            'puntos' => 'integer',
            'asientos' => 'integer',
            'entre_personas' => 'boolean',
            'simulada' => 'boolean',
            'nivel_bot' => Nivel::class,
            'ganador' => 'integer',
            'terminada_en' => 'datetime',
            'plazo_vence_en' => 'datetime',
        ];
    }
}
