<?php

namespace App\Juego;

use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Progreso;
use App\Models\Torneo;
use App\Motor\Carta;
use Illuminate\Support\Facades\DB;

/**
 * Los títulos: lo que dice debajo del apodo. No ocupan niveles: se ganan haciendo cosas en la mesa.
 *
 * Cada uno pide una cantidad de algo que se cuenta desde los hechos del motor: envidos cantados, envidos
 * ganados (los que se quisieron y se cantaron los tantos), trucos queridos ganados, flores cantadas,
 * retrucos y vale cuatros queridos ganados, trucos ganados sin que te quieran y sin cartas bravas, manos
 * ganadas después de una primera parda y faltas envido queridos ganados. Las cuentas se guardan en el
 * jugador al cerrar cada partida, como los fósforos, así ninguna página cuenta nada al abrirse;
 * recalcular() las rehace desde las partidas.
 *
 * Tres títulos salen de los otros juegos (torneos ganados, la escalera entera y los ocho desafíos): esas
 * cuentas se ponen al día desde los torneos y los progresos con ponerAlDia().
 *
 * Los desafíos no cuentan: son la misma mano repetida, y reintentar no puede ser la forma de ganar un título.
 *
 * Cada título tiene sus dos formas y cada uno elige cómo se lee el suyo (decidido por Román el 15 de
 * octubre de 2026). Pichón es igual para todos.
 */
final class Titulos
{
    public const DE_FABRICA = 'pichon';

    /**
     * Las cuentas que se guardan en el jugador.
     */
    public const CUENTAS = [
        'envidos_cantados', 'envidos_ganados', 'trucos_ganados', 'flores_cantadas',
        'retrucos_ganados', 'chamuyos', 'manos_con_parda', 'faltas_ganadas', 'vale_cuatros_ganados',
        // Las de los logros de la libreta (M23).
        'treinta_y_tres', 'envido_primero', 'quieros_ganados', 'contraflores_ganadas', 'partidas_jugadas',
        'partidas_ganadas', 'remontadas', 'sin_salir_de_malas', 'dificil_ganadas', 'ultra_ganadas',
        'de_a_cuatro_ganadas', 'senas_hechas', 'revanchas_ganadas', 'tres_pardas', 'treinta_a_cero',
        'ancho_contra_ancho', 'contraflor_al_resto',
        // El sello del gallito (M25).
        'gallitos_ganados',
    ];

    private const ENVIDOS = ['envido', 'real_envido', 'falta_envido'];

    private const TRUCOS = ['truco', 'retruco', 'vale_cuatro'];

    /**
     * Cuántos puntos abajo hay que haber estado para que ganar sea ganar de atrás.
     */
    private const REMONTADA = 10;

    /**
     * Las cuentas que salen de los otros juegos.
     */
    public const DE_LOS_JUEGOS = [
        'torneos_ganados', 'escalones_pasados', 'desafios_pasados', 'mejor_racha',
        'barrios_completos', 'paradas_pasadas', 'paradas_perfectas', 'jefes_a_la_primera',
    ];

    /**
     * Las cartas bravas: los anchos, los sietes de espada y de oro y los tres. Chamuyar es ganar sin ninguna.
     */
    private const BRAVA_DESDE = 10;

    /**
     * Los títulos: sus dos formas, qué cuenta piden y cuánto. "dice" es cómo se cuenta lo que pide, cuando
     * no alcanza con el número y el nombre de la cuenta.
     *
     * @var array<string, array{nombre: string, femenino: string, cuenta: string|null, pide: int, dice?: string}>
     */
    public const TODOS = [
        'pichon' => ['nombre' => 'Pichón', 'femenino' => 'Pichón', 'cuenta' => null, 'pide' => 0],
        'cantor' => ['nombre' => 'Cantor', 'femenino' => 'Cantora', 'cuenta' => 'envidos_cantados', 'pide' => 25],
        'tantero' => ['nombre' => 'Tantero', 'femenino' => 'Tantera', 'cuenta' => 'envidos_ganados', 'pide' => 25],
        'truquero' => ['nombre' => 'Truquero', 'femenino' => 'Truquera', 'cuenta' => 'trucos_ganados', 'pide' => 25],
        'florido' => ['nombre' => 'Florido', 'femenino' => 'Florida', 'cuenta' => 'flores_cantadas', 'pide' => 10],
        'retruquero' => ['nombre' => 'Retruquero', 'femenino' => 'Retruquera', 'cuenta' => 'retrucos_ganados', 'pide' => 10],
        'chamuyero' => ['nombre' => 'Chamuyero', 'femenino' => 'Chamuyera', 'cuenta' => 'chamuyos', 'pide' => 10],
        'pardero' => ['nombre' => 'Pardero', 'femenino' => 'Pardera', 'cuenta' => 'manos_con_parda', 'pide' => 10],
        'falta-envido' => ['nombre' => 'Falta envido', 'femenino' => 'Falta envido', 'cuenta' => 'faltas_ganadas', 'pide' => 1, 'dice' => 'Ganar un falta envido querido'],
        'vale-cuatro' => ['nombre' => 'Vale cuatro', 'femenino' => 'Vale cuatro', 'cuenta' => 'vale_cuatros_ganados', 'pide' => 1, 'dice' => 'Ganar un vale cuatro querido'],
        'campeon' => ['nombre' => 'Campeón', 'femenino' => 'Campeona', 'cuenta' => 'torneos_ganados', 'pide' => 1, 'dice' => 'Ganar un torneo'],
        // Los de la gira: cada jefe deja el suyo. Los barrios se completan en orden, así que vencer al jefe de San
        // Telmo es tener dos barrios completos.
        'del-club' => ['nombre' => 'Del club', 'femenino' => 'Del club', 'cuenta' => 'barrios_completos', 'pide' => 1, 'dice' => 'Vencer a Don Anselmo en Boedo'],
        'anticuario' => ['nombre' => 'Anticuario', 'femenino' => 'Anticuaria', 'cuenta' => 'barrios_completos', 'pide' => 2, 'dice' => 'Vencer a Don Ceferino en San Telmo'],
        'estibador' => ['nombre' => 'Estibador', 'femenino' => 'Estibadora', 'cuenta' => 'barrios_completos', 'pide' => 3, 'dice' => 'Vencer al Grúa Bianchi en La Boca'],
        'adivino' => ['nombre' => 'Adivino', 'femenino' => 'Adivina', 'cuenta' => 'barrios_completos', 'pide' => 4, 'dice' => 'Vencer a Madama Ferrante en Pompeya'],
        'resero' => ['nombre' => 'Resero', 'femenino' => 'Resera', 'cuenta' => 'barrios_completos', 'pide' => 5, 'dice' => 'Vencer al Resero en Mataderos'],
        'patron' => ['nombre' => 'Patrón del Mercado', 'femenino' => 'Patrona del Mercado', 'cuenta' => 'barrios_completos', 'pide' => 6, 'dice' => 'Vencer al Patrón del Mercado en el Abasto'],
        'porteno' => ['nombre' => 'Porteño', 'femenino' => 'Porteña', 'cuenta' => 'paradas_pasadas', 'pide' => 38, 'dice' => 'Pasar las 38 paradas de la gira'],
        'desafiante' => ['nombre' => 'Desafiante', 'femenino' => 'Desafiante', 'cuenta' => 'desafios_pasados', 'pide' => 8, 'dice' => 'Pasar los ocho desafíos'],
    ];

    /**
     * Cómo se dice lo que pide cada cuenta, para la Colección.
     */
    public const QUE_PIDE = [
        'envidos_cantados' => 'envidos cantados',
        'envidos_ganados' => 'envidos ganados',
        'trucos_ganados' => 'trucos queridos ganados',
        'flores_cantadas' => 'flores cantadas',
        'retrucos_ganados' => 'retrucos queridos ganados',
        'chamuyos' => 'trucos ganados sin que te quieran y sin cartas bravas',
        'manos_con_parda' => 'manos ganadas después de una primera parda',
        'faltas_ganadas' => 'faltas envido queridos ganados',
        'vale_cuatros_ganados' => 'vale cuatros queridos ganados',
        'torneos_ganados' => 'torneos ganados',
        'escalones_pasados' => 'escalones de la escalera',
        'barrios_completos' => 'barrios de la gira completos',
        'paradas_pasadas' => 'paradas de la gira pasadas',
        'paradas_perfectas' => 'paradas pasadas con los tres fósforos',
        'jefes_a_la_primera' => 'jefes vencidos al primer intento',
        'desafios_pasados' => 'desafíos superados',
    ];

    /**
     * Lo que hizo cada asiento en esa partida: asiento => cuentas. Solo los asientos de personas.
     *
     * @return array<int, array<string, int>>
     */
    public function en(Partida $partida, Mesa $mesa): array
    {
        $personas = Camino::personas($partida);

        // Una sala que se cerró sin que se sentara nadie no llegó a repartir: no tiene nada que contar.
        if ($personas === [] || $partida->esDesafio() || $partida->primer_mano === null
            || ! in_array($partida->estado, [Partida::TERMINADA, Partida::ABANDONADA], true)) {
            return [];
        }

        $cuentas = array_fill_keys(array_keys($personas), array_fill_keys(self::CUENTAS, 0));
        $deACuatro = $partida->deACuatro();
        $gallito = $partida->esGallito();
        // En el gallito el gallo es el mano de cada mano y juega solo (equipo 0) contra la pareja (equipo 1).
        $gallo = 0;
        $equipoDe = function (int $asiento) use ($deACuatro, $gallito, &$gallo) {
            return match (true) {
                $gallito => $asiento === $gallo ? 0 : 1,
                $deACuatro => $asiento % 2,
                default => $asiento,
            };
        };
        $anterior = null;

        // Lo que hace falta recordar de la mano en juego: las cartas de cada uno, quién cantó el último truco y
        // si quedó sin contestar, quién lo quiso, cómo salieron las bazas, cuál fue el último envido y la última
        // contraflor cantados y si lo que se quiso fue un falta envido.
        $mano = fn () => [
            'manos' => [], 'cantoElTruco' => null, 'trucoSinContestar' => null, 'quisieron' => [], 'pardas' => [],
            'baza' => [], 'ultimoEnvido' => null, 'faltaQuerida' => false, 'envidoPrimero' => [], 'alResto' => false,
        ];
        $m = $mano();
        // Y de la partida: el tanteo y lo más abajo que estuvo cada equipo.
        $tanteo = array_fill(0, $gallito ? 3 : 2, 0);
        $abajo = $tanteo;

        foreach ($mesa->pasoAPaso($partida) as [$evento, $motor]) {
            // Una seña no pasa por el motor: se cuenta del evento.
            if ($evento->tipo === EventoDePartida::SENA && isset($cuentas[$evento->asiento])) {
                $cuentas[$evento->asiento]['senas_hechas']++;
            }

            // Si el motor es el mismo de antes, el evento no pasó por el motor y sus hechos ya se contaron.
            if ($motor === $anterior) {
                continue;
            }

            $anterior = $motor;

            if ($evento->tipo === EventoDePartida::REPARTO) {
                $m = $mano();
                $m['manos'] = $evento->datos['manos'];
                $gallo = $motor->mano();
            }

            foreach ($motor->hechos() as $hecho) {
                foreach (array_keys($personas) as $asiento) {
                    $this->contar($cuentas[$asiento], $hecho, $asiento, $equipoDe, $m);
                }

                $this->recordar($m, $hecho, $equipoDe);

                if ($hecho['tipo'] === 'puntos') {
                    $tanteo = $hecho['tanteo'];

                    // Lo más abajo que estuvo cada uno: contra el que iba más arriba de los demás.
                    foreach (array_keys($tanteo) as $lado) {
                        $abajo[$lado] = max($abajo[$lado] ?? 0, self::delRival($tanteo, $lado) - $tanteo[$lado]);
                    }
                }
            }
        }

        // Lo de la partida entera, para quien la ganó.
        $camino = new Camino;

        foreach (array_keys($personas) as $asiento) {
            // En el tanteo de la partida, su lugar: su equipo o, en el gallito, su asiento (cada uno suma aparte).
            $equipo = $gallito ? $asiento : $equipoDe($asiento);
            $fosforos = $camino->fosforosEn($partida, $asiento, $mesa);
            $gano = $fosforos === Camino::POR_GANAR;
            $terminada = $partida->estado === Partida::TERMINADA;
            $suyo = $tanteo[$equipo];
            $delRival = self::delRival($tanteo, $equipo);

            $cuentas[$asiento]['partidas_jugadas'] += (int) ($fosforos > 0);

            if (! $gano) {
                continue;
            }

            $cuentas[$asiento]['partidas_ganadas']++;
            $cuentas[$asiento]['remontadas'] += (int) (($abajo[$equipo] ?? 0) >= self::REMONTADA);
            $cuentas[$asiento]['sin_salir_de_malas'] += (int) ($terminada && $suyo >= $partida->puntos && $delRival < intdiv($partida->puntos, 2));
            $cuentas[$asiento]['treinta_a_cero'] += (int) ($terminada && $partida->puntos === 30 && $suyo >= 30 && $delRival === 0);
            $cuentas[$asiento]['dificil_ganadas'] += (int) ($partida->nivel_bot === Nivel::Dificil->value);
            $cuentas[$asiento]['ultra_ganadas'] += (int) ($partida->nivel_bot === Nivel::UltraDificil->value);
            $cuentas[$asiento]['de_a_cuatro_ganadas'] += (int) $deACuatro;
            $cuentas[$asiento]['gallitos_ganados'] += (int) $gallito;
            $cuentas[$asiento]['revanchas_ganadas'] += (int) ($partida->anterior_id !== null);
        }

        return $cuentas;
    }

    /**
     * Los puntos del rival de un lado del tanteo: el otro equipo o, en el gallito, el que va más arriba de los otros dos.
     *
     * @param  list<int>  $tanteo
     */
    private static function delRival(array $tanteo, int $lado): int
    {
        unset($tanteo[$lado]);

        return max($tanteo);
    }

    /**
     * Suma en las cuentas de un asiento lo que dice un hecho del motor.
     *
     * @param  array<string, int>  $cuentas
     * @param  array<string, mixed>  $hecho
     * @param  array<string, mixed>  $m  Lo que se recuerda de la mano.
     */
    private function contar(array &$cuentas, array $hecho, int $asiento, \Closure $equipoDe, array $m): void
    {
        $equipo = $equipoDe($asiento);
        $suyo = fn (?int $otro) => $otro !== null && $equipoDe($otro) === $equipo;

        switch ($hecho['tipo']) {
            case 'canto':
                if ($hecho['asiento'] === $asiento && in_array($hecho['canto'], self::ENVIDOS, true)) {
                    $cuentas['envidos_cantados']++;
                }

                if ($hecho['asiento'] === $asiento && $hecho['canto'] === 'flor') {
                    $cuentas['flores_cantadas']++;
                }

                break;

            case 'tantos':
                if (! $suyo($hecho['ganador'])) {
                    break;
                }

                if ($hecho['canto'] === 'contraflor') {
                    $cuentas['contraflores_ganadas']++;
                    $cuentas['contraflor_al_resto'] += (int) $m['alResto'];

                    break;
                }

                $cuentas['envidos_ganados']++;
                $cuentas['faltas_ganadas'] += (int) $m['faltaQuerida'];
                $cuentas['envido_primero'] += (int) in_array($asiento, $m['envidoPrimero'], true);

                // Su propio tanto, si fue el que ganó: el que dijo en voz alta.
                foreach ($hecho['tantos'] as $dicho) {
                    if ($dicho['asiento'] === $asiento && $hecho['ganador'] === $asiento && $dicho['tanto'] === 33) {
                        $cuentas['treinta_y_tres']++;
                    }
                }

                break;

            case 'puntos':
                // La mano se cerró con un truco querido a favor de su equipo: vale 2 o más, 3 o más con el retruco
                // querido y 4 con el vale cuatro.
                if ($hecho['concepto'] === 'truco' && $hecho['puntos'] >= 2 && $hecho['equipo'] === $equipo) {
                    $cuentas['trucos_ganados']++;
                    $cuentas['retrucos_ganados'] += (int) ($hecho['puntos'] >= 3);
                    $cuentas['vale_cuatros_ganados'] += (int) ($hecho['puntos'] >= 4);
                    $cuentas['quieros_ganados'] += (int) in_array($asiento, $m['quisieron'], true);
                }

                break;

            case 'baza':
                // El ancho de espada le ganó al de basto: los dos están en la baza y la ganó él.
                if ($hecho['ganador'] === $asiento && ($m['baza'][$asiento] ?? null) === '1-espada'
                    && in_array('1-basto', array_diff_key($m['baza'], [$asiento => true]), true)) {
                    $cuentas['ancho_contra_ancho']++;
                }

                break;

            case 'mano_cerrada':
                if ($hecho['ganador'] !== $equipo) {
                    break;
                }

                // Cantó truco, no se lo quisieron y no tenía ninguna carta brava.
                if ($hecho['motivo'] === 'no_quiero' && $m['cantoElTruco'] === $asiento && ! $this->teniaBravas($m['manos'][$asiento] ?? [])) {
                    $cuentas['chamuyos']++;
                }

                if (($m['pardas'][1] ?? false) === true) {
                    $cuentas['manos_con_parda']++;
                }

                if ($hecho['motivo'] === 'bazas' && count(array_filter($m['pardas'])) === 3) {
                    $cuentas['tres_pardas']++;
                }

                break;
        }
    }

    /**
     * Anota lo que hay que recordar de la mano, después de contar, para que valga desde el hecho siguiente.
     *
     * @param  array<string, mixed>  $m
     * @param  array<string, mixed>  $hecho
     */
    private function recordar(array &$m, array $hecho, \Closure $equipoDe): void
    {
        switch ($hecho['tipo']) {
            case 'canto':
                if (in_array($hecho['canto'], self::TRUCOS, true)) {
                    $m['cantoElTruco'] = $hecho['asiento'];
                    $m['trucoSinContestar'] = $hecho['asiento'];
                }

                if (in_array($hecho['canto'], self::ENVIDOS, true)) {
                    $m['ultimoEnvido'] = $hecho['canto'];

                    // El envido está primero: le habían cantado truco y contestó con envido.
                    if ($m['trucoSinContestar'] !== null && $equipoDe($m['trucoSinContestar']) !== $equipoDe($hecho['asiento'])) {
                        $m['envidoPrimero'][] = $hecho['asiento'];
                    }
                }

                if (in_array($hecho['canto'], ['contraflor', 'contraflor_al_resto'], true)) {
                    $m['alResto'] = $hecho['canto'] === 'contraflor_al_resto';
                }

                break;

            case 'respuesta':
                if ($hecho['canto'] === 'envido') {
                    $m['faltaQuerida'] = $hecho['quiere'] && $m['ultimoEnvido'] === 'falta_envido';
                }

                if ($hecho['canto'] === 'truco') {
                    $m['trucoSinContestar'] = null;

                    if ($hecho['quiere']) {
                        $m['quisieron'][] = $hecho['asiento'];
                    }
                }

                break;

            case 'carta':
                $m['baza'][$hecho['asiento']] = $hecho['carta'];

                break;

            case 'baza':
                $m['pardas'][$hecho['numero']] = $hecho['ganador'] === null;
                $m['baza'] = [];

                break;
        }
    }

    /**
     * @param  list<string>  $mano
     */
    private function teniaBravas(array $mano): bool
    {
        foreach ($mano as $carta) {
            if (Carta::de($carta)->jerarquia() >= self::BRAVA_DESDE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pone al día lo que no se suma partida por partida: torneos ganados, escalones y desafíos pasados, y la
     * mejor racha de ganadas que cuentan para el ranking. Lo llaman la mesa al cerrar cada partida y el torneo
     * al tener campeón.
     */
    public function ponerAlDia(?Jugador $jugador): void
    {
        if ($jugador === null) {
            return;
        }

        $superados = (new Progresos)->superados($jugador);
        $sueltos = array_map(fn (array $ficha) => Progresos::claveDeDesafio($ficha['clave']), Desafios::sueltos());

        Jugador::query()->whereKey($jugador->getKey())->update([
            'torneos_ganados' => Torneo::query()
                ->where('jugador_id', $jugador->getKey())
                ->whereNotNull('campeon')
                ->get()
                ->filter(fn (Torneo $torneo) => $torneo->campeon === $torneo->lugarDeLaPersona())
                ->count(),
            'escalones_pasados' => count(array_filter($superados, fn (string $clave) => str_starts_with($clave, 'escalon:'))),
            'desafios_pasados' => count(array_intersect($superados, $sueltos)),
            'mejor_racha' => (new Numeros)->de($jugador)['mejorRacha'],
            ...self::deLaGira($jugador),
        ]);
    }

    /**
     * Lo que lleva de la gira: barrios completos (vencido su jefe), paradas pasadas, paradas con los tres fósforos y
     * jefes vencidos en el primer intento. Sale de lo anotado en progresos, que es el mejor intento de cada parada.
     *
     * @return array{barrios_completos: int, paradas_pasadas: int, paradas_perfectas: int, jefes_a_la_primera: int}
     */
    public static function deLaGira(Jugador $jugador): array
    {
        $fosforos = Gira::fosforos($jugador);
        $jefes = array_map(fn (array $barrio) => Gira::clave($barrio['clave'], count($barrio['niveles'])), Gira::barrios());
        $intentos = Progreso::query()->where('jugador_id', $jugador->getKey())->whereIn('clave', $jefes)->whereNotNull('superado_en')->pluck('intentos', 'clave');

        return [
            'barrios_completos' => count(array_filter($jefes, fn (string $clave) => isset($fosforos[$clave]))),
            'paradas_pasadas' => count($fosforos),
            'paradas_perfectas' => count(array_filter($fosforos, fn (int $cuantos) => $cuantos >= FosforosDeGira::MAXIMO)),
            'jefes_a_la_primera' => $intentos->filter(fn ($veces) => (int) $veces === 1)->count(),
        ];
    }

    /**
     * Le suma a cada persona lo que hizo en la partida recién cerrada. Lo llama la mesa una sola vez, al cerrarla.
     */
    public function anotar(Partida $partida, Mesa $mesa): void
    {
        foreach ($this->en($partida, $mesa) as $asiento => $cuentas) {
            $sumas = array_filter($cuentas);

            if ($sumas !== []) {
                Jugador::query()->whereKey(Camino::personas($partida)[$asiento])->incrementEach($sumas);
            }
        }
    }

    /**
     * Pone las cuentas en cero y las vuelve a sumar desde las partidas cerradas.
     */
    public function recalcular(Mesa $mesa): void
    {
        DB::transaction(function () use ($mesa) {
            $total = [];

            $cerradas = Partida::query()
                ->whereIn('estado', [Partida::TERMINADA, Partida::ABANDONADA])
                ->orderBy('id');

            foreach ($cerradas->lazy(200) as $partida) {
                foreach ($this->en($partida, $mesa) as $asiento => $cuentas) {
                    $jugador = Camino::personas($partida)[$asiento];

                    foreach ($cuentas as $cuenta => $cuanto) {
                        $total[$jugador][$cuenta] = ($total[$jugador][$cuenta] ?? 0) + $cuanto;
                    }
                }
            }

            Jugador::query()->update(array_fill_keys(self::CUENTAS, 0));

            foreach ($total as $jugador => $cuentas) {
                if (array_filter($cuentas) !== []) {
                    Jugador::query()->whereKey($jugador)->update($cuentas);
                }
            }

            // Y lo de los otros juegos, para quien jugó alguno.
            Jugador::query()->update(array_fill_keys(self::DE_LOS_JUEGOS, 0));

            $conJuegos = Torneo::query()->pluck('jugador_id')
                ->merge(DB::table('progresos')->pluck('jugador_id'))
                ->unique();

            foreach (Jugador::query()->whereKey($conJuegos)->get() as $jugador) {
                $this->ponerAlDia($jugador);
            }
        });
    }

    /**
     * Si ese jugador ya ganó ese título.
     */
    public static function gano(?Jugador $jugador, string $titulo): bool
    {
        $datos = self::TODOS[$titulo] ?? null;

        return $datos !== null && ($datos['cuenta'] === null || (int) ($jugador?->{$datos['cuenta']} ?? 0) >= $datos['pide']);
    }

    /**
     * Cómo se lee un título en la forma que eligió.
     */
    public static function nombre(string $titulo, bool $femenino = false): string
    {
        $datos = self::TODOS[$titulo] ?? self::TODOS[self::DE_FABRICA];

        return $femenino ? $datos['femenino'] : $datos['nombre'];
    }
}
