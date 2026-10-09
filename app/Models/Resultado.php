<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lo que una partida cerrada le cuenta al ranking sobre una de las personas que la jugó: si la ganó
 * y cómo le fue en los envidos. Lo escribe App\Juego\Estadisticas leyendo los eventos de la partida;
 * nadie más lo toca, y se puede rehacer entero desde ellos.
 *
 * @property int $id
 * @property int $partida_id
 * @property int $jugador_id
 * @property bool $gano
 * @property int $envidos_jugados
 * @property int $envidos_ganados
 * @property Carbon $terminada_en
 */
#[Table('resultados')]
#[Fillable(['partida_id', 'jugador_id', 'gano', 'envidos_jugados', 'envidos_ganados', 'terminada_en'])]
class Resultado extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Jugador, $this>
     */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class);
    }

    /**
     * @return BelongsTo<Partida, $this>
     */
    public function partida(): BelongsTo
    {
        return $this->belongsTo(Partida::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'partida_id' => 'integer',
            'jugador_id' => 'integer',
            'gano' => 'boolean',
            'envidos_jugados' => 'integer',
            'envidos_ganados' => 'integer',
            'terminada_en' => 'datetime',
        ];
    }
}
