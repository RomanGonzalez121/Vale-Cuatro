<?php

namespace App\Http\Controllers;

use App\Identidad\Paleta;
use App\Maqueta\DatosDeEjemplo;
use App\View\Components\Carta;
use Illuminate\Contracts\View\View;

/**
 * Las pantallas del sitio. En M0 muestran datos de ejemplo fijos;
 * cada una pasa a datos reales cuando llega su módulo.
 */
class PaginaController extends Controller
{
    public function portada(): View
    {
        return view('paginas.portada');
    }

    public function mesa(): View
    {
        return view('paginas.mesa', [
            'manos' => DatosDeEjemplo::manos(),
        ]);
    }

    public function ranking(): View
    {
        return view('paginas.ranking', [
            'jugadores' => DatosDeEjemplo::ranking(),
        ]);
    }

    public function historial(): View
    {
        return view('paginas.historial', [
            'partidas' => DatosDeEjemplo::partidas(),
            'manos' => DatosDeEjemplo::repeticion(),
        ]);
    }

    public function comoSeJuega(): View
    {
        return view('paginas.como-se-juega');
    }

    public function identidad(): View
    {
        return view('paginas.identidad', [
            'colores' => Paleta::COLORES,
            'combinaciones' => Paleta::combinaciones(),
            'mazo' => Carta::mazo(),
        ]);
    }
}
