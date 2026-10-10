<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EntraComoInvitado;
use App\Juego\Llaves;
use App\Juego\Mesa;
use App\Juego\TorneoNoDisponible;
use App\Juego\Torneos;
use App\Models\Torneo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * El torneo relámpago contra bots: armarlo, mirar las llaves, sentarse a jugar la partida de la ronda
 * y dejarlo. Un torneo es de quien lo armó: nadie más lo ve ni lo toca.
 */
class TorneoController extends Controller
{
    use EntraComoInvitado;

    public function __construct(private readonly Torneos $torneos) {}

    /**
     * Arma el torneo y lleva a sus llaves. Quien llega sin sesión entra como invitado, igual que al
     * jugar contra el bot. Con un torneo sin terminar, lleva a ese.
     */
    public function crear(Request $request, Mesa $mesa): RedirectResponse
    {
        $datos = $request->validate(['lugares' => ['required', Rule::in(Torneos::LUGARES)]]);
        $jugador = $this->jugadorOInvitado($request);

        // Con una partida sin terminar el botón dice "Seguir la partida", acá como en cualquier otro modo.
        if ($mesa->abiertaDe($jugador) !== null) {
            return redirect()->route('mesa');
        }

        return redirect()->route('torneo', $this->torneos->crear($jugador, (int) $datos['lugares']));
    }

    public function ver(Request $request, int $torneo, Llaves $llaves): View
    {
        $torneo = $this->delJugador($request, $torneo);

        // Por si el trabajo que pone las llaves al día todavía no corrió: se hace acá, y él no encuentra nada.
        $this->torneos->avanzar($torneo->getKey());
        $torneo->refresh();

        return view('paginas.torneo', ['torneo' => $torneo, 'llaves' => $llaves->de($torneo)]);
    }

    /**
     * Sentarse a jugar la partida de la ronda: la abre (o retoma la que ya estaba) y lleva a la mesa.
     */
    public function jugar(Request $request, int $torneo): RedirectResponse
    {
        $torneo = $this->delJugador($request, $torneo);

        try {
            $this->torneos->jugar($torneo);
        } catch (TorneoNoDisponible $motivo) {
            return redirect()->route('torneo', $torneo)->with('aviso', $motivo->getMessage());
        }

        return redirect()->route('mesa');
    }

    public function abandonar(Request $request, int $torneo): RedirectResponse
    {
        $torneo = $this->delJugador($request, $torneo);
        $seJugaba = $torneo->enCurso();

        $this->torneos->abandonar($torneo);

        return redirect()->route('torneo', $torneo)->with('aviso', $seJugaba ? 'Dejaste el torneo.' : null);
    }

    /**
     * El torneo de quien hace el pedido. Uno ajeno contesta lo mismo que uno que no existe.
     */
    private function delJugador(Request $request, int $torneo): Torneo
    {
        return $this->torneos->deJugador($request->user(), $torneo) ?? abort(404);
    }
}
