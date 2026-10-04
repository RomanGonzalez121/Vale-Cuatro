<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registro e ingreso son para quien todavía no entró con una cuenta. El
 * invitado pasa: tiene sesión, pero no cuenta. Por eso no alcanza con el
 * filtro "guest" de Laravel, que lo dejaría afuera.
 */
class SinCuenta
{
    public function handle(Request $request, Closure $next): Response
    {
        $jugador = $request->user();

        if ($jugador !== null && ! $jugador->esInvitado()) {
            return redirect()->route('perfil');
        }

        return $next($request);
    }
}
