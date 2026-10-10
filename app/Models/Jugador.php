<?php

namespace App\Models;

use Database\Factories\JugadorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use RuntimeException;

/**
 * Quien se sienta a la mesa. Hay dos clases de jugador en la misma tabla:
 * el que tiene cuenta (email y contraseña) y el invitado, que solo tiene un
 * apodo sorteado. Un invitado que se registra conserva su fila y lo que jugó.
 *
 * Y hay una tercera fila que no es de nadie: el jugador de ejemplo del ranking.
 * Juega partidas simuladas contra los otros de ejemplo y el sitio lo marca como bot.
 * No tiene email ni contraseña, así que nadie puede ingresar como él.
 *
 * @property int $id
 * @property string $apodo
 * @property string|null $email
 * @property bool $de_ejemplo
 * @property bool $es_administrador
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
     * quien no cargó un email. Un jugador de ejemplo tampoco tiene, pero no es una persona.
     */
    public function esInvitado(): bool
    {
        return $this->email === null && ! $this->esDeEjemplo();
    }

    /**
     * Un jugador de ejemplo del ranking: simulado, y dicho así en cada lugar donde aparece.
     */
    public function esDeEjemplo(): bool
    {
        return (bool) $this->de_ejemplo;
    }

    /**
     * Quien puede entrar al panel de administración. El rol no se asigna en masa ni desde ninguna
     * pantalla: lo otorga un comando de consola (administrador:dar).
     */
    public function esAdministrador(): bool
    {
        return (bool) $this->es_administrador;
    }

    /**
     * Le cambia el apodo por uno neutro, "Jugador" y un número sorteado. Es lo que hace el panel con un
     * apodo ofensivo: como el apodo vive en un solo lugar, deja de verse en todas las pantallas. Su dueño
     * puede elegir otro desde el perfil. Devuelve el apodo que tenía.
     */
    public function ocultarApodo(): string
    {
        $anterior = $this->apodo;

        // Igual que con los invitados: se guarda directo y decide el índice único de la tabla.
        for ($cifras = 5; $cifras <= 12; $cifras++) {
            try {
                $this->update(['apodo' => 'Jugador '.random_int(10 ** ($cifras - 1), 10 ** $cifras - 1)]);

                return $anterior;
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new RuntimeException('No se pudo sortear un apodo neutro.');
    }

    /**
     * Lo que borra `model:prune` cada día: los invitados que no volvieron.
     * Los jugadores de ejemplo no se tocan: no vuelven nunca, y la tabla los necesita.
     *
     * Tampoco el invitado que abrió una partida que jugó otra persona. Esa partida es de los dos: con
     * él se irían sus eventos, y el otro la perdería de su historial y del ranking.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNull('email')
            ->where('de_ejemplo', false)
            ->whereNotExists(fn (QueryBuilder $consulta) => $consulta
                ->from('partidas')
                ->whereColumn('partidas.jugador_id', 'jugadores.id')
                ->whereNotNull('partidas.invitado_id'))
            ->where('updated_at', '<', now()->subDays(self::DIAS_DE_INVITADO));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'de_ejemplo' => 'boolean',
            'es_administrador' => 'boolean',
        ];
    }
}
