<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EntraComoInvitado;
use App\Juego\Desafios;
use App\Juego\Escalera;
use App\Juego\Mesa;
use App\Juego\Progresos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * La escalera del club: sus ocho escalones y la puerta de entrada a cada uno. Un escalón se juega
 * solo si ya se pasaron los anteriores, y eso lo decide el servidor con lo que tiene anotado.
 */
class EscaleraController extends Controller
{
    use EntraComoInvitado;

    public function ver(Request $request, Progresos $progresos, Mesa $mesa): View
    {
        $actual = $progresos->escalonActual($request->user());

        return view('paginas.escalera', [
            'escalones' => Escalera::escalones(),
            // El escalón que toca: el primero sin pasar. Con la escalera completa, uno más que el último.
            'actual' => $actual,
            'pasados' => $actual - 1,
            'enCurso' => $request->user() === null ? null : $mesa->abiertaDe($request->user()),
        ]);
    }

    /**
     * Abre el escalón y lleva a la mesa. Uno cerrado no se abre, tampoco pidiéndolo a mano: se vuelve
     * a la escalera. Con una partida sin terminar, lleva a esa.
     */
    public function jugar(Request $request, int $escalon, Progresos $progresos, Mesa $mesa): RedirectResponse
    {
        $ficha = Escalera::escalon($escalon) ?? abort(404);

        if (! $progresos->escalonAbierto($request->user(), $escalon)) {
            return redirect()->route('escalera')->with('aviso', 'Ese escalón todavía está cerrado: primero pasá el anterior.');
        }

        $jugador = $this->jugadorOInvitado($request);

        if ($ficha['tipo'] === Escalera::MANO) {
            $mesa->abrirDesafio($jugador, Desafios::de($ficha['desafio']), $escalon);
        } else {
            $mesa->abrirEnEscalera($jugador, $ficha['nivel'], $escalon);
        }

        return redirect()->route('mesa');
    }
}
