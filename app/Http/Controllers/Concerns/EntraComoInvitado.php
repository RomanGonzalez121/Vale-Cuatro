<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Jugador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Quien llega sin sesión a una puerta de entrada (jugar contra el bot, abrir una sala o sentarse
 * con un link) recibe un jugador invitado en el momento, sin formulario.
 */
trait EntraComoInvitado
{
    /**
     * Quien hace el pedido: su cuenta, o un invitado creado ahora y recordado en el navegador.
     */
    private function jugadorOInvitado(Request $request): Jugador
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

        return $jugador;
    }
}
