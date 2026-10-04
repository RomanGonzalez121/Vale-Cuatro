<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistroRequest;
use App\Models\Jugador;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RegistroController extends Controller
{
    public function formulario(): View
    {
        return view('paginas.registro');
    }

    public function registrar(RegistroRequest $request): RedirectResponse
    {
        // Quien venía jugando como invitado no estrena fila: la suya pasa a
        // ser la cuenta, y así conserva lo que ya jugó.
        $invitado = $request->user();
        $jugador = $invitado ?? new Jugador;
        $jugador->fill($request->validated())->save();

        // El invitado entró con "recordarme" puesto. Salir borra esa cookie,
        // para que la cuenta nueva no quede recordada sin que nadie lo pida.
        if ($invitado !== null) {
            Auth::logout();
        }

        Auth::login($jugador);
        $request->session()->regenerate();

        return redirect()->route('perfil')->with('hecho', 'Cuenta creada. Ya podés jugar con tu apodo.');
    }
}
