<?php

use App\Models\Jugador;
use App\Models\Partida;
use Illuminate\Support\Facades\Broadcast;

/*
 | El canal de una partida entre personas. Entran las dos personas que ocupan un asiento (quien la abrió
 | desde que está esperando, y quien se sentó con el link); nadie más. Por acá solo viajan avisos, sin cartas.
 */
Broadcast::channel('partida.{id}', function (Jugador $jugador, string $id) {
    $partida = Partida::query()->find((int) $id);

    return $partida !== null && $partida->entre_personas && $partida->asientoDe($jugador) !== null;
});
