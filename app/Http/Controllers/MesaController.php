<?php

namespace App\Http\Controllers;

use App\Juego\Mesa;
use App\Models\Partida;
use App\Motor\Accion;
use App\Motor\AccionInvalida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Lo que el navegador le pide a la mesa mientras se juega. Siempre trabaja
 * sobre la partida en curso de quien hace el pedido: no recibe un número de
 * partida, así que nadie puede tocar la de otro.
 *
 * Cada respuesta trae el paso de la jugada propia, y cada paso es la vista del
 * asiento del jugador. El bot juega aparte, desde la cola: sus pasos se piden
 * con la consulta corta. Las cartas del bot no viajan nunca.
 */
class MesaController extends Controller
{
    public function __construct(private readonly Mesa $mesa) {}

    /**
     * La mesa con la partida en curso. Recargar la página vuelve a la misma partida, en el mismo punto.
     * No crea nada: sin partida en curso, manda a elegir el modo.
     */
    public function ver(Request $request): View|RedirectResponse
    {
        $partida = $this->mesa->enCursoDe($request->user());

        if ($partida === null) {
            return redirect()->route('modos')->with('aviso', $this->comoTermino($request));
        }

        return view('paginas.mesa', [
            'vista' => $this->mesa->vista($partida, $this->asientoDe($partida, $request)),
            'nivel' => $partida->nivel_bot,
        ]);
    }

    /**
     * La consulta corta de la mesa. Con "desde" (el número del último evento que ya mostró) devuelve
     * los pasos posteriores: así se entera de lo que jugó el bot, también si esa jugada cerró la partida.
     * Sin "desde" devuelve cómo está la partida ahora, para ponerse al día cuando una jugada no entró.
     */
    public function estado(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'desde' => ['sometimes', 'integer', 'min:0'],
            'partida' => ['required_with:desde', 'integer'],
        ]);
        $partida = $this->mesa->ultimaDe($request->user());
        $sinPartida = response()->json(['motivo' => 'No tenés una partida en curso.'], 409);

        if ($partida === null) {
            return $sinPartida;
        }

        $asiento = $this->asientoDe($partida, $request);

        if (! isset($datos['desde'])) {
            return $partida->enCurso() ? response()->json(['vista' => $this->mesa->vista($partida, $asiento)]) : $sinPartida;
        }

        // La pestaña que pregunta puede haber quedado con una partida vieja (se abandonó desde otra y se empezó una nueva).
        if ($partida->id !== (int) $datos['partida']) {
            return $sinPartida;
        }

        $pasos = $this->mesa->pasosDesde($partida, (int) $datos['desde'], $asiento);

        // Cerrada y sin nada nuevo que contar: no hay bot al que esperar. La mesa se recarga y el servidor decide.
        return $pasos === [] && ! $partida->enCurso() ? $sinPartida : response()->json(['pasos' => $pasos]);
    }

    /**
     * La red de seguridad: la mesa lo pide si pasaron unos segundos y el bot no jugó.
     * El bot juega ahí mismo lo que le toque; lo que jugó llega por la consulta corta.
     */
    public function despertarAlBot(Request $request): JsonResponse
    {
        $partida = $this->mesa->enCursoDe($request->user());

        if ($partida === null) {
            return response()->json(['motivo' => 'No tenés una partida en curso.'], 409);
        }

        $this->mesa->despertarAlBot($partida);

        return response()->json(['listo' => true]);
    }

    public function accion(Request $request): JsonResponse
    {
        try {
            $accion = Accion::desdeArray($request->only(['tipo', 'carta']));
        } catch (InvalidArgumentException) {
            return $this->rechazo('Esa acción no existe.');
        }

        return $this->conLaPartida($request, fn (Partida $partida, int $asiento) => $this->mesa->actuar($partida, $accion, $asiento));
    }

    public function repartir(Request $request): JsonResponse
    {
        return $this->conLaPartida($request, fn (Partida $partida, int $asiento) => $this->mesa->repartir($partida, $asiento));
    }

    public function abandonar(Request $request): RedirectResponse
    {
        $partida = $this->mesa->enCursoDe($request->user());

        if ($partida !== null) {
            try {
                $this->mesa->abandonar($partida, $this->asientoDe($partida, $request));
            } catch (AccionInvalida) {
                // Otro pedido la cerró justo antes (dos toques seguidos): ya está abandonada.
            }
        }

        return redirect()->route('modos')->with('aviso', 'Abandonaste la partida.');
    }

    /**
     * El bot juega aparte, así que puede cerrar la partida mientras el jugador no está mirando
     * (salió para seguir después, o recargó justo). Al volver a la mesa se le cuenta cómo terminó.
     */
    private function comoTermino(Request $request): ?string
    {
        $ultima = $this->mesa->ultimaDe($request->user());

        if ($ultima?->estado !== Partida::TERMINADA) {
            return null;
        }

        $asiento = $this->asientoDe($ultima, $request);
        $tanteo = $this->mesa->reconstruir($ultima)->tanteo();
        $rival = $ultima->entre_personas ? 'tu rival' : 'el bot';

        return "Tu última partida terminó {$tanteo[$asiento]} a {$tanteo[1 - $asiento]}: ".($ultima->ganador === $asiento ? 'ganaste.' : "ganó {$rival}.");
    }

    /**
     * El asiento de quien hace el pedido, sacado de la partida y no de lo que mande el navegador:
     * nadie puede mirar ni mover las cartas del otro eligiendo un asiento. Si no es de la partida, no pasa.
     */
    private function asientoDe(Partida $partida, Request $request): int
    {
        return $partida->asientoDe($request->user()) ?? abort(403, 'Esa partida no es tuya.');
    }

    /**
     * @param  callable(Partida, int): list<array<string, mixed>>  $hacer
     */
    private function conLaPartida(Request $request, callable $hacer): JsonResponse
    {
        $partida = $this->mesa->enCursoDe($request->user());

        if ($partida === null) {
            return response()->json(['motivo' => 'No tenés una partida en curso.'], 409);
        }

        try {
            return response()->json(['pasos' => $hacer($partida, $this->asientoDe($partida, $request))]);
        } catch (AccionInvalida $rechazo) {
            return $this->rechazo($rechazo->getMessage());
        }
    }

    private function rechazo(string $motivo): JsonResponse
    {
        return response()->json(['motivo' => $motivo], 422);
    }
}
