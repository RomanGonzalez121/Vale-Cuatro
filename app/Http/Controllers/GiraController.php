<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EntraComoInvitado;
use App\Juego\Gira;
use App\Juego\Mesa;
use App\Juego\Premios;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * La gira de los clubes: el plano de cada barrio y la puerta de entrada a cada nivel. Un nivel se juega solo
 * si está abierto, y eso lo decide el servidor con lo que tiene anotado (Gira::abierto).
 */
class GiraController extends Controller
{
    use EntraComoInvitado;

    /**
     * Lleva al barrio donde va: el último abierto que todavía no se completó.
     */
    public function ver(Request $request): RedirectResponse
    {
        $barrios = Gira::de($request->user());
        $donde = collect($barrios)->last(fn (array $barrio) => $barrio['abierto']) ?? $barrios[0];

        return redirect()->route('gira.barrio', $donde['clave']);
    }

    public function barrio(Request $request, string $clave, Mesa $mesa): View
    {
        $barrios = Gira::de($request->user());
        $barrio = collect($barrios)->firstWhere('clave', $clave) ?? abort(404);
        $niveles = $barrio['niveles'];

        // Se ve la parada que sigue; con el barrio completo o cerrado, la primera.
        $sigue = collect($niveles)->first(fn (array $nivel) => $nivel['abierto'] && ! $nivel['pasado']);

        return view('paginas.gira', [
            'barrios' => $barrios,
            'barrio' => $barrio,
            'elegida' => $sigue['numero'] ?? 1,
            'fosforos' => array_sum(array_column($barrios, 'fosforos')),
            'deTodos' => array_sum(array_column($barrios, 'deTodos')),
            'carta' => (new Premios)->cartaDe($request->user()),
            'anterior' => $this->anterior($barrios, $clave),
            'enCurso' => $request->user() === null ? null : $mesa->abiertaDe($request->user()),
        ]);
    }

    /**
     * Abre el nivel y lleva a la mesa. Uno cerrado no se abre, tampoco pidiéndolo a mano. Con una partida sin
     * terminar, lleva a esa.
     */
    public function jugar(Request $request, string $clave, int $numero, Mesa $mesa): RedirectResponse
    {
        $nivel = Gira::nivel($clave, $numero) ?? abort(404);

        if (! Gira::abierto($request->user(), $clave, $numero)) {
            return redirect()->route('gira.barrio', $clave)->with('aviso', 'Esa parada todavía está cerrada: primero pasá la anterior.');
        }

        if (! in_array($nivel['tipo'], Gira::SE_JUEGAN, true)) {
            return redirect()->route('gira.barrio', $clave)->with('aviso', 'Ese nivel todavía no se juega: llega muy pronto.');
        }

        $mesa->abrirEnGira($this->jugadorOInvitado($request), $clave, $numero);

        return redirect()->route('mesa');
    }

    /**
     * El barrio anterior, para decir a quién hay que ganarle para abrir este.
     *
     * @param  list<array<string, mixed>>  $barrios
     * @return array<string, mixed>|null
     */
    private function anterior(array $barrios, string $clave): ?array
    {
        $previo = null;

        foreach ($barrios as $barrio) {
            if ($barrio['clave'] === $clave) {
                return $previo;
            }

            $previo = $barrio;
        }

        return null;
    }
}
