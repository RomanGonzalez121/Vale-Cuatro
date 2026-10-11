<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * El par de claves con que el sitio firma los avisos que manda. La pública se le da a cada navegador que
 * se suscribe; la privada no sale del servidor y se guarda cifrada con la clave de la aplicación.
 *
 * @property string $publica
 * @property string $privada
 */
#[Table('claves_de_aviso')]
#[Fillable(['id', 'publica', 'privada'])]
class ClaveDeAviso extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['privada' => 'encrypted'];
    }
}
