<?php

namespace App\Http\Controllers;

use App\Juego\Mesa;
use App\Models\Jugador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * La puerta de entrada a la mesa. Quien llega sin sesión recibe un jugador
 * invitado en el momento, sin formulario. Es un POST porque crea datos: un
 * buscador o una precarga del navegador no deben fabricar jugadores ni partidas.
 */
class JugarController extends Controller
{
    public function __invoke(Request $request, Mesa $mesa): RedirectResponse
    {
        $jugador = $request->user();

        if ($jugador === null) {
            // Con "recordarme" el invitado sigue siendo el mismo aunque venza la sesión.
            Auth::login($jugador = Jugador::invitado(), remember: true);
            $request->session()->regenerate();
        } elseif ($jugador->esInvitado()) {
            // Anota que volvió a jugar: los invitados que no vuelven se borran solos.
            $jugador->touch();
        }

        // Retoma la partida que tenía sin terminar o empieza una nueva, ya repartida.
        $mesa->abrir($jugador);

        return redirect()->route('mesa');
    }
}
