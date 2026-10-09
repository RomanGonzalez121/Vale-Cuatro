<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EntraComoInvitado;
use App\Juego\Mesa;
use App\Models\Partida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * La sala de espera de quien abrió una partida entre personas: ahí está el link para mandar y se
 * espera a que alguien se siente. Solo quien la abrió la ve, la consulta y la cancela.
 */
class SalaController extends Controller
{
    use EntraComoInvitado;

    public function __construct(private readonly Mesa $mesa) {}

    /**
     * Abre una sala. Quien llega sin sesión recibe un jugador invitado en el momento, igual que al
     * jugar contra el bot. Si ya tenía una partida sin terminar, vuelve a esa. Con "serie", lo que
     * se abre es una serie al mejor de tres.
     */
    public function crear(Request $request): RedirectResponse
    {
        $request->validate(['serie' => ['nullable', 'boolean']]);

        $partida = $this->mesa->crearSala($this->jugadorOInvitado($request), $request->boolean('serie'));

        return $partida->esperando() ? redirect()->route('sala', $partida->codigo) : redirect()->route('mesa');
    }

    public function ver(Request $request, string $codigo): View|RedirectResponse
    {
        $partida = $this->partida($codigo);

        // Quien no abrió esta sala no tiene nada que hacer acá: va a la invitación.
        if (! $this->esDeQuienPide($partida, $request)) {
            return redirect()->route('invitacion', $codigo);
        }

        if ($partida->enCurso()) {
            return redirect()->route('mesa');
        }

        if (! $partida->esperando()) {
            return redirect()->route('modos')->with('aviso', 'Esa sala ya se cerró.');
        }

        return view('paginas.sala', [
            // El id va en la página para suscribirse al canal privado: no sirve de nada sin ser de la partida.
            'partida' => $partida->id,
            'codigo' => $codigo,
            'enlace' => route('invitacion', $codigo),
            'minutos' => Mesa::MINUTOS_DE_SALA,
            'enSerie' => $partida->serie_id !== null,
        ]);
    }

    /**
     * Si ya se sentó alguien. La sala lo pide cuando se reconecta y como respaldo si el aviso en vivo no llega.
     */
    public function estado(Request $request, string $codigo): JsonResponse
    {
        $partida = $this->partida($codigo);

        abort_unless($this->esDeQuienPide($partida, $request), 403, 'Esa sala no es tuya.');

        return response()->json([
            'empezo' => $partida->enCurso(),
            'cerrada' => ! $partida->estaAbierta(),
            // Quién se sentó, para decírselo a quien espera. Es el apodo, que en este sitio es público.
            'rival' => $partida->invitado?->apodo,
            // Si quien abrió la sala (el asiento 0) es mano: el reparto de la sala empieza por el mano, igual que en
            // la mesa. Se sortea al sentarse el rival, así que antes no hay mano. No es un secreto: la mesa lo dice.
            'mano' => $partida->primer_mano === Mesa::JUGADOR,
        ]);
    }

    public function cancelar(Request $request, string $codigo): RedirectResponse
    {
        $partida = $this->partida($codigo);

        abort_unless($this->esDeQuienPide($partida, $request), 403, 'Esa sala no es tuya.');

        // Justo se sentó alguien: la partida ya empezó y se deja desde la mesa.
        if (! $this->mesa->cancelarSala($partida)) {
            return redirect()->route('mesa');
        }

        return redirect()->route('modos')->with('aviso', 'Cerraste la sala.');
    }

    private function partida(string $codigo): Partida
    {
        return Partida::query()->where('codigo', $codigo)->where('entre_personas', true)->firstOrFail();
    }

    private function esDeQuienPide(Partida $partida, Request $request): bool
    {
        return $partida->jugador_id === $request->user()->getKey();
    }
}
