<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Algo que pasó en una partida: un reparto (con las cartas de cada asiento),
 * una acción de un asiento o un abandono. Los eventos no se editan ni se borran:
 * aplicados en orden al motor, devuelven la partida tal como quedó.
 *
 * @property int $partida_id
 * @property int $numero
 * @property string $tipo
 * @property int|null $asiento
 * @property array<string, mixed> $datos
 */
#[Table('eventos_de_partida')]
#[Fillable(['numero', 'tipo', 'asiento', 'datos', 'creado_en'])]
class EventoDePartida extends Model
{
    public const REPARTO = 'reparto';

    public const ACCION = 'accion';

    /** Lo que hizo el servidor por quien dejó vencer su turno: es una acción más, pero no la eligió esa persona. */
    public const VENCIMIENTO = 'vencimiento';

    public const ABANDONO = 'abandono';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'asiento' => 'integer',
            'datos' => 'array',
            'creado_en' => 'datetime',
        ];
    }
}
