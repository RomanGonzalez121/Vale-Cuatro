<?php

namespace App\Juego;

use App\Models\Jugador;
use App\Models\Partida;
use Illuminate\Support\Facades\DB;

/**
 * Los títulos: lo que dice debajo del apodo. No ocupan niveles: se ganan haciendo cosas en la mesa.
 *
 * Cada uno pide una cantidad de algo que se cuenta desde los hechos del motor: envidos cantados, envidos
 * ganados (los que se quisieron y se cantaron los tantos), trucos queridos ganados y flores cantadas. Las
 * cuentas se guardan en el jugador al cerrar cada partida, como los fósforos, así ninguna página cuenta
 * nada al abrirse; recalcular() las rehace desde las partidas.
 *
 * Los desafíos no cuentan: son la misma mano repetida, y reintentar no puede ser la forma de ganar un título.
 *
 * Cada título tiene sus dos formas y cada uno elige cómo se lee el suyo (decidido por Román el 15 de
 * octubre de 2026). Pichón es igual para todos.
 */
final class Titulos
{
    public const DE_FABRICA = 'pichon';

    /**
     * Las cuentas que se guardan en el jugador.
     */
    public const CUENTAS = ['envidos_cantados', 'envidos_ganados', 'trucos_ganados', 'flores_cantadas'];

    /**
     * Los títulos: sus dos formas, qué cuenta piden y cuánto.
     *
     * @var array<string, array{nombre: string, femenino: string, cuenta: string|null, pide: int}>
     */
    public const TODOS = [
        'pichon' => ['nombre' => 'Pichón', 'femenino' => 'Pichón', 'cuenta' => null, 'pide' => 0],
        'cantor' => ['nombre' => 'Cantor', 'femenino' => 'Cantora', 'cuenta' => 'envidos_cantados', 'pide' => 25],
        'tantero' => ['nombre' => 'Tantero', 'femenino' => 'Tantera', 'cuenta' => 'envidos_ganados', 'pide' => 25],
        'truquero' => ['nombre' => 'Truquero', 'femenino' => 'Truquera', 'cuenta' => 'trucos_ganados', 'pide' => 25],
        'florido' => ['nombre' => 'Florido', 'femenino' => 'Florida', 'cuenta' => 'flores_cantadas', 'pide' => 10],
    ];

    /**
     * Cómo se dice lo que pide cada cuenta, para la Colección.
     */
    public const QUE_PIDE = [
        'envidos_cantados' => 'envidos cantados',
        'envidos_ganados' => 'envidos ganados',
        'trucos_ganados' => 'trucos queridos ganados',
        'flores_cantadas' => 'flores cantadas',
    ];

    /**
     * Lo que hizo cada asiento en esa partida: asiento => cuentas. Solo los asientos de personas.
     *
     * @return array<int, array<string, int>>
     */
    public function en(Partida $partida, Mesa $mesa): array
    {
        $personas = Camino::personas($partida);

        // Una sala que se cerró sin que se sentara nadie no llegó a repartir: no tiene nada que contar.
        if ($personas === [] || $partida->esDesafio() || $partida->primer_mano === null
            || ! in_array($partida->estado, [Partida::TERMINADA, Partida::ABANDONADA], true)) {
            return [];
        }

        $cuentas = array_fill_keys(array_keys($personas), array_fill_keys(self::CUENTAS, 0));
        $deACuatro = $partida->deACuatro();
        $anterior = null;

        foreach ($mesa->pasoAPaso($partida) as [, $motor]) {
            // Si el motor es el mismo de antes, el evento no pasó por el motor y sus hechos ya se contaron.
            if ($motor === $anterior) {
                continue;
            }

            $anterior = $motor;

            foreach ($motor->hechos() as $hecho) {
                foreach (array_keys($personas) as $asiento) {
                    $equipo = $deACuatro ? $asiento % 2 : $asiento;

                    match (true) {
                        $hecho['tipo'] === 'canto' && $hecho['asiento'] === $asiento && in_array($hecho['canto'], ['envido', 'real_envido', 'falta_envido'], true) => $cuentas[$asiento]['envidos_cantados']++,
                        $hecho['tipo'] === 'canto' && $hecho['asiento'] === $asiento && $hecho['canto'] === 'flor' => $cuentas[$asiento]['flores_cantadas']++,
                        $hecho['tipo'] === 'tantos' && $hecho['canto'] === 'envido' && ($deACuatro ? $hecho['ganador'] % 2 : $hecho['ganador']) === $equipo => $cuentas[$asiento]['envidos_ganados']++,
                        // La mano se cerró con un truco querido (vale 2 o más) a favor de su equipo.
                        $hecho['tipo'] === 'puntos' && $hecho['concepto'] === 'truco' && $hecho['puntos'] >= 2 && $hecho['equipo'] === $equipo => $cuentas[$asiento]['trucos_ganados']++,
                        default => null,
                    };
                }
            }
        }

        return $cuentas;
    }

    /**
     * Le suma a cada persona lo que hizo en la partida recién cerrada. Lo llama la mesa una sola vez, al cerrarla.
     */
    public function anotar(Partida $partida, Mesa $mesa): void
    {
        foreach ($this->en($partida, $mesa) as $asiento => $cuentas) {
            $sumas = array_filter($cuentas);

            if ($sumas !== []) {
                Jugador::query()->whereKey(Camino::personas($partida)[$asiento])->incrementEach($sumas);
            }
        }
    }

    /**
     * Pone las cuentas en cero y las vuelve a sumar desde las partidas cerradas.
     */
    public function recalcular(Mesa $mesa): void
    {
        DB::transaction(function () use ($mesa) {
            $total = [];

            $cerradas = Partida::query()
                ->whereIn('estado', [Partida::TERMINADA, Partida::ABANDONADA])
                ->orderBy('id');

            foreach ($cerradas->lazy(200) as $partida) {
                foreach ($this->en($partida, $mesa) as $asiento => $cuentas) {
                    $jugador = Camino::personas($partida)[$asiento];

                    foreach ($cuentas as $cuenta => $cuanto) {
                        $total[$jugador][$cuenta] = ($total[$jugador][$cuenta] ?? 0) + $cuanto;
                    }
                }
            }

            Jugador::query()->update(array_fill_keys(self::CUENTAS, 0));

            foreach ($total as $jugador => $cuentas) {
                if (array_filter($cuentas) !== []) {
                    Jugador::query()->whereKey($jugador)->update($cuentas);
                }
            }
        });
    }

    /**
     * Si ese jugador ya ganó ese título.
     */
    public static function gano(?Jugador $jugador, string $titulo): bool
    {
        $datos = self::TODOS[$titulo] ?? null;

        return $datos !== null && ($datos['cuenta'] === null || (int) ($jugador?->{$datos['cuenta']} ?? 0) >= $datos['pide']);
    }

    /**
     * Cómo se lee un título en la forma que eligió.
     */
    public static function nombre(string $titulo, bool $femenino = false): string
    {
        $datos = self::TODOS[$titulo] ?? self::TODOS[self::DE_FABRICA];

        return $femenino ? $datos['femenino'] : $datos['nombre'];
    }
}
