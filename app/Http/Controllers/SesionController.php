<?php

namespace App\Http\Controllers;

use App\Http\Requests\IngresoRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SesionController extends Controller
{
    public function formulario(): View
    {
        return view('paginas.ingresar');
    }

    public function ingresar(IngresoRequest $request): RedirectResponse
    {
        $request->autenticar();

        // Sesión nueva al entrar: un identificador robado antes del ingreso deja de servir.
        $request->session()->regenerate();

        return redirect()->intended(route('portada'));
    }

    public function salir(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portada');
    }
}
