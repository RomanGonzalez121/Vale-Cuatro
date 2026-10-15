<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApodoRequest;
use App\Juego\Camino;
use App\Juego\Categoria;
use App\Juego\Experiencia;
use App\Juego\Historial;
use App\Juego\Numeros;
use App\Juego\Premios;
use App\Juego\Progresos;
use App\Models\Jugador;
use App\Models\Partida;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
    public function __construct(private readonly Premios $premios, private readonly Historial $historial) {}

    public function ver(Request $request): View
    {
        $jugador = $request->user();
        $nivel = $this->premios->nivel($jugador);

        // Mirar el perfil es ver lo destrabado: el puntito del apodo en el menú se apaga desde acá.
        $this->premios->marcarVisto($jugador);

        return view('paginas.perfil', [
            ...$this->comun($jugador),
            'propio' => true,
            'dorso' => $this->premios->dorsoDe($jugador),
            'camino' => $this->premios->camino($jugador),
            'dorsos' => $this->premios->dorsos($jugador),
            // Lo que todavía no se dibujó (mesas, mazos) también está en la Colección, con su nivel.
            'proximamente' => array_values(array_filter(Camino::fichas(), fn (array $ficha) => ! $ficha['listo'])),
            // El camino entero, nivel por nivel, para "Ver el camino entero".
            'niveles' => $this->niveles(),
            'cerca' => $this->cerca($nivel),
            // La administración tiene todo abierto para probar: la pantalla lo dice en vez de contar lo que falta.
            'loTieneTodo' => Premios::loTieneTodo($jugador),
            // Por qué escalón va: los dorsos de la escalera se ganan ahí.
            'escalon' => (new Progresos)->escalonActual($jugador),
        ]);
    }

    /**
     * El perfil de otro, como se ve desde afuera (tocando su apodo en el ranking): su mesa, su carta, su nivel,
     * sus números y sus últimas partidas. Nada de su cuenta ni de lo que eligió para ver él (el dorso es suyo).
     * Solo de quien tiene cuenta y de los jugadores de ejemplo: quien juega sin cuenta no tiene perfil.
     */
    public function publico(Request $request, Jugador $jugador): View|RedirectResponse
    {
        abort_if($jugador->esInvitado() || $jugador->esAdministrador(), 404);

        if ($request->user()?->is($jugador)) {
            return redirect()->route('perfil');
        }

        return view('paginas.perfil', [
            ...$this->comun($jugador),
            'propio' => false,
        ]);
    }

    /**
     * Lo que muestran los dos perfiles, el propio y el de otro.
     *
     * @return array<string, mixed>
     */
    private function comun(Jugador $jugador): array
    {
        $nivel = $this->premios->nivel($jugador);

        return [
            'jugador' => $jugador,
            'nivel' => $nivel,
            'enLaTira' => Camino::enLaTira((int) $jugador->fosforos),
            'categoria' => Categoria::delNivel($nivel),
            'carta' => $this->premios->cartaDe($jugador),
            'proximo' => $nivel < Camino::NIVELES ? Camino::entre($nivel, $nivel + 1) : [],
            'numeros' => (new Numeros)->de($jugador),
            'ultimas' => $this->historial->cerradasDe($jugador)->limit(5)->get()
                ->map(fn (Partida $partida) => $this->historial->resumen($partida, (int) $partida->asientoDe($jugador)))
                ->all(),
        ];
    }

    /**
     * Unos niveles antes y después del actual: los que se ven de entrada en el camino.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private function cerca(int $nivel): array
    {
        $desde = max(2, min($nivel - 2, Camino::NIVELES - 7));

        return array_slice($this->niveles(), $desde - 1, 8, true);
    }

    /**
     * Los cuarenta niveles con lo que destraba cada uno.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private function niveles(): array
    {
        $niveles = array_fill_keys(range(1, Camino::NIVELES), []);

        foreach (Camino::fichas() as $ficha) {
            if (isset($ficha['nivel'])) {
                $niveles[$ficha['nivel']][] = $ficha;
            }
        }

        return $niveles;
    }

    public function guardar(ApodoRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('perfil')->with('hecho', 'Apodo guardado.');
    }

    /**
     * Elige "tu carta" entre las del camino que ya ganó.
     */
    public function elegirCarta(Request $request): RedirectResponse|JsonResponse
    {
        $carta = $request->validate(['carta' => ['required', 'string', Rule::in(array_keys(Premios::CAMINO))]])['carta'];
        $jugador = $request->user();

        if (! $this->premios->tieneCarta($jugador, $carta)) {
            throw ValidationException::withMessages(['carta' => 'Esa carta todavía no la ganaste.']);
        }

        $jugador->forceFill(['carta' => $carta])->save();

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => 'Carta elegida.']);
        }

        return redirect()->to(route('perfil').'#coleccion')->with('hecho', 'Carta elegida.');
    }

    /**
     * Elige el dorso de sus cartas entre los que ganó en la escalera.
     */
    public function elegirDorso(Request $request): RedirectResponse|JsonResponse
    {
        $dorso = $request->validate(['dorso' => ['required', 'string', Rule::in(array_keys(Premios::DORSOS))]])['dorso'];
        $jugador = $request->user();

        if (! $this->premios->tieneDorso($jugador, $dorso)) {
            throw ValidationException::withMessages(['dorso' => 'Ese dorso todavía no lo ganaste.']);
        }

        $jugador->forceFill(['dorso' => $dorso])->save();

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => 'Dorso elegido.']);
        }

        // Al volver, la carta del perfil se muestra dada vuelta: es el dorso lo que se acaba de cambiar.
        return redirect()->to(route('perfil').'#coleccion')->with('hecho', 'Dorso elegido.')->with('muestra', 'dorso');
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
