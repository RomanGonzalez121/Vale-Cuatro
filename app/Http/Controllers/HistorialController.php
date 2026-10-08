<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SesionDeInvitado;
use App\Juego\Historial;
use App\Juego\Mesa;
use App\Juego\Repeticion;
use App\Models\Partida;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * El historial: la lista de partidas cerradas de quien entra y la repetición de cada una.
 *
 * Nada de lo que muestra está guardado aparte: el tanteo, las manos y cada jugada salen de volver a pasar
 * los eventos de la partida por el motor. Quien juega sin cuenta ve lo de su sesión; con cuenta, todo.
 *
 * La repetición se abre de dos maneras con los mismos datos: como un cartel sobre la lista, que los pide
 * a cuadros(), o como página propia, que es lo que se ve al recargar o al entrar directo a su dirección.
 */
class HistorialController extends Controller
{
    public function __construct(private readonly Historial $historial, private readonly Repeticion $repeticion) {}

    /**
     * La lista. Sin haber entrado nunca no hay jugador: se muestra vacía, con el botón para jugar.
     */
    public function lista(Request $request, Mesa $mesa): View
    {
        $jugador = $request->user();

        return view('paginas.historial', [
            'partidas' => $jugador === null ? null : $this->historial->pagina($jugador, SesionDeInvitado::desde($request)),
            'esInvitado' => $jugador?->esInvitado() ?? false,
            // Una partida sin terminar no está en la lista: se avisa aparte, con el link para seguirla.
            'enCurso' => $jugador === null ? null : $mesa->enCursoDe($jugador),
        ]);
    }

    /**
     * La repetición de una partida cerrada, como página. Solo la ve quien la jugó.
     */
    public function ver(Request $request, Mesa $mesa, int $partida): View|RedirectResponse
    {
        $cerrada = $this->cerrada($request, $partida);

        if ($cerrada === null) {
            // La partida propia que todavía se juega no tiene repetición: se sigue en la mesa.
            // Cualquier otra cosa (no existe, es de otros, quedó fuera de la sesión) se contesta igual: no está.
            abort_unless($mesa->enCursoDe($request->user())?->getKey() === $partida, 404);

            return redirect()->route('mesa');
        }

        return view('paginas.repeticion', ['datos' => $this->datos($request, $cerrada)]);
    }

    /**
     * Los mismos datos, para el cartel que se abre sobre la lista. Va en otra dirección que la página: si
     * compartieran una, el navegador podría mostrar estos datos sueltos al recargar o al volver atrás.
     */
    public function cuadros(Request $request, int $partida): JsonResponse
    {
        $cerrada = $this->cerrada($request, $partida);

        abort_if($cerrada === null, 404);

        return response()->json($this->datos($request, $cerrada));
    }

    /**
     * La partida cerrada con ese número, si es de quien pregunta y está entre las que se le muestran.
     */
    private function cerrada(Request $request, int $partida): ?Partida
    {
        return $this->historial->cerradaDe($request->user(), $partida, SesionDeInvitado::desde($request));
    }

    /**
     * Todo lo que necesita la repetición de una partida, vista desde el asiento de quien pregunta: el
     * resumen que también muestra la lista, cómo se nombra al rival en una frase y los cuadros.
     *
     * @return array<string, mixed>
     */
    private function datos(Request $request, Partida $partida): array
    {
        $asiento = (int) $partida->asientoDe($request->user());

        return [
            'resumen' => $this->historial->resumen($partida, $asiento),
            'rival' => $this->repeticion->nombreDelRival($partida, $asiento),
            ...$this->repeticion->de($partida, $asiento),
        ];
    }
}
