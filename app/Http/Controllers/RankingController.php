<?php

namespace App\Http\Controllers;

use App\Juego\Mesa;
use App\Juego\Ranking;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RankingController extends Controller
{
    /**
     * La tabla, con el puesto de quien mira. Es pública: sin sesión se ve igual, sin "Tu puesto".
     */
    public function ver(Request $request, Ranking $ranking, Mesa $mesa): View
    {
        $jugador = $request->user();

        return view('paginas.ranking', [
            ...$ranking->para($jugador),
            'esInvitado' => $jugador?->esInvitado() ?? false,
            // Con una partida sin terminar, el botón la retoma en vez de ofrecer otra.
            'enCurso' => $jugador !== null && $mesa->enCursoDe($jugador) !== null,
        ]);
    }
}
