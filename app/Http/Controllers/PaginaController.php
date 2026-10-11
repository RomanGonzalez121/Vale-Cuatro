<?php

namespace App\Http\Controllers;

use App\Identidad\Paleta;
use App\Juego\Mesa;
use App\Juego\Modos;
use App\Juego\Torneos;
use App\View\Components\Carta;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Las pantallas del sitio que todavía muestran datos de ejemplo fijos.
 * Cada una pasa a datos reales cuando llega su módulo.
 */
class PaginaController extends Controller
{
    public function portada(): View
    {
        return view('paginas.portada');
    }

    public function modos(Request $request, Mesa $mesa, Torneos $torneos): View
    {
        $jugador = $request->user();

        return view('paginas.modos', [
            'juegos' => Modos::juegos(),
            // Con una partida sin terminar (contra el bot, con otra persona o una sala esperando rival),
            // la pantalla lo avisa y ofrece seguirla en vez de empezar otra.
            'enCurso' => $jugador === null ? null : $mesa->abiertaDe($jugador),
            // Y con un torneo sin terminar, ofrece volver a sus llaves en vez de armar otro.
            'torneo' => $jugador === null ? null : $torneos->enCursoDe($jugador),
        ]);
    }

    public function comoSeJuega(): View
    {
        return view('paginas.como-se-juega');
    }

    /**
     * La página que el sitio instalado muestra cuando no hay conexión. No depende de quién la pide:
     * el service worker la guarda una vez y es la misma para todos.
     */
    public function sinConexion(): View
    {
        return view('paginas.sin-conexion');
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
