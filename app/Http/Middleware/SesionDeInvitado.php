<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anota cuándo empezó la sesión de un invitado.
 *
 * Quien juega sin cuenta ve en su historial solo lo que jugó en esta sesión: cuando la sesión vence, la
 * lista arranca vacía aunque el navegador lo siga reconociendo. Las partidas no se borran (también son
 * del rival, y las van a usar el ranking y la administración): se dejan de mostrar. Quien tiene cuenta
 * conserva todo, y un invitado que se registra recupera lo de antes, porque sigue siendo el mismo jugador.
 *
 * La marca vive en la sesión y no en la base: una sesión nueva no la trae, y eso es justo lo que se quiere.
 */
class SesionDeInvitado
{
    private const CLAVE = 'invitado_desde';

    public function handle(Request $request, Closure $siguiente): Response
    {
        $respuesta = $siguiente($request);

        // Después del pedido, y no antes: el invitado puede haberse creado recién en este mismo pedido.
        if ($request->hasSession() && $request->user()?->esInvitado() && ! $request->session()->has(self::CLAVE)) {
            $request->session()->put(self::CLAVE, now()->getTimestamp());
        }

        return $respuesta;
    }

    /**
     * Desde cuándo se le muestran partidas a quien hace el pedido: null si tiene cuenta (todas), y para un
     * invitado, el comienzo de su sesión. Si la sesión todavía no tiene la marca es que acaba de empezar.
     */
    public static function desde(Request $request): ?Carbon
    {
        if (! $request->user()?->esInvitado()) {
            return null;
        }

        return Carbon::createFromTimestamp($request->session()->get(self::CLAVE, now()->getTimestamp()));
    }
}
