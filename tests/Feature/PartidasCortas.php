<?php

namespace Tests\Feature;

use App\Juego\Mesa;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Serie;
use App\Motor\Accion;
use App\Motor\TipoDeAccion;

/**
 * Partidas a un solo punto, para los tests de las series y de la revancha: se cierran por la mesa de
 * verdad (ahí se decide qué pasa con la serie) en una o dos jugadas, y gana quien el test diga.
 *
 * El motor recibe los puntos de la partida como dato, y la partida siguiente de una serie copia los de
 * la anterior: una serie entera son media docena de jugadas, sin depender de las cartas que toquen.
 */
trait PartidasCortas
{
    private function laMesaDeVerdad(): Mesa
    {
        return $this->app->make(Mesa::class);
    }

    /**
     * Una partida entre dos personas a un punto, ya repartida. Con $enSerie, es la primera de una serie.
     */
    private function partidaCorta(Jugador $uno, Jugador $dos, bool $enSerie = false, int $primerMano = Mesa::JUGADOR): Partida
    {
        $partida = new Partida([
            'jugador_id' => $uno->id,
            'invitado_id' => $dos->id,
            'entre_personas' => true,
            'codigo' => Partida::codigoNuevo(),
            'nivel_bot' => null,
            'primer_mano' => $primerMano,
            'puntos' => 1,
        ]);

        $partida->serie_id = $enSerie ? Serie::create()->id : null;
        $partida->save();

        $this->laMesaDeVerdad()->repartir($partida, Mesa::JUGADOR);

        return $partida;
    }

    /**
     * Cierra la partida a favor de ese asiento: el otro se va al mazo apenas le toca. Si el turno es de
     * quien va a ganar, primero tira una carta.
     */
    private function ganar(Partida $partida, int $ganador): void
    {
        $mesa = $this->laMesaDeVerdad();
        $perdedor = 1 - $ganador;

        if ($mesa->vista($partida, $perdedor)['acciones'] === []) {
            $carta = collect($mesa->vista($partida, $ganador)['acciones'])->firstWhere('tipo', TipoDeAccion::Jugar->value);

            $mesa->actuar($partida, Accion::desdeArray($carta), $ganador);
        }

        $mesa->actuar($partida, Accion::de(TipoDeAccion::Mazo), $perdedor);
    }

    /**
     * La partida que siguió a esa entre los mismos dos, si existe.
     */
    private function laQueSiguioA(Partida $partida): ?Partida
    {
        return Partida::query()->where('anterior_id', $partida->id)->first();
    }
}
