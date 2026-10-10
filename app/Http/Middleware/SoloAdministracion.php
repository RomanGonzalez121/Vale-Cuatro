<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja pasar solo a la cuenta de administración. Para cualquier otro (sin sesión, invitado o con
 * cuenta) la dirección no existe: se contesta lo mismo que a una página que no está, así nadie se
 * entera de que hay un panel.
 */
class SoloAdministracion
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->esAdministrador() === true, 404);

        return $next($request);
    }
}
