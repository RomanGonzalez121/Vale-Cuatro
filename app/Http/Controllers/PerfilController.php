<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApodoRequest;
use App\Juego\Categoria;
use App\Juego\Experiencia;
use App\Juego\Premios;
use App\Juego\Progresos;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * El perfil: quién sos en el club. Tu carta, tu categoría, el dorso de tus cartas y cuánto decís que sabés.
 *
 * Nada de lo que se elige acá da ventaja en la mesa. Y nada se elige sin haberlo ganado: cada pedido
 * vuelve a preguntarle a Premios si esa carta o ese dorso están destrabados, venga de la pantalla o no.
 */
class PerfilController extends Controller
{
    public function __construct(private readonly Premios $premios) {}

    public function ver(Request $request): View
    {
        $jugador = $request->user();
        $ganadas = $this->premios->ganadas($jugador);
        $categoria = Categoria::con($ganadas);

        return view('paginas.perfil', [
            'jugador' => $jugador,
            'ganadas' => $ganadas,
            'categoria' => $categoria,
            'carta' => $this->premios->cartaDe($jugador),
            'dorso' => $this->premios->dorsoDe($jugador),
            'camino' => $this->premios->camino($jugador),
            'proxima' => $this->premios->proxima($jugador),
            'dorsos' => $this->premios->dorsos($jugador),
            // La administración tiene todo abierto para probar: la pantalla lo dice en vez de contar lo que falta.
            'loTieneTodo' => Premios::loTieneTodo($jugador),
            // Por qué escalón va: lo que le falta para el próximo dorso está en la escalera.
            'escalon' => (new Progresos)->escalonActual($jugador),
        ]);
    }

    public function guardar(ApodoRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('perfil')->with('hecho', 'Apodo guardado.');
    }

    /**
     * Elige "tu carta" entre las del camino que ya ganó.
     */
    public function elegirCarta(Request $request): RedirectResponse
    {
        $carta = $request->validate(['carta' => ['required', 'string', Rule::in(array_keys(Premios::CAMINO))]])['carta'];
        $jugador = $request->user();

        if (! $this->premios->tieneCarta($jugador, $carta)) {
            throw ValidationException::withMessages(['carta' => 'Esa carta todavía no la ganaste.']);
        }

        $jugador->forceFill(['carta' => $carta])->save();

        return redirect()->route('perfil')->with('hecho', 'Carta elegida.');
    }

    /**
     * Elige el dorso de sus cartas entre los que ganó en la escalera.
     */
    public function elegirDorso(Request $request): RedirectResponse
    {
        $dorso = $request->validate(['dorso' => ['required', 'string', Rule::in(array_keys(Premios::DORSOS))]])['dorso'];
        $jugador = $request->user();

        if (! $this->premios->tieneDorso($jugador, $dorso)) {
            throw ValidationException::withMessages(['dorso' => 'Ese dorso todavía no lo ganaste.']);
        }

        $jugador->forceFill(['dorso' => $dorso])->save();

        // Al volver, la carta del perfil se muestra dada vuelta: es el dorso lo que se acaba de cambiar.
        return redirect()->route('perfil')->with('hecho', 'Dorso elegido.')->with('muestra', 'dorso');
    }

    /**
     * Declara cuánto sabe de truco. Cambia contra qué bot entra y qué ayudas ve; nada más.
     */
    public function declarar(Request $request): RedirectResponse
    {
        $request->validate(['experiencia' => ['required', Rule::enum(Experiencia::class)]]);

        $request->user()->forceFill(['experiencia' => $request->enum('experiencia', Experiencia::class)])->save();

        return redirect()->route('perfil')->with('hecho', 'Listo, guardado.');
    }
}
