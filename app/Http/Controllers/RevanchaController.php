<?php

namespace App\Http\Controllers;

use App\Juego\Mesa;
use App\Juego\Revanchas;
use App\Models\Partida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * La revancha, desde el final de una partida: pedirla, quererla, no quererla o cancelarla.
 *
 * Cada pedido dice de qué partida habla, y el servidor la busca solo entre las de quien pregunta: nadie
 * puede pedir ni contestar la revancha de una partida ajena. El asiento sale de la partida, no del navegador.
 *
 * Cada respuesta dice en qué quedó la revancha para quien preguntó. Contra el bot, el botón del final es
 * un formulario común: ahí se contesta llevando a la mesa, donde ya está la partida nueva.
 */
class RevanchaController extends Controller
{
    public function __construct(private readonly Mesa $mesa, private readonly Revanchas $revanchas) {}

    public function estado(Request $request): JsonResponse
    {
        $partida = $this->partida($request);

        return response()->json($this->revanchas->estado($partida, $this->asientoDe($partida, $request)));
    }

    public function pedir(Request $request): JsonResponse|RedirectResponse
    {
        $partida = $this->partida($request);
        $estado = $this->revanchas->pedir($partida, $this->asientoDe($partida, $request));

        if ($request->expectsJson()) {
            return response()->json($estado);
        }

        return $estado['estado'] === Revanchas::ACEPTADA
            ? redirect()->route('mesa')
            : redirect()->route('modos')->with('aviso', 'No se pudo armar la revancha.');
    }

    public function aceptar(Request $request): JsonResponse
    {
        $partida = $this->partida($request);

        return response()->json($this->revanchas->aceptar($partida, $this->asientoDe($partida, $request)));
    }

    public function rechazar(Request $request): JsonResponse
    {
        $partida = $this->partida($request);

        return response()->json($this->revanchas->rechazar($partida, $this->asientoDe($partida, $request)));
    }

    public function cancelar(Request $request): JsonResponse
    {
        $partida = $this->partida($request);

        return response()->json($this->revanchas->cancelar($partida, $this->asientoDe($partida, $request)));
    }

    /**
     * La partida de la que se habla, buscada solo entre las de quien hace el pedido. Si no es suya, para
     * el servidor no existe.
     */
    private function partida(Request $request): Partida
    {
        $datos = $request->validate(['partida' => ['required', 'integer']]);

        return $this->mesa->deJugador($request->user(), (int) $datos['partida']) ?? abort(404);
    }

    private function asientoDe(Partida $partida, Request $request): int
    {
        return $partida->asientoDe($request->user()) ?? abort(404);
    }
}
