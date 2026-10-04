<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApodoRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PerfilController extends Controller
{
    public function ver(Request $request): View
    {
        return view('paginas.perfil', [
            'jugador' => $request->user(),
        ]);
    }

    public function guardar(ApodoRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('perfil')->with('hecho', 'Apodo guardado.');
    }
}
