<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EntraComoInvitado;
use App\Juego\Mesa;
use App\Juego\SalaNoDisponible;
use App\Models\Partida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * El link que se le manda a alguien para jugar. Se puede abrir sin cuenta: quien se sienta
 * recibe un jugador invitado en el momento. De la sala solo muestra quién invita.
 */
class InvitacionController extends Controller
{
    use EntraComoInvitado;

    public function __construct(private readonly Mesa $mesa) {}

    public function ver(Request $request, string $codigo): View|RedirectResponse
    {
        $partida = $this->partida($codigo);
        $jugador = $request->user();

        // Quien ya es de esta partida (la abrió, o ya se sentó) no tiene que volver a sentarse.
        if ($jugador !== null && $partida->asientoDe($jugador) !== null) {
            return $partida->esperando() ? redirect()->route('sala', $codigo) : redirect()->route('mesa');
        }

        return view('paginas.invitacion', [
            'codigo' => $codigo,
            'quienInvita' => $partida->jugador->apodo,
            'disponible' => $partida->esperando(),
            'motivo' => $partida->enCurso() ? 'Esa partida ya tiene sus dos jugadores.' : 'Esa partida ya terminó.',
        ]);
    }

    public function entrar(Request $request, string $codigo): RedirectResponse
    {
        $this->partida($codigo);

        try {
            $this->mesa->sentarse($codigo, $this->jugadorOInvitado($request));
        } catch (SalaNoDisponible $motivo) {
            return redirect()->route('invitacion', $codigo)->with('aviso', $motivo->getMessage());
        }

        return redirect()->route('mesa');
    }

    private function partida(string $codigo): Partida
    {
        return Partida::query()->where('codigo', $codigo)->where('entre_personas', true)->with('jugador')->firstOrFail();
    }
}
