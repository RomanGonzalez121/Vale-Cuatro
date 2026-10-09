<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un pedido de revancha entre dos personas: quién lo hizo, sobre qué partida y en qué quedó.
 * Si nadie lo acepta no deja nada jugado: la partida nueva nace recién con el "quiero".
 *
 * @property int $partida_id
 * @property int $asiento
 * @property string $estado
 * @property Carbon $vence_en
 */
#[Table('revanchas')]
#[Fillable(['partida_id', 'asiento', 'vence_en'])]
class Revancha extends Model
{
    public const PENDIENTE = 'pendiente';

    public const ACEPTADA = 'aceptada';

    public const RECHAZADA = 'rechazada';

    public const CANCELADA = 'cancelada';

    /** Se quiso, pero ya no podía salir: alguno de los dos había entrado a otra partida. */
    public const CAIDA = 'caida';

    protected $attributes = [
        'estado' => self::PENDIENTE,
    ];

    /**
     * Sin contestar y todavía a tiempo. Pasada la hora, el pedido se venció solo: nadie tiene que marcarlo.
     */
    public function vigente(): bool
    {
        return $this->estado === self::PENDIENTE && now()->getTimestamp() < $this->vence_en->getTimestamp();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'partida_id' => 'integer',
            'asiento' => 'integer',
            'vence_en' => 'datetime',
        ];
    }
}
