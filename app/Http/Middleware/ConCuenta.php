<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja pasar solo a quien tiene cuenta. Al invitado lo manda a crearla y al
 * que no tiene sesión, a ingresar.
 */
class ConCuenta
{
    public function handle(Request $request, Closure $next): Response
    {
        $jugador = $request->user();

        if ($jugador === null) {
            return redirect()->guest(route('ingresar'));
        }

        if ($jugador->esInvitado()) {
            return redirect()->route('registro');
        }

        return $next($request);
    }
}
