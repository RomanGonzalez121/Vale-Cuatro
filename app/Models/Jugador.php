<?php

namespace App\Models;

use Database\Factories\JugadorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use RuntimeException;

/**
 * Quien se sienta a la mesa. Hay dos clases de jugador en la misma tabla:
 * el que tiene cuenta (email y contraseña) y el invitado, que solo tiene un
 * apodo sorteado. Un invitado que se registra conserva su fila y lo que jugó.
 *
 * @property int $id
 * @property string $apodo
 * @property string|null $email
 */
#[Table('jugadores')]
#[Fillable(['apodo', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Jugador extends Authenticatable
{
    /** @use HasFactory<JugadorFactory> */
    use HasFactory, MassPrunable;

    /** Días sin jugar después de los cuales se borra un invitado. */
    public const DIAS_DE_INVITADO = 30;

    /**
     * Crea un invitado con un apodo libre: "Invitado" y un número sorteado.
     *
     * No pregunta antes si el número está libre, porque entre la pregunta y
     * el guardado otro pedido podría tomarlo. Guarda directo y deja que
     * decida el índice único de la tabla: si el número ya estaba, sortea otro
     * con una cifra más, así los apodos no se acaban ni se queda dando vueltas.
     */
    public static function invitado(): self
    {
        // Con 11 cifras el apodo ocupa los 20 caracteres de la columna.
        for ($cifras = 5; $cifras <= 11; $cifras++) {
            try {
                return self::create(['apodo' => 'Invitado '.random_int(10 ** ($cifras - 1), 10 ** $cifras - 1)]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new RuntimeException('No se pudo sortear un apodo de invitado.');
    }

    /**
     * No hay una marca aparte que pueda quedar desactualizada: es invitado
     * quien no cargó un email.
     */
    public function esInvitado(): bool
    {
        return $this->email === null;
    }

    /**
     * Lo que borra `model:prune` cada día: los invitados que no volvieron.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNull('email')
            ->where('updated_at', '<', now()->subDays(self::DIAS_DE_INVITADO));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
