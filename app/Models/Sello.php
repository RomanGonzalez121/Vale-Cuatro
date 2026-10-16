<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un sello de la libreta: un escalón ganado de un logro (App\Juego\Logros).
 */
class Sello extends Model
{
    public $timestamps = false;

    protected $table = 'sellos';

    protected $fillable = ['jugador_id', 'logro', 'escalon', 'partida_id', 'ganado_en', 'visto'];

    protected function casts(): array
    {
        return [
            'escalon' => 'integer',
            'ganado_en' => 'datetime',
            'visto' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Jugador, $this>
     */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class);
    }
}
