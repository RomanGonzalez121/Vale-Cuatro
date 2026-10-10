<?php

namespace App\Http\Controllers;

use App\Administracion\Acciones;
use App\Administracion\Panel;
use App\Models\Jugador;
use App\Models\Partida;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * El panel de administración: una pantalla que muestra cómo está el sitio y cinco acciones.
 *
 * A estas rutas llega solo la cuenta de administración (middleware SoloAdministracion); para cualquier
 * otro no existen. Ninguna otorga ni quita el rol: eso lo hace un comando de consola.
 * Cada acción vuelve al panel y cuenta qué pasó.
 */
class AdministracionController extends Controller
{
    public function __construct(private readonly Panel $panel, private readonly Acciones $acciones) {}

    public function ver(Request $request): View
    {
        $datos = $request->validate(['apodo' => ['nullable', 'string', 'max:20']]);
        $buscado = trim((string) ($datos['apodo'] ?? ''));

        return view('paginas.administracion', [
            'partidas' => $this->panel->partidas(),
            'cola' => $this->panel->cola(),
            'porLimpiar' => $this->panel->porLimpiar(),
            'buscado' => $buscado,
            'jugadores' => $this->panel->jugadores($buscado),
            'cuaderno' => $this->panel->cuaderno(),
        ]);
    }

    public function cerrarPartida(Request $request, Partida $partida): RedirectResponse
    {
        return $this->acciones->cerrarPartida($request->user(), $partida)
            ? $this->hecho("Partida {$partida->id} cerrada.")
            : $this->aviso('Esa partida ya terminó o volvió a moverse: no se cerró.');
    }

    public function reintentarTrabajo(Request $request, string $trabajo): RedirectResponse
    {
        return $this->acciones->reintentarTrabajo($request->user(), $trabajo)
            ? $this->hecho('El trabajo volvió a la cola.')
            : $this->aviso('Ese trabajo ya no está entre los fallidos.');
    }

    public function descartarTrabajo(Request $request, string $trabajo): RedirectResponse
    {
        return $this->acciones->descartarTrabajo($request->user(), $trabajo)
            ? $this->hecho('Trabajo descartado.')
            : $this->aviso('Ese trabajo ya no está entre los fallidos.');
    }

    public function limpiar(Request $request): RedirectResponse
    {
        return $this->hecho('Limpieza hecha: '.$this->acciones->limpiar($request->user()).'.');
    }

    public function ocultarApodo(Request $request, Jugador $jugador): RedirectResponse
    {
        $nuevo = $this->acciones->ocultarApodo($request->user(), $jugador);

        return $nuevo === null
            ? $this->aviso('Ese apodo no se puede ocultar.')
            : $this->hecho("Apodo ocultado: ahora se llama {$nuevo}.");
    }

    private function hecho(string $texto): RedirectResponse
    {
        return redirect()->route('administracion')->with('hecho', $texto);
    }

    private function aviso(string $texto): RedirectResponse
    {
        return redirect()->route('administracion')->with('aviso', $texto);
    }
}
