<?php

namespace App\Http\Controllers;

use App\Models\Jugador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * La puerta de entrada a la mesa. Quien llega sin sesión recibe un jugador
 * invitado en el momento, sin formulario. Es un POST porque crea datos: un
 * buscador o una precarga del navegador no deben fabricar jugadores.
 */
class JugarController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $jugador = $request->user();

        if ($jugador === null) {
            // Con "recordarme" el invitado sigue siendo el mismo aunque venza la sesión.
            Auth::login(Jugador::invitado(), remember: true);
            $request->session()->regenerate();
        } elseif ($jugador->esInvitado()) {
            // Anota que volvió a jugar: los invitados que no vuelven se borran solos.
            $jugador->touch();
        }

        return redirect()->route('mesa');
    }
}
