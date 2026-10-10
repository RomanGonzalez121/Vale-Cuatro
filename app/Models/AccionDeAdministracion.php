<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una anotación del cuaderno del panel: quién hizo qué, y cuándo. No se edita ni se borra.
 *
 * @property int|null $administrador_id
 * @property string $accion
 * @property string $detalle
 * @property Carbon $creada_en
 */
#[Table('acciones_de_administracion')]
#[Fillable(['administrador_id', 'accion', 'detalle', 'creada_en'])]
class AccionDeAdministracion extends Model
{
    public const CERRAR_PARTIDA = 'cerrar_partida';

    public const REINTENTAR_TRABAJO = 'reintentar_trabajo';

    public const DESCARTAR_TRABAJO = 'descartar_trabajo';

    public const LIMPIAR = 'limpiar';

    public const OCULTAR_APODO = 'ocultar_apodo';

    public $timestamps = false;

    /**
     * @return BelongsTo<Jugador, $this>
     */
    public function administrador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class, 'administrador_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'administrador_id' => 'integer',
            'creada_en' => 'datetime',
        ];
    }
}
