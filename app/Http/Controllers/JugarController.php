<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EntraComoInvitado;
use App\Juego\Experiencia;
use App\Juego\Mesa;
use App\Juego\Nivel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * La puerta de entrada a la mesa contra el bot. Quien llega sin sesión recibe un jugador
 * invitado en el momento, sin formulario. Es un POST porque crea datos: un
 * buscador o una precarga del navegador no deben fabricar jugadores ni partidas.
 */
class JugarController extends Controller
{
    use EntraComoInvitado;

    public function __invoke(Request $request, Mesa $mesa): RedirectResponse
    {
        // El nivel del bot llega desde la pantalla de modos. El botón de la portada no lo manda: va el de siempre,
        // o el que le toca a ese jugador por lo que declaró en su perfil.
        // Y si se juega una sola partida o una serie al mejor de tres, y de a cuántos: mano a mano o de a cuatro.
        $request->validate([
            'nivel' => ['nullable', Rule::enum(Nivel::class)],
            'serie' => ['nullable', 'boolean'],
            'asientos' => ['nullable', Rule::in([2, 4])],
        ]);
        $jugador = $this->jugadorOInvitado($request);
        $nivel = $request->enum('nivel', Nivel::class) ?? Experiencia::nivelPara($jugador);

        // Retoma la partida que tenía sin terminar, con su nivel, o empieza una nueva ya repartida.
        $mesa->abrir($jugador, $nivel, $request->boolean('serie'), $request->integer('asientos', 2));

        return redirect()->route('mesa');
    }
}
