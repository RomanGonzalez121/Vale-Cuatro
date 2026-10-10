<?php

namespace App\Models;

use App\Juego\Nivel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un torneo relámpago contra bots: eliminación directa entre una persona y tres o siete bots.
 *
 * Cada uno ocupa un "lugar", que es su número en la lista de inscriptos. Las llaves, los ganadores y el
 * campeón se dicen por lugar. De un bot se guardan el apodo y el nivel con el que juega; el lugar de la
 * persona no guarda nada: es el dueño del torneo.
 *
 * @property int $id
 * @property int $jugador_id
 * @property int $lugares
 * @property int $puntos
 * @property int $semilla
 * @property list<array{apodo: string|null, nivel: int|null}> $inscriptos
 * @property string $estado
 * @property int|null $campeon
 * @property Carbon|null $terminado_en
 */
#[Table('torneos')]
#[Fillable(['jugador_id', 'lugares', 'puntos', 'semilla', 'inscriptos'])]
class Torneo extends Model
{
    public const EN_CURSO = 'en_curso';

    public const TERMINADO = 'terminado';

    protected $attributes = [
        'estado' => self::EN_CURSO,
    ];

    /**
     * @return BelongsTo<Jugador, $this>
     */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class);
    }

    /**
     * Los cruces de las llaves, ronda por ronda y de arriba hacia abajo.
     *
     * @return HasMany<CruceDeTorneo, $this>
     */
    public function cruces(): HasMany
    {
        return $this->hasMany(CruceDeTorneo::class)->orderBy('ronda')->orderBy('orden');
    }

    public function enCurso(): bool
    {
        return $this->estado === self::EN_CURSO;
    }

    /**
     * Cuántas rondas tiene: dos con cuatro jugadores, tres con ocho.
     */
    public function rondas(): int
    {
        return (int) round(log($this->lugares, 2));
    }

    /**
     * El lugar que ocupa la persona: el único que no es de un bot.
     */
    public function lugarDeLaPersona(): int
    {
        foreach ($this->inscriptos as $lugar => $inscripto) {
            if ($inscripto['nivel'] === null) {
                return $lugar;
            }
        }

        return 0;
    }

    /**
     * El nivel con el que juega el bot de ese lugar, o null si es el de la persona.
     */
    public function nivelDe(int $lugar): ?Nivel
    {
        $nivel = $this->inscriptos[$lugar]['nivel'] ?? null;

        return $nivel === null ? null : Nivel::from($nivel);
    }

    /**
     * Cómo se llama una ronda, contando desde el final: la última es la final.
     */
    public function nombreDeRonda(int $ronda): string
    {
        return match ($this->rondas() - $ronda) {
            0 => 'Final',
            1 => 'Semifinales',
            2 => 'Cuartos de final',
            default => "Ronda {$ronda}",
        };
    }

    /**
     * Cómo se llama una sola partida de esa ronda: "Semifinal", no "Semifinales".
     */
    public function nombreDePartido(int $ronda): string
    {
        return match ($this->rondas() - $ronda) {
            0 => 'Final',
            1 => 'Semifinal',
            2 => 'Cuartos de final',
            default => "Ronda {$ronda}",
        };
    }

    /**
     * Cómo se llama quien ocupa un lugar: el apodo del bot, o el de la persona dueña del torneo.
     */
    public function apodoDe(int $lugar): string
    {
        return $this->inscriptos[$lugar]['apodo'] ?? $this->jugador?->apodo ?? 'Vos';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jugador_id' => 'integer',
            'lugares' => 'integer',
            'puntos' => 'integer',
            'semilla' => 'integer',
            'inscriptos' => 'array',
            'campeon' => 'integer',
            'terminado_en' => 'datetime',
        ];
    }
}
