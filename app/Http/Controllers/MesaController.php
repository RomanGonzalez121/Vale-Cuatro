<?php

namespace App\Http\Controllers;

use App\Juego\Mesa;
use App\Models\Partida;
use App\Motor\Accion;
use App\Motor\AccionInvalida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Lo que el navegador le pide a la mesa mientras se juega. Siempre trabaja
 * sobre la partida en curso de quien hace el pedido: no recibe un número de
 * partida, así que nadie puede tocar la de otro.
 *
 * Cada respuesta trae los pasos que ocurrieron (la jugada propia y las del
 * bot), y cada paso es la vista del asiento del jugador. Las cartas del bot
 * no viajan nunca.
 */
class MesaController extends Controller
{
    public function __construct(private readonly Mesa $mesa) {}

    public function accion(Request $request): JsonResponse
    {
        try {
            $accion = Accion::desdeArray($request->only(['tipo', 'carta']));
        } catch (InvalidArgumentException) {
            return $this->rechazo('Esa acción no existe.');
        }

        return $this->conLaPartida($request, fn (Partida $partida) => $this->mesa->actuar($partida, $accion));
    }

    public function repartir(Request $request): JsonResponse
    {
        return $this->conLaPartida($request, fn (Partida $partida) => $this->mesa->repartir($partida));
    }

    public function abandonar(Request $request): RedirectResponse
    {
        $partida = $this->mesa->enCursoDe($request->user());

        if ($partida !== null) {
            $this->mesa->abandonar($partida);
        }

        return redirect()->route('modos')->with('aviso', 'Abandonaste la partida.');
    }

    /**
     * @param  callable(Partida): list<array<string, mixed>>  $hacer
     */
    private function conLaPartida(Request $request, callable $hacer): JsonResponse
    {
        $partida = $this->mesa->enCursoDe($request->user());

        if ($partida === null) {
            return response()->json(['motivo' => 'No tenés una partida en curso.'], 409);
        }

        try {
            return response()->json(['pasos' => $hacer($partida)]);
        } catch (AccionInvalida $rechazo) {
            return $this->rechazo($rechazo->getMessage());
        }
    }

    private function rechazo(string $motivo): JsonResponse
    {
        return response()->json(['motivo' => $motivo], 422);
    }
}
