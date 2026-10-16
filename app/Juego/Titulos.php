<?php

namespace App\Juego;

use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
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
    ];

    /**
     * Las cuentas que salen de los otros juegos.
     */
    public const DE_LOS_JUEGOS = ['torneos_ganados', 'escalones_pasados', 'desafios_pasados'];

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
        'del-club' => ['nombre' => 'Del club', 'femenino' => 'Del club', 'cuenta' => 'escalones_pasados', 'pide' => 8, 'dice' => 'Pasar la escalera entera'],
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
        $equipoDe = fn (int $asiento) => $deACuatro ? $asiento % 2 : $asiento;
        $anterior = null;

        // Lo que hace falta recordar de la mano en juego: las cartas de cada uno, quién cantó el último
        // truco, si la primera baza fue parda y si lo que se quiso fue un falta envido.
        $manos = [];
        $cantoElTruco = null;
        $primeraParda = false;
        $ultimoEnvido = null;
        $faltaQuerida = false;

        foreach ($mesa->pasoAPaso($partida) as [$evento, $motor]) {
            // Si el motor es el mismo de antes, el evento no pasó por el motor y sus hechos ya se contaron.
            if ($motor === $anterior) {
                continue;
            }

            $anterior = $motor;

            if ($evento->tipo === EventoDePartida::REPARTO) {
                $manos = $evento->datos['manos'];
                $cantoElTruco = null;
                $primeraParda = false;
                $ultimoEnvido = null;
                $faltaQuerida = false;
            }

            foreach ($motor->hechos() as $hecho) {
                foreach (array_keys($personas) as $asiento) {
                    $equipo = $equipoDe($asiento);

                    match (true) {
                        $hecho['tipo'] === 'canto' && $hecho['asiento'] === $asiento && in_array($hecho['canto'], ['envido', 'real_envido', 'falta_envido'], true) => $cuentas[$asiento]['envidos_cantados']++,
                        $hecho['tipo'] === 'canto' && $hecho['asiento'] === $asiento && $hecho['canto'] === 'flor' => $cuentas[$asiento]['flores_cantadas']++,
                        $hecho['tipo'] === 'tantos' && $hecho['canto'] === 'envido' && $equipoDe($hecho['ganador']) === $equipo => $this->ganoElEnvido($cuentas[$asiento], $faltaQuerida),
                        // La mano se cerró con un truco querido a favor de su equipo: vale 2 o más, 3 o más con
                        // el retruco querido y 4 con el vale cuatro.
                        $hecho['tipo'] === 'puntos' && $hecho['concepto'] === 'truco' && $hecho['puntos'] >= 2 && $hecho['equipo'] === $equipo => $this->ganoElTruco($cuentas[$asiento], $hecho['puntos']),
                        // Cantó truco, no se lo quisieron y no tenía ninguna carta brava.
                        $hecho['tipo'] === 'mano_cerrada' && $hecho['motivo'] === 'no_quiero' && $cantoElTruco === $asiento && $hecho['ganador'] === $equipo && ! $this->teniaBravas($manos[$asiento] ?? []) => $cuentas[$asiento]['chamuyos']++,
                        $hecho['tipo'] === 'mano_cerrada' && $primeraParda && $hecho['ganador'] === $equipo => $cuentas[$asiento]['manos_con_parda']++,
                        default => null,
                    };
                }

                // Lo que se recuerda de la mano se anota después de contar, para que valga desde el hecho siguiente.
                match (true) {
                    $hecho['tipo'] === 'canto' && in_array($hecho['canto'], ['truco', 'retruco', 'vale_cuatro'], true) => $cantoElTruco = $hecho['asiento'],
                    $hecho['tipo'] === 'canto' && in_array($hecho['canto'], ['envido', 'real_envido', 'falta_envido'], true) => $ultimoEnvido = $hecho['canto'],
                    $hecho['tipo'] === 'respuesta' && $hecho['canto'] === 'envido' => $faltaQuerida = $hecho['quiere'] && $ultimoEnvido === 'falta_envido',
                    $hecho['tipo'] === 'baza' && $hecho['numero'] === 1 => $primeraParda = $hecho['ganador'] === null,
                    default => null,
                };
            }
        }

        return $cuentas;
    }

    /**
     * @param  array<string, int>  $cuentas
     */
    private function ganoElEnvido(array &$cuentas, bool $eraLaFalta): void
    {
        $cuentas['envidos_ganados']++;

        if ($eraLaFalta) {
            $cuentas['faltas_ganadas']++;
        }
    }

    /**
     * @param  array<string, int>  $cuentas
     */
    private function ganoElTruco(array &$cuentas, int $puntos): void
    {
        $cuentas['trucos_ganados']++;

        if ($puntos >= 3) {
            $cuentas['retrucos_ganados']++;
        }

        if ($puntos >= 4) {
            $cuentas['vale_cuatros_ganados']++;
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
     * Pone al día lo que ese jugador hizo en los otros juegos: torneos ganados, escalones y desafíos pasados.
     * Lo llaman la mesa al cerrar una partida de la escalera o de un desafío y el torneo al tener campeón.
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
        ]);
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
