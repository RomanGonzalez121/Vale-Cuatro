<?php

namespace App\Http\Controllers;

use App\Identidad\Paleta;
use App\Juego\Desafios;
use App\Juego\Gira;
use App\Juego\Mesa;
use App\Juego\Modos;
use App\Juego\Progresos;
use App\Juego\Torneos;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Torneo;
use App\View\Components\Carta;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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
            'torneo' => $torneo = $jugador === null ? null : $torneos->enCursoDe($jugador),
            'escenas' => $this->escenas($jugador, $torneo, $torneos),
        ]);
    }

    /**
     * Lo que el jugador lleva en cada juego, para dibujarlo en el panel de la pantalla de modos: el próximo
     * desafío que le falta, por qué escalón va y, si tiene un torneo empezado, el cruce que le toca.
     * Quien todavía no jugó nada ve por dónde se empieza. Un juego sin nada que mostrar no figura.
     *
     * @return array<string, array<string, mixed>>
     */
    private function escenas(?Jugador $jugador, ?Torneo $torneo, Torneos $torneos): array
    {
        $progresos = new Progresos;
        $superados = $progresos->superados($jugador);
        $sueltos = Desafios::sueltos();
        $faltan = array_values(array_filter($sueltos, fn (array $desafio) => ! in_array(Progresos::claveDeDesafio($desafio['clave']), $superados, true)));

        $escenas = [
            // Mano a mano y de a cuatro: las partidas sueltas que lleva jugadas y cuántas ganó.
            'mano-a-mano' => $this->ganadas($jugador, 2),
            'de-a-cuatro' => $this->ganadas($jugador, 4),
            'desafios' => ['superados' => count($sueltos) - count($faltan), 'total' => count($sueltos), 'proximo' => $faltan[0] ?? null],
            'gira' => $this->gira($jugador),
        ];

        // En un torneo empezado, el cruce que le toca: su carta contra la del rival. Si todavía no se sabe contra quién, nada.
        $cruce = $torneo === null ? null : $torneos->cruceDeLaPersona($torneo);
        $rival = $cruce?->rivalDe($torneo->lugarDeLaPersona());

        if ($rival !== null) {
            $escenas['torneo'] = [
                'partido' => mb_strtolower($torneo->nombreDePartido($cruce->ronda)),
                'rival' => $torneo->apodoDe($rival),
                'carta' => $torneo->cartaDe($rival),
                'tuya' => $torneo->cartaDe($torneo->lugarDeLaPersona()),
            ];
        }

        // Sin un cruce a la vista, los torneos que terminó y en cuántos salió campeón.
        $terminados = $jugador === null ? collect() : Torneo::query()->where('jugador_id', $jugador->getKey())->where('estado', '!=', 'en_curso')->get();

        $escenas['torneo'] ??= [
            'jugados' => $terminados->count(),
            'campeon' => $terminados->filter(fn (Torneo $terminado) => $terminado->campeon === $terminado->lugarDeLaPersona())->count(),
        ];

        return $escenas;
    }

    /**
     * Cuántas partidas sueltas de ese juego terminó el jugador y cuántas ganó. No cuentan las de un
     * torneo, un desafío o la escalera, que tienen su propio lugar. Sin sesión, cero.
     *
     * @return array{jugadas: int, ganadas: int}
     */
    private function ganadas(?Jugador $jugador, int $asientos): array
    {
        if ($jugador === null) {
            return ['jugadas' => 0, 'ganadas' => 0];
        }

        $id = $jugador->getKey();
        $suyas = fn () => Partida::query()
            ->whereIn('estado', [Partida::TERMINADA, Partida::ABANDONADA])
            ->whereNotNull('ganador')
            ->where('asientos', $asientos)
            ->whereNull('torneo_id')->whereNull('desafio')->whereNull('escalon');

        return [
            'jugadas' => $suyas()->where(fn (Builder $consulta) => $consulta->where('jugador_id', $id)->orWhere('invitado_id', $id))->count(),
            // Quien abrió la partida ocupa el asiento 0 (o su pareja es la 0); quien se sentó con el link, el 1.
            'ganadas' => $suyas()->where(fn (Builder $consulta) => $consulta
                ->where(fn (Builder $propia) => $propia->where('jugador_id', $id)->where('ganador', 0))
                ->orWhere(fn (Builder $ajena) => $ajena->where('invitado_id', $id)->where('ganador', 1)))->count(),
        ];
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

    /**
     * Lo que la persona lleva de la gira, para su panel en los modos: los barrios, por dónde va y sus fósforos.
     *
     * @return array<string, mixed>
     */
    private function gira(?Jugador $jugador): array
    {
        $barrios = Gira::de($jugador);
        $actual = array_values(array_filter($barrios, fn (array $barrio) => $barrio['abierto'] && ! $barrio['completo']))[0] ?? null;
        $sigue = $actual === null ? null : array_values(array_filter($actual['niveles'], fn (array $nivel) => $nivel['abierto'] && ! $nivel['pasado']))[0] ?? null;

        return [
            'barrios' => array_map(fn (array $barrio) => ['nombre' => $barrio['nombre'], 'abierto' => $barrio['abierto'], 'completo' => $barrio['completo']], $barrios),
            'fosforos' => array_sum(array_column($barrios, 'fosforos')),
            'deTodos' => array_sum(array_column($barrios, 'deTodos')),
            'completa' => $actual === null,
            'barrio' => $actual['nombre'] ?? null,
            'parada' => $sigue['numero'] ?? null,
            'titulo' => $sigue['titulo'] ?? null,
        ];
    }
}
