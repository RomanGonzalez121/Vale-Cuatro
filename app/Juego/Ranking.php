<?php

namespace App\Juego;

use App\Models\Jugador;
use App\Models\Resultado;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * La tabla del ranking, armada con lo que cada partida cerrada dejó anotado (Resultado).
 *
 * Se ordena por partidas ganadas. Si dos empatan, va primero quien tiene mejor porcentaje, y si siguen
 * empatados, quien trae la racha más larga. En la tabla aparecen las cuentas y los jugadores de ejemplo
 * (marcados como bots). Quien juega sin cuenta no aparece, pero se le dice en qué puesto estaría.
 *
 * Es una sola consulta que trae un renglón por jugador; el orden y los puestos se resuelven acá.
 */
final class Ranking
{
    /** Cuántos puestos muestra la pantalla. Quien está más abajo ve el suyo en "Tu puesto". */
    public const A_LA_VISTA = 20;

    /**
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     propia: array<string, mixed>|null,
     *     deArriba: array<string, mixed>|null,
     * } Las filas a la vista en orden, la de quien mira (con su puesto, o el que tendría si juega sin
     *   cuenta) y la del puesto de arriba del suyo.
     */
    public function para(?Jugador $quienMira = null): array
    {
        $todas = $this->renglones($quienMira);
        usort($todas, self::comparar(...));

        // Quien juega sin cuenta no ocupa un puesto: se lo saca de la tabla y se mira dónde habría quedado.
        $enLaTabla = array_values(array_filter($todas, fn (array $fila) => $fila['enLaTabla']));

        foreach ($enLaTabla as $indice => $fila) {
            $enLaTabla[$indice]['puesto'] = $indice + 1;
        }

        $propia = null;

        foreach ($todas as $fila) {
            if ($fila['vos']) {
                $adelante = count(array_filter($enLaTabla, fn (array $otra) => ! $otra['vos'] && self::comparar($otra, $fila) < 0));
                $propia = [...$fila, 'puesto' => $adelante + 1];
            }
        }

        return [
            'filas' => array_slice($enLaTabla, 0, self::A_LA_VISTA),
            'propia' => $propia,
            'deArriba' => $propia === null ? null : $this->deArriba($enLaTabla, $propia),
        ];
    }

    /**
     * Un renglón por jugador con lo que suman sus partidas: jugadas, ganadas, envidos y la racha de ahora.
     * Entran las cuentas, los jugadores de ejemplo y quien está mirando (aunque juegue sin cuenta).
     *
     * @return list<array<string, mixed>>
     */
    private function renglones(?Jugador $quienMira): array
    {
        // La racha de ahora son las ganadas después de la última perdida, por la fecha en que se cerró
        // cada partida. Una persona juega de a una partida por vez, así que no cierra dos en el mismo segundo.
        $ultimaPerdida = Resultado::query()
            ->selectRaw('jugador_id, max(terminada_en) as ultima')
            ->where('gano', false)
            ->groupBy('jugador_id');

        $mira = $quienMira?->getKey();

        return DB::table('resultados as r')
            ->join('jugadores as j', 'j.id', '=', 'r.jugador_id')
            ->leftJoinSub($ultimaPerdida, 'p', 'p.jugador_id', '=', 'r.jugador_id')
            ->where(function (Builder $consulta) use ($mira) {
                $consulta->whereNotNull('j.email')->orWhere('j.de_ejemplo', true);

                if ($mira !== null) {
                    $consulta->orWhere('j.id', $mira);
                }
            })
            ->groupBy('j.id', 'j.apodo', 'j.email', 'j.de_ejemplo')
            ->selectRaw('j.id, j.apodo, j.de_ejemplo, case when j.email is null then 0 else 1 end as con_cuenta')
            ->selectRaw('count(*) as jugadas')
            ->selectRaw('sum(case when r.gano = 1 then 1 else 0 end) as ganadas')
            ->selectRaw('sum(r.envidos_jugados) as envidos_jugados, sum(r.envidos_ganados) as envidos_ganados')
            ->selectRaw('sum(case when r.gano = 1 and (p.ultima is null or r.terminada_en > p.ultima) then 1 else 0 end) as racha')
            ->get()
            ->map(fn (object $fila) => [
                'id' => (int) $fila->id,
                'apodo' => $fila->apodo,
                'bot' => (bool) $fila->de_ejemplo,
                'vos' => (int) $fila->id === $mira,
                'enLaTabla' => (bool) $fila->de_ejemplo || (bool) $fila->con_cuenta,
                'ganadas' => (int) $fila->ganadas,
                'jugadas' => (int) $fila->jugadas,
                'racha' => (int) $fila->racha,
                // El porcentaje de envidos ganados, o null si todavía no jugó ninguno.
                'envidos' => (int) $fila->envidos_jugados === 0 ? null : (int) round(100 * $fila->envidos_ganados / $fila->envidos_jugados),
            ])
            ->all();
    }

    /**
     * El orden de la tabla: más ganadas, después mejor porcentaje, después racha más larga. Si todo es
     * igual, primero quien se anotó antes en el sitio, para que el orden no cambie de una visita a otra.
     *
     * @param  array<string, mixed>  $una
     * @param  array<string, mixed>  $otra
     */
    private static function comparar(array $una, array $otra): int
    {
        return [$otra['ganadas'], $otra['ganadas'] * $una['jugadas'], $otra['racha'], $una['id']]
            <=> [$una['ganadas'], $una['ganadas'] * $otra['jugadas'], $una['racha'], $otra['id']];
    }

    /**
     * La fila del puesto de arriba del de quien mira: a quién tiene que alcanzar.
     *
     * @param  list<array<string, mixed>>  $enLaTabla
     * @param  array<string, mixed>  $propia
     * @return array<string, mixed>|null
     */
    private function deArriba(array $enLaTabla, array $propia): ?array
    {
        $deArriba = null;

        foreach ($enLaTabla as $fila) {
            if (! $fila['vos'] && self::comparar($fila, $propia) < 0) {
                $deArriba = $fila;
            }
        }

        return $deArriba;
    }
}
