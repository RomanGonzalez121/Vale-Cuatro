<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un navegador de un jugador que pidió que le avisen. Un jugador puede tener varios (el celular y la
 * computadora); un navegador es de un solo jugador a la vez.
 *
 * @property int $jugador_id
 * @property string $huella
 * @property string $destino
 * @property string $p256dh
 * @property string $auth
 */
#[Table('suscripciones_de_aviso')]
#[Fillable(['jugador_id', 'huella', 'destino', 'p256dh', 'auth'])]
class SuscripcionDeAviso extends Model
{
    /**
     * Con qué se busca una dirección de avisos: es larga y no entra en un índice.
     */
    public static function huellaDe(string $destino): string
    {
        return hash('sha256', $destino);
    }

    /**
     * @return BelongsTo<Jugador, $this>
     */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class);
    }
}
