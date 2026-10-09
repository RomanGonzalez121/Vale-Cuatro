<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una serie al mejor de tres: las partidas que dos jugadores acordaron jugar seguidas.
 *
 * Solo las agrupa. Cuántas ganó cada uno no está guardado: se cuenta mirando sus partidas. Los dos
 * ocupan el mismo asiento en todas, así que el marcador y el ganador se dicen por asiento.
 *
 * @property int $id
 * @property int $al_mejor_de
 * @property int|null $ganador
 * @property Carbon|null $terminada_en
 */
#[Table('series')]
#[Fillable(['al_mejor_de'])]
class Serie extends Model
{
    /** El único largo de serie que se ofrece. */
    public const AL_MEJOR_DE = 3;

    protected $attributes = [
        'al_mejor_de' => self::AL_MEJOR_DE,
    ];

    /**
     * @return HasMany<Partida, $this>
     */
    public function partidas(): HasMany
    {
        return $this->hasMany(Partida::class)->orderBy('id');
    }

    /**
     * Cuántas partidas hay que ganar para llevarse la serie: dos, al mejor de tres.
     */
    public function necesarias(): int
    {
        return intdiv($this->al_mejor_de, 2) + 1;
    }

    /**
     * Las partidas que ganó cada asiento, contando solo las que ya se cerraron.
     *
     * @return array{0: int, 1: int}
     */
    public function marcador(): array
    {
        $ganadas = $this->partidas()->getQuery()->whereNotNull('ganador')->reorder()->pluck('ganador');

        return [$ganadas->filter(fn ($ganador) => (int) $ganador === 0)->count(), $ganadas->filter(fn ($ganador) => (int) $ganador === 1)->count()];
    }

    /**
     * El marcador visto desde un asiento: primero las propias, después las del otro.
     *
     * @return array{0: int, 1: int}
     */
    public function marcadorDesde(int $asiento): array
    {
        $marcador = $this->marcador();

        return [$marcador[$asiento], $marcador[1 - $asiento]];
    }

    /**
     * Si alguna de sus partidas se cerró porque alguien se fue (o dejó de jugar): ahí la serie se cortó, y
     * su marcador no dice quién la ganó.
     */
    public function cortada(): bool
    {
        return $this->partidas()->getQuery()->where('estado', Partida::ABANDONADA)->whereNotNull('ganador')->exists();
    }

    public function cerrada(): bool
    {
        return $this->terminada_en !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'al_mejor_de' => 'integer',
            'ganador' => 'integer',
            'terminada_en' => 'datetime',
        ];
    }
}
