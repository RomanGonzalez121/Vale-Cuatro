<?php

namespace App\Juego;

use App\Models\Jugador;
use App\Models\Partida;

/**
 * El ambiente de una parada de la gira: cada barrio se juega en la mesa de su bar, el jefe trae su mazo y su
 * dorso, y algún rival común trae lo suyo (Doña Elvira, el bordado). Solo en la gira, lo del nivel le gana a lo
 * que eligió cada uno, salvo que la persona prenda "Jugar con mis cosas" en los ajustes de la mesa.
 *
 * Jugar con un premio del bar no lo destraba: el ambiente se pone para ese pedido y no se anota en ningún lado.
 */
final class Ambiente
{
    /**
     * Lo que trae la parada de esa partida: la mesa siempre; el mazo y el dorso, si alguien los trae. Null si la
     * partida no es de la gira (Boedo, la escalera, también lo es). La copa del barrio se juega en la mesa del bar.
     *
     * @return array{mesa: string, mazo: string|null, dorso: string|null}|null
     */
    public static function de(Partida $partida): ?array
    {
        [$clave, $numero] = Gira::deLaPartida($partida) ?? [null, null];

        if ($clave === null && $partida->torneo_id !== null && $partida->torneo?->gira !== null) {
            [$clave, $numero] = explode(':', $partida->torneo->gira);
        }

        $barrio = $clave === null ? null : Gira::barrio($clave);
        $nivel = $barrio === null ? null : Gira::nivel($clave, (int) $numero);

        return $nivel === null ? null : self::delNivel($barrio, $nivel);
    }

    /**
     * Lo que trae un nivel de un barrio, sin mirar ninguna partida: lo usa también la ficha de la parada.
     *
     * @param  array<string, mixed>  $barrio
     * @param  array<string, mixed>  $nivel
     * @return array{mesa: string, mazo: string|null, dorso: string|null}
     */
    public static function delNivel(array $barrio, array $nivel): array
    {
        $jefe = $nivel['tipo'] === 'jefe' ? $barrio['jefe'] : [];

        return [
            'mesa' => $nivel['mesa'] ?? $barrio['mesa'],
            'mazo' => $nivel['mazo'] ?? $jefe['mazo'] ?? null,
            'dorso' => $nivel['dorso'] ?? $jefe['dorso'] ?? null,
        ];
    }

    /**
     * Cómo se dice lo que trae alguien, para la ficha: "Trae su mazo Catalán y su dorso Grabado." Vacío si no trae nada.
     *
     * @param  array{mesa: string, mazo: string|null, dorso: string|null}  $ambiente
     */
    public static function loQueTrae(array $ambiente): string
    {
        $partes = array_filter([
            $ambiente['mazo'] === null ? null : 'su mazo '.Premios::POR_NIVEL['mazo'][$ambiente['mazo']]['nombre'],
            $ambiente['dorso'] === null ? null : 'su dorso '.Premios::DORSOS[$ambiente['dorso']]['nombre'],
        ]);

        return $partes === [] ? '' : 'Trae '.implode(' y ', $partes).'.';
    }

    /**
     * Si esa persona juega con lo del bar: siempre, salvo que haya prendido "Jugar con mis cosas".
     */
    public static function seImpone(?Jugador $jugador): bool
    {
        return ! ($jugador?->con_mis_cosas ?? false);
    }

    /**
     * Pone el ambiente como lo propio de este pedido: las mesas, el mazo y los dorsos de la página lo leen de
     * Premios::propia() y Premios::dorsoPropio(), que guardan su respuesta en el pedido.
     *
     * @param  array{mesa: string, mazo: string|null, dorso: string|null}  $ambiente
     */
    public static function poner(array $ambiente): void
    {
        $atributos = request()->attributes;
        $atributos->set('mesa-propia', $ambiente['mesa']);

        if ($ambiente['mazo'] !== null) {
            $atributos->set('mazo-propia', $ambiente['mazo']);
        }

        if ($ambiente['dorso'] !== null) {
            $atributos->set('dorso-propio', $ambiente['dorso']);
        }
    }
}
