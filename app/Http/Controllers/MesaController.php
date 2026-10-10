<?php

namespace App\Http\Controllers;

use App\Juego\Mesa;
use App\Juego\Torneos;
use App\Models\EventoDePartida;
use App\Models\Partida;
use App\Motor\Accion;
use App\Motor\AccionInvalida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Lo que el navegador le pide a la mesa mientras se juega. Siempre trabaja
 * sobre una partida en curso de quien hace el pedido: la busca solo entre las
 * suyas, así que nadie puede tocar la de otro.
 *
 * La mesa manda el número de la partida que está mostrando. Hace falta desde
 * que existen las series: cuando termina una partida la siguiente ya está
 * repartida, y un pedido de la pantalla vieja no puede caer en la nueva.
 *
 * Cada respuesta trae el paso de la jugada propia, y cada paso es la vista del
 * asiento del jugador. El bot juega aparte, desde la cola: sus pasos se piden
 * con la consulta corta. Las cartas del bot no viajan nunca.
 */
class MesaController extends Controller
{
    public function __construct(private readonly Mesa $mesa, private readonly Torneos $torneos) {}

    /**
     * La mesa con la partida en curso. Recargar la página vuelve a la misma partida, en el mismo punto.
     * No crea nada: sin partida en curso, manda a elegir el modo.
     */
    public function ver(Request $request): View|RedirectResponse
    {
        $partida = $this->mesa->enCursoDe($request->user());

        if ($partida === null) {
            // Quien abrió una sala y todavía espera rival vuelve a su sala.
            $sala = $this->mesa->abiertaDe($request->user());

            if ($sala?->esperando()) {
                return redirect()->route('sala', $sala->codigo);
            }

            // Si lo último que jugó fue una partida de torneo, lo que sigue está en sus llaves.
            $ultima = $this->mesa->ultimaDe($request->user());

            if ($ultima?->torneo_id !== null) {
                return redirect()->route('torneo', $ultima->torneo_id);
            }

            return redirect()->route('modos')->with('aviso', $this->comoTermino($request));
        }

        $asiento = $this->asientoDe($partida, $request);

        return view('paginas.mesa', [
            'vista' => $this->mesa->vista($partida, $asiento),
            // Contra el bot, su nivel. Con otra persona no hay nivel: se muestra su apodo.
            'nivel' => $partida->nivel_bot,
            'rival' => $this->apodoDelRival($partida, $asiento),
            // Quien abrió la sala todavía no avisó que llegó: la mesa lo hace apenas está a la vista.
            'faltaLlegar' => $this->mesa->faltaLlegar($partida, $asiento),
            // Si es parte de una serie al mejor de tres, cómo va antes de esta partida.
            'serie' => $this->serieDe($partida, $asiento),
            // Si es de un torneo: qué partido es, contra qué bot y adónde se vuelve al terminar.
            'torneo' => $this->torneoDe($partida),
        ]);
    }

    /**
     * Lo que la mesa necesita saber cuando la partida es de un torneo: el bot que tocó, con su apodo, qué
     * partido es y qué viene si se gana. Null si la partida no es de un torneo.
     *
     * @return array{llaves: string, rival: string, partido: string, corto: string, esFinal: bool, sigue: string|null}|null
     */
    private function torneoDe(Partida $partida): ?array
    {
        $cruce = $this->torneos->cruceDe($partida);

        if ($cruce === null) {
            return null;
        }

        $torneo = $cruce->torneo;
        $esFinal = $cruce->ronda === $torneo->rondas();

        return [
            'llaves' => route('torneo', $torneo),
            'rival' => $torneo->apodoDe($cruce->rivalDe($torneo->lugarDeLaPersona())),
            'partido' => $partido = $torneo->nombreDePartido($cruce->ronda),
            // En la barra de la mesa va en una palabra: "Cuartos", que es lo que entra en un celular.
            'corto' => explode(' ', $partido)[0],
            'esFinal' => $esFinal,
            // Cómo se dice adónde pasa quien gana: "a la final", "a las semifinales".
            'sigue' => $esFinal ? null : ($cruce->ronda + 1 === $torneo->rondas() ? 'a la final' : 'a las '.mb_strtolower($torneo->nombreDeRonda($cruce->ronda + 1))),
        ];
    }

    /**
     * El marcador de la serie visto desde un asiento, o null si la partida es suelta: las que ganó cada
     * uno hasta ahora y cuántas hacen falta para llevársela.
     *
     * @return array{vos: int, rival: int, necesarias: int}|null
     */
    private function serieDe(Partida $partida, int $asiento): ?array
    {
        if ($partida->serie === null) {
            return null;
        }

        [$propias, $ajenas] = $partida->serie->marcadorDesde($asiento);

        return ['vos' => $propias, 'rival' => $ajenas, 'necesarias' => $partida->serie->necesarias()];
    }

    /**
     * La mesa de quien abrió la sala avisa que ya está a la vista. Mientras no lo hace, su turno corre con un
     * plazo de espera más largo: el rival pudo sentarse cuando esa persona estaba mandando el link desde otra
     * aplicación. Devuelve lo que pasó después de lo último que la mesa mostró, que incluye su propia llegada.
     */
    public function presente(Request $request): JsonResponse
    {
        $datos = $request->validate(['desde' => ['required', 'integer', 'min:0']]);
        $partida = $this->enJuego($request);

        if ($partida === null) {
            return response()->json(['motivo' => 'No tenés una partida en curso.'], 409);
        }

        $asiento = $this->asientoDe($partida, $request);
        $this->mesa->llegar($partida, $asiento);

        return response()->json(['pasos' => $this->mesa->pasosDesde($partida, (int) $datos['desde'], $asiento)]);
    }

    /**
     * La consulta corta de la mesa. Con "desde" (el número del último evento que ya mostró) devuelve
     * los pasos posteriores: así se entera de lo que jugó el bot, también si esa jugada cerró la partida.
     * Sin "desde" devuelve cómo está la partida ahora, para ponerse al día cuando una jugada no entró.
     */
    public function estado(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'desde' => ['sometimes', 'integer', 'min:0'],
            'partida' => ['required_with:desde', 'integer'],
        ]);
        $sinPartida = response()->json(['motivo' => 'No tenés una partida en curso.'], 409);

        if (! isset($datos['desde'])) {
            $partida = $this->enJuego($request);

            return $partida === null ? $sinPartida : response()->json(['vista' => $this->mesa->vista($partida, $this->asientoDe($partida, $request))]);
        }

        // Se busca la partida que la mesa dice estar mostrando, y solo entre las del jugador. No alcanza con
        // "su última partida": quien se sienta en una sala puede tener otra más nueva, ya cerrada.
        $partida = $this->mesa->deJugador($request->user(), (int) $datos['partida']);

        if ($partida === null) {
            return $sinPartida;
        }

        $pasos = $this->mesa->pasosDesde($partida, (int) $datos['desde'], $this->asientoDe($partida, $request));

        // Cerrada y sin nada nuevo que contar: no hay a quién esperar. Pasa también cuando la pestaña quedó con una
        // partida vieja (se abandonó desde otra y se empezó una nueva). La mesa se recarga y el servidor decide.
        return $pasos === [] && ! $partida->enCurso() ? $sinPartida : response()->json(['pasos' => $pasos]);
    }

    /**
     * La red de seguridad: la mesa lo pide si pasaron unos segundos y el bot no jugó.
     * El bot juega ahí mismo lo que le toque; lo que jugó llega por la consulta corta.
     */
    public function despertarAlBot(Request $request): JsonResponse
    {
        $partida = $this->enJuego($request);

        if ($partida === null) {
            return response()->json(['motivo' => 'No tenés una partida en curso.'], 409);
        }

        $this->mesa->despertarAlBot($partida);

        return response()->json(['listo' => true]);
    }

    /**
     * La red de seguridad del plazo entre personas: la mesa lo pide cuando su cuenta regresiva llegó a cero y el
     * servidor no resolvió la espera (el proceso de la cola está caído o atrasado). El servidor decide: si el plazo
     * todavía no se cumplió, o ya lo resolvió otro pedido, no hace nada.
     */
    public function resolverPlazo(Request $request): JsonResponse
    {
        $partida = $this->enJuego($request);

        if ($partida === null) {
            return response()->json(['motivo' => 'No tenés una partida en curso.'], 409);
        }

        return response()->json(['resolvio' => $this->mesa->resolverPlazo($partida->getKey())]);
    }

    public function accion(Request $request): JsonResponse
    {
        try {
            $accion = Accion::desdeArray($request->only(['tipo', 'carta']));
        } catch (InvalidArgumentException) {
            return $this->rechazo('Esa acción no existe.');
        }

        $desde = $this->desde($request);

        return $this->conLaPartida($request, fn (Partida $partida, int $asiento) => $this->mesa->actuar($partida, $accion, $asiento, $desde));
    }

    public function repartir(Request $request): JsonResponse
    {
        $desde = $this->desde($request);

        return $this->conLaPartida($request, fn (Partida $partida, int $asiento) => $this->mesa->repartir($partida, $asiento, $desde));
    }

    /**
     * El último evento que la mesa dice haber mostrado al mandar la jugada, si lo mandó. Con eso el servidor
     * rechaza una jugada decidida sobre una pantalla atrasada.
     */
    private function desde(Request $request): ?int
    {
        $desde = $request->input('desde');

        return is_int($desde) || (is_string($desde) && ctype_digit($desde)) ? (int) $desde : null;
    }

    public function abandonar(Request $request): RedirectResponse
    {
        $partida = $this->enJuego($request);

        // La mesa que lo pide mostraba una partida que ya no se juega: no se abandona otra en su lugar.
        // Se vuelve a la mesa, y ahí el servidor muestra la que sigue o cuenta cómo terminó.
        if ($partida === null && $request->filled('partida')) {
            return redirect()->route('mesa');
        }

        if ($partida !== null) {
            try {
                $this->mesa->abandonar($partida, $this->asientoDe($partida, $request));
            } catch (AccionInvalida) {
                // Otro pedido la cerró justo antes (dos toques seguidos): ya está abandonada.
            }
        }

        // En un torneo, dejar la partida es quedar afuera: se vuelve a las llaves, que muestran cómo siguió.
        if ($partida?->torneo_id !== null) {
            return redirect()->route('torneo', $partida->torneo_id)->with('aviso', 'Abandonaste la partida y quedaste afuera del torneo.');
        }

        return redirect()->route('modos')->with('aviso', $partida?->serie_id === null ? 'Abandonaste la partida.' : 'Abandonaste la partida y, con ella, la serie.');
    }

    /**
     * La partida en curso de la que habla el pedido. Con el número de partida que manda la mesa, es esa y
     * ninguna otra: si ya no se juega, no hay partida (y la mesa se recarga). Sin número, vale la que el
     * jugador tenga en curso.
     */
    private function enJuego(Request $request): ?Partida
    {
        $numero = $request->input('partida');

        if ($numero === null || $numero === '') {
            return $this->mesa->enCursoDe($request->user());
        }

        if (! is_int($numero) && ! (is_string($numero) && ctype_digit($numero))) {
            return null;
        }

        $partida = $this->mesa->deJugador($request->user(), (int) $numero);

        return $partida?->enCurso() ? $partida : null;
    }

    /**
     * El bot o el otro jugador pueden cerrar la partida mientras uno no está mirando (salió para seguir después,
     * recargó justo, o se fue el rival). Al volver a la mesa se le cuenta cómo terminó.
     */
    private function comoTermino(Request $request): ?string
    {
        $ultima = $this->mesa->ultimaDe($request->user());

        return match ($ultima?->estado) {
            Partida::TERMINADA => $this->resultado($ultima, $request),
            Partida::ABANDONADA => $this->comoSeFueAlguien($ultima, $request),
            // La cerró la administración porque había quedado sin movimiento.
            Partida::CERRADA => 'Tu última partida se cerró porque había quedado sin movimiento. No la ganó nadie.',
            default => null,
        };
    }

    private function resultado(Partida $partida, Request $request): string
    {
        $asiento = $this->asientoDe($partida, $request);
        $tanteo = $this->mesa->reconstruir($partida)->tanteo();
        $rival = $partida->entre_personas ? 'tu rival' : 'el bot';

        return "Tu última partida terminó {$tanteo[$asiento]} a {$tanteo[1 - $asiento]}: ".($partida->ganador === $asiento ? 'ganaste.' : "ganó {$rival}.").$this->comoQuedoLaSerie($partida, $asiento);
    }

    /**
     * Si con esa partida se cerró una serie, cómo quedó: va al final del aviso, como una frase más.
     */
    private function comoQuedoLaSerie(Partida $partida, int $asiento): string
    {
        $serie = $partida->serie;

        if ($serie === null || ! $serie->cerrada()) {
            return '';
        }

        [$propias, $ajenas] = $serie->marcadorDesde($asiento);
        $rival = $partida->entre_personas ? 'tu rival' : 'el bot';

        return " La serie quedó {$propias} a {$ajenas}: ".($serie->ganador === $asiento ? 'la ganaste.' : "la ganó {$rival}.");
    }

    /**
     * Entre personas, quien se quedó se entera de que el otro se fue, y quien perdió por dejar vencer su turno se entera
     * de por qué. Quien abandonó a propósito ya lo sabe, y una sala cancelada o una partida contra el bot no necesitan aviso.
     */
    private function comoSeFueAlguien(Partida $partida, Request $request): ?string
    {
        if (! $partida->entre_personas || $partida->ganador === null) {
            return null;
        }

        $asiento = $this->asientoDe($partida, $request);
        $abandono = $partida->eventos()->getQuery()->where('tipo', EventoDePartida::ABANDONO)->reorder('numero', 'desc')->first();
        $porVencimientos = ($abandono?->datos['motivo'] ?? null) === 'vencimientos';

        // En una serie, irse de una partida es perder la serie entera.
        $enSerie = $partida->serie_id !== null;

        if ($abandono?->asiento === $asiento) {
            return $porVencimientos ? 'Perdiste la partida'.($enSerie ? ' y la serie' : '').': se te venció el turno '.Mesa::VENCIMIENTOS_PARA_PERDER.' veces seguidas.' : null;
        }

        if ($porVencimientos) {
            return $enSerie ? 'Tu rival dejó de jugar: perdió la partida y la serie.' : 'Tu rival dejó de jugar y perdió la partida.';
        }

        return $enSerie ? 'Tu rival abandonó la partida: ganaste la serie.' : 'Tu rival abandonó la partida: ganaste.';
    }

    /**
     * El apodo de la otra persona, o null si se juega contra el bot. Si su cuenta ya no existe, "Tu rival".
     */
    private function apodoDelRival(Partida $partida, int $asiento): ?string
    {
        if (! $partida->entre_personas) {
            return null;
        }

        return ($asiento === Mesa::JUGADOR ? $partida->invitado : $partida->jugador)?->apodo ?? 'Tu rival';
    }

    /**
     * El asiento de quien hace el pedido, sacado de la partida y no de lo que mande el navegador:
     * nadie puede mirar ni mover las cartas del otro eligiendo un asiento. Si no es de la partida, no pasa.
     */
    private function asientoDe(Partida $partida, Request $request): int
    {
        return $partida->asientoDe($request->user()) ?? abort(403, 'Esa partida no es tuya.');
    }

    /**
     * @param  callable(Partida, int): list<array<string, mixed>>  $hacer
     */
    private function conLaPartida(Request $request, callable $hacer): JsonResponse
    {
        $partida = $this->enJuego($request);

        if ($partida === null) {
            return response()->json(['motivo' => 'No tenés una partida en curso.'], 409);
        }

        try {
            return response()->json(['pasos' => $hacer($partida, $this->asientoDe($partida, $request))]);
        } catch (AccionInvalida $rechazo) {
            return $this->rechazo($rechazo->getMessage());
        }
    }

    private function rechazo(string $motivo): JsonResponse
    {
        return response()->json(['motivo' => $motivo], 422);
    }
}
