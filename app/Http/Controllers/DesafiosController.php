<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EntraComoInvitado;
use App\Juego\Desafios;
use App\Juego\Mesa;
use App\Juego\Progresos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Los desafíos sueltos: la lista y la puerta de entrada a cada uno. Están todos abiertos desde el
 * principio y se juegan en cualquier orden. Las manos de la escalera no se abren por acá.
 */
class DesafiosController extends Controller
{
    use EntraComoInvitado;

    public function lista(Request $request, Progresos $progresos, Mesa $mesa): View
    {
        $superados = $progresos->superados($request->user());
        $desafios = array_map(
            fn (array $desafio) => [...$desafio, 'superado' => in_array(Progresos::claveDeDesafio($desafio['clave']), $superados, true)],
            Desafios::sueltos(),
        );

        return view('paginas.desafios', [
            'desafios' => $desafios,
            'superados' => count(array_filter(array_column($desafios, 'superado'))),
            // Con una partida sin terminar no se abre otra: los botones llevan a esa.
            'enCurso' => $request->user() === null ? null : $mesa->abiertaDe($request->user()),
        ]);
    }

    /**
     * Abre el desafío y lleva a la mesa. Quien llega sin sesión entra como invitado, igual que al jugar
     * contra el bot. Con una partida sin terminar, lleva a esa.
     */
    public function jugar(Request $request, string $clave, Mesa $mesa): RedirectResponse
    {
        $desafio = Desafios::de($clave);

        // Una mano de la escalera no es un desafío suelto: se juega desde su escalón, cuando está abierto.
        if ($desafio === null || ! $desafio['suelto']) {
            abort(404);
        }

        $mesa->abrirDesafio($this->jugadorOInvitado($request), $desafio);

        return redirect()->route('mesa');
    }
}
