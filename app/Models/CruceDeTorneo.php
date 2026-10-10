<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un cruce de las llaves de un torneo: dos lugares, y quién de los dos pasó.
 *
 * Los de la primera ronda nacen completos, con el sorteo. Los de las rondas siguientes nacen vacíos y
 * se llenan con los ganadores de la anterior: el ganador del cruce 0 y el del 1 se encuentran en el 0
 * de la ronda siguiente, el del 2 y el del 3 en el 1, y así.
 *
 * @property int $id
 * @property int $torneo_id
 * @property int $ronda
 * @property int $orden
 * @property int|null $uno
 * @property int|null $dos
 * @property int|null $ganador
 * @property int|null $puntos_uno
 * @property int|null $puntos_dos
 * @property bool $por_abandono
 * @property int|null $partida_id
 */
#[Table('cruces_de_torneo')]
#[Fillable(['ronda', 'orden', 'uno', 'dos'])]
class CruceDeTorneo extends Model
{
    public $timestamps = false;

    protected $attributes = [
        'por_abandono' => false,
    ];

    /**
     * @return BelongsTo<Torneo, $this>
     */
    public function torneo(): BelongsTo
    {
        return $this->belongsTo(Torneo::class);
    }

    /**
     * La partida que jugó la persona en este cruce, si es uno de los suyos y ya la empezó.
     *
     * @return BelongsTo<Partida, $this>
     */
    public function partida(): BelongsTo
    {
        return $this->belongsTo(Partida::class);
    }

    /**
     * Ya se sabe quiénes lo juegan.
     */
    public function completo(): bool
    {
        return $this->uno !== null && $this->dos !== null;
    }

    public function resuelto(): bool
    {
        return $this->ganador !== null;
    }

    public function loJuega(int $lugar): bool
    {
        return $this->uno === $lugar || $this->dos === $lugar;
    }

    /**
     * El otro lugar del cruce.
     */
    public function rivalDe(int $lugar): ?int
    {
        return $this->uno === $lugar ? $this->dos : $this->uno;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'torneo_id' => 'integer',
            'ronda' => 'integer',
            'orden' => 'integer',
            'uno' => 'integer',
            'dos' => 'integer',
            'ganador' => 'integer',
            'puntos_uno' => 'integer',
            'puntos_dos' => 'integer',
            'por_abandono' => 'boolean',
            'partida_id' => 'integer',
        ];
    }
}
