<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cómo le fue a un jugador con un desafío suelto o con un escalón de la escalera: cuántas veces lo
 * jugó y cuándo lo pasó. Lo escribe App\Juego\Progresos al cerrarse cada partida; ninguna pantalla lo toca.
 *
 * @property int $id
 * @property int $jugador_id
 * @property string $clave
 * @property int $intentos
 * @property Carbon|null $superado_en
 */
#[Table('progresos')]
#[Fillable(['jugador_id', 'clave'])]
class Progreso extends Model
{
    protected $attributes = ['intentos' => 0];

    /**
     * @return BelongsTo<Jugador, $this>
     */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class);
    }

    public function superado(): bool
    {
        return $this->superado_en !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jugador_id' => 'integer',
            'intentos' => 'integer',
            'superado_en' => 'datetime',
        ];
    }
}
