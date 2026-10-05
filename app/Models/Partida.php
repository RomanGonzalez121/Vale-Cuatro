<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una partida guardada. Lo que se jugó no está acá: está en sus eventos.
 * Esta fila dice de quién es, cómo arrancó y en qué quedó.
 *
 * @property int $id
 * @property int $jugador_id
 * @property string $estado
 * @property int $primer_mano
 * @property int $puntos
 * @property int|null $ganador
 * @property Carbon|null $terminada_en
 */
#[Table('partidas')]
#[Fillable(['jugador_id', 'primer_mano', 'puntos'])]
class Partida extends Model
{
    public const EN_CURSO = 'en_curso';

    public const TERMINADA = 'terminada';

    public const ABANDONADA = 'abandonada';

    /** Una partida recién creada ya está en curso, sin tener que volver a leerla de la base. */
    protected $attributes = [
        'estado' => self::EN_CURSO,
    ];

    /**
     * @return BelongsTo<Jugador, $this>
     */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class);
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'primer_mano' => 'integer',
            'puntos' => 'integer',
            'ganador' => 'integer',
            'terminada_en' => 'datetime',
        ];
    }
}
