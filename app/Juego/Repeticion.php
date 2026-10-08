<?php

namespace App\Juego;

use App\Models\EventoDePartida;
use App\Models\Partida;
use App\Motor\Partida as Motor;

/**
 * La repetición de una partida terminada, jugada por jugada.
 *
 * No guarda nada: toma los eventos de la partida, los vuelve a pasar por el motor y, por cada cosa que
 * pasó (una carta, un canto, unos puntos), arma un "cuadro": la mesa tal como se veía en ese momento desde
 * el asiento de quien mira. Avanzar o retroceder es cambiar de cuadro.
 *
 * Qué cartas del rival se ven: contra el bot, todas, que para eso es una repetición. Entre dos personas,
 * solo las que se vieron en la mesa (las jugadas y las que mostró por el envido o la flor): el reglamento
 * dice que las demás vuelven al mazo boca abajo y nunca se muestran, y mostrarlas le contaría a cada uno
 * cuándo el otro le mintió.
 */
final class Repeticion
{
    private const CANTOS = [
        'envido' => 'Envido',
        'real_envido' => 'Real envido',
        'falta_envido' => 'Falta envido',
        'flor' => 'Flor',
        'contraflor' => 'Contraflor',
        'contraflor_al_resto' => 'Contraflor al resto',
        'truco' => 'Truco',
        'retruco' => 'Retruco',
        'vale_cuatro' => 'Vale cuatro',
    ];

    private const DEL_TRUCO = ['truco', 'retruco', 'vale_cuatro'];

    private const NIVEL_DE_TRUCO = ['', 'Truco', 'Retruco', 'Vale cuatro'];

    private const CONCEPTOS = [
        'envido' => 'envido',
        'envido_no_querido' => 'envido no querido',
        'envido_no_jugado' => 'envido sin jugar',
        'flor' => 'flor',
        'contraflor' => 'contraflor',
        'contraflor_no_querida' => 'contraflor no querida',
        'mano' => 'mano',
    ];

    public function __construct(private readonly Mesa $mesa) {}

    /**
     * Los cuadros de la partida vistos desde un asiento, y en qué cuadro empieza cada mano.
     *
     * @return array{cuadros: list<array<string, mixed>>, manos: list<int>}
     */
    public function de(Partida $partida, int $asiento): array
    {
        $ellos = 1 - $asiento;
        $rival = $this->nombreDelRival($partida, $asiento);
        $quien = fn (?int $otro): string => $otro === $asiento ? 'vos' : 'rival';

        $cuadro = [
            'mano' => 0,
            'tanteo' => ['vos' => 0, 'rival' => 0],
            'propias' => [null, null, null],
            'rival' => [null, null, null],
            'bazas' => self::bazasVacias(),
            'canto' => null,
            'texto' => '',
            'fin' => null,
        ];
        $cuadros = [];
        $manos = [];

        foreach ($this->mesa->pasoAPaso($partida) as [$evento, $motor]) {
            // Que quien abrió la sala llegó a la mesa no es algo que se haya visto en ella.
            if ($evento->tipo === EventoDePartida::LLEGADA) {
                continue;
            }

            if ($evento->tipo === EventoDePartida::ABANDONO) {
                $cuadros[] = [...$cuadro, 'canto' => null, 'texto' => $this->comoAbandono($evento, $asiento, $rival), 'fin' => $quien(1 - $evento->asiento)];

                continue;
            }

            foreach (self::enOrdenDeRelato($motor->hechos()) as $hecho) {
                $cuadro['canto'] = null;
                $de = $quien($hecho['asiento'] ?? $hecho['equipo'] ?? $hecho['ganador'] ?? null);
                $sujeto = $de === 'vos' ? null : self::alEmpezar($rival);

                switch ($hecho['tipo']) {
                    case 'reparto':
                        $manos[] = count($cuadros);
                        $repartidas = $evento->datos['manos'];
                        $cuadro = [
                            ...$cuadro,
                            'mano' => $hecho['numero'],
                            'tanteo' => ['vos' => $motor->tanteo()[$asiento], 'rival' => $motor->tanteo()[$ellos]],
                            'propias' => array_values($repartidas[$asiento]),
                            // Contra el bot se ven sus cartas. Las de otra persona quedan boca abajo.
                            'rival' => $partida->entre_personas ? ['dorso', 'dorso', 'dorso'] : array_values($repartidas[$ellos]),
                            'bazas' => self::bazasVacias(),
                            'texto' => "Mano {$hecho['numero']}. ".($hecho['mano'] === $asiento ? 'Sos mano.' : 'Es mano '.$rival.'.'),
                        ];
                        break;

                    case 'carta':
                        $cuadro['bazas'][$this->bazaDe($motor, $hecho)][$de] = $hecho['carta'];
                        $cuadro = $this->sinLaCarta($cuadro, $de, $hecho['carta']);
                        $cuadro['texto'] = ($sujeto === null ? 'Jugaste' : "{$sujeto} jugó").' el '.self::nombreDe($hecho['carta']).'.';
                        break;

                    case 'baza':
                        $cuadro['bazas'][$hecho['numero'] - 1]['ganador'] = $hecho['ganador'] === null ? 'parda' : $de;
                        $cuadro['texto'] = match (true) {
                            $hecho['ganador'] === null => 'Parda.',
                            $sujeto === null => 'Ganaste la baza.',
                            default => "{$sujeto} ganó la baza.",
                        };
                        break;

                    case 'canto':
                        $canto = self::CANTOS[$hecho['canto']];
                        $cuadro['canto'] = ['quien' => $de, 'texto' => $canto, 'tono' => in_array($hecho['canto'], self::DEL_TRUCO, true) ? 'copa' : 'oro'];
                        $cuadro['texto'] = ($sujeto === null ? 'Cantaste' : "{$sujeto} cantó").' '.mb_strtolower($canto).'.';
                        break;

                    case 'respuesta':
                        $cuadro['canto'] = ['quien' => $de, 'texto' => $hecho['quiere'] ? 'Quiero' : 'No quiero', 'tono' => $hecho['quiere'] ? 'basto' : 'copa'];
                        $cuadro['texto'] = match (true) {
                            $sujeto === null => $hecho['quiere'] ? 'Quisiste.' : 'No quisiste.',
                            default => $sujeto.($hecho['quiere'] ? ' quiso.' : ' no quiso.'),
                        };
                        break;

                    case 'tantos':
                        $cuadro['texto'] = $this->tantosDichos($hecho, $asiento, $rival);
                        break;

                    case 'puntos':
                        $cuadro['tanteo'] = ['vos' => $hecho['tanteo'][$asiento], 'rival' => $hecho['tanteo'][$ellos]];
                        $cuadro['texto'] = ($sujeto === null ? 'Sumaste' : "{$sujeto} sumó")." {$hecho['puntos']}: ".$this->conceptoDe($hecho['concepto'], $motor).'.';
                        break;

                    case 'mazo':
                        $vencio = $evento->tipo === EventoDePartida::VENCIMIENTO;
                        $cuadro['texto'] = match (true) {
                            $sujeto === null => $vencio ? 'Se te venció el turno.' : 'Te fuiste al mazo.',
                            default => $vencio ? "A {$rival} se le venció el turno." : "{$sujeto} se fue al mazo.",
                        };
                        break;

                    case 'mano_cerrada':
                        // Las cartas que el rival mostró por el envido o la flor se dan vuelta; las demás vuelven al mazo.
                        if ($partida->entre_personas) {
                            $cuadro['rival'] = $this->mostradasSinJugar($motor, $ellos);
                        }

                        $cuadro['texto'] = $sujeto === null ? 'Ganaste la mano.' : "{$sujeto} ganó la mano.";
                        break;

                    case 'partida_terminada':
                        $cuadro['fin'] = $de;
                        $cuadro['texto'] = ($sujeto === null ? 'Ganaste la partida' : "{$sujeto} ganó la partida").", {$cuadro['tanteo']['vos']} a {$cuadro['tanteo']['rival']}.";
                        break;
                }

                $cuadros[] = $cuadro;
            }
        }

        return ['cuadros' => $cuadros, 'manos' => $manos];
    }

    /**
     * El motor avisa que la partida terminó apenas se anota el punto que la cierra, antes de cerrar la
     * mano. Al contarla va al final: lo último que se lee es quién ganó la partida.
     *
     * @param  list<array<string, mixed>>  $hechos
     * @return list<array<string, mixed>>
     */
    private static function enOrdenDeRelato(array $hechos): array
    {
        $esElFinal = fn (array $hecho): bool => $hecho['tipo'] === 'partida_terminada';

        return [...array_filter($hechos, fn (array $hecho) => ! $esElFinal($hecho)), ...array_filter($hechos, $esElFinal)];
    }

    /**
     * Cómo se nombra al rival en medio de una frase: "el bot", o el apodo de la otra persona.
     */
    public function nombreDelRival(Partida $partida, int $asiento): string
    {
        if (! $partida->entre_personas) {
            return 'el bot';
        }

        return ($asiento === Mesa::JUGADOR ? $partida->invitado : $partida->jugador)?->apodo ?? 'tu rival';
    }

    /**
     * @return list<array{vos: ?string, rival: ?string, ganador: ?string}>
     */
    private static function bazasVacias(): array
    {
        return array_fill(0, 3, ['vos' => null, 'rival' => null, 'ganador' => null]);
    }

    private static function nombreDe(string $carta): string
    {
        return str_replace('-', ' de ', $carta);
    }

    /**
     * El rival al empezar una frase. Un apodo es un nombre propio y va como lo escribió su dueño;
     * lo único que cambia es la mayúscula de "el bot" y de "tu rival".
     */
    private static function alEmpezar(string $rival): string
    {
        return match ($rival) {
            'el bot' => 'El bot',
            'tu rival' => 'Tu rival',
            default => $rival,
        };
    }

    /**
     * En qué baza cayó una carta, según cómo quedó el motor después de jugarla.
     *
     * @param  array<string, mixed>  $hecho
     */
    private function bazaDe(Motor $motor, array $hecho): int
    {
        foreach ($motor->aArray()['bazas'] as $numero => $baza) {
            foreach ($baza['jugadas'] as [$asiento, $carta]) {
                if ($asiento === $hecho['asiento'] && $carta === $hecho['carta']) {
                    return $numero;
                }
            }
        }

        return 0;
    }

    /**
     * La mano de quien jugó, ya sin esa carta. El lugar queda vacío, así las otras no se corren. De una
     * persona no se sabe qué lugar ocupaba la carta: se saca el último dorso.
     *
     * @param  array<string, mixed>  $cuadro
     * @return array<string, mixed>
     */
    private function sinLaCarta(array $cuadro, string $de, string $carta): array
    {
        $mano = $de === 'vos' ? 'propias' : 'rival';
        $lugar = array_search($carta, $cuadro[$mano], true);

        if ($lugar === false) {
            $dorsos = array_keys($cuadro[$mano], 'dorso', true);
            $lugar = $dorsos === [] ? null : end($dorsos);
        }

        if ($lugar !== null) {
            $cuadro[$mano][$lugar] = null;
        }

        return $cuadro;
    }

    /**
     * Las cartas que el rival mostró al cerrar la mano y no había jugado, en tres lugares.
     *
     * @return list<?string>
     */
    private function mostradasSinJugar(Motor $motor, int $ellos): array
    {
        $estado = $motor->aArray();
        $jugadas = [];

        foreach ($estado['bazas'] as $baza) {
            foreach ($baza['jugadas'] as [, $carta]) {
                $jugadas[] = $carta;
            }
        }

        $mostradas = array_values(array_diff($estado['cierre']['mostradas'][$ellos] ?? [], $jugadas));

        return array_pad($mostradas, 3, null);
    }

    /**
     * Los tantos como se dijeron en la mesa: el primero canta su número y el otro contesta con uno mayor
     * o con "son buenas". Un tanto que no se dijo tampoco aparece acá.
     *
     * @param  array<string, mixed>  $hecho
     */
    private function tantosDichos(array $hecho, int $asiento, string $rival): string
    {
        $frases = [];

        foreach ($hecho['tantos'] as $orden => $dicho) {
            $vos = $dicho['asiento'] === $asiento;

            $frases[] = match (true) {
                $dicho['tanto'] === null => $vos ? 'Dijiste: son buenas.' : self::alEmpezar($rival).' dijo: son buenas.',
                default => ($vos ? 'Cantaste' : self::alEmpezar($rival).' cantó')." {$dicho['tanto']}".($orden === 0 ? '.' : ', son mejores.'),
            };
        }

        return implode(' ', $frases);
    }

    /**
     * Por qué se sumaron unos puntos. Lo que vale la mano lleva el nombre del último canto querido; si
     * nadie lo quiso, el del canto que se rechazó.
     */
    private function conceptoDe(string $concepto, Motor $motor): string
    {
        if ($concepto !== 'truco') {
            return self::CONCEPTOS[$concepto] ?? $concepto;
        }

        ['nivel' => $nivel, 'querido' => $querido] = $motor->aArray()['truco'];
        $nombre = mb_strtolower(self::NIVEL_DE_TRUCO[$querido === $nivel ? $querido : $nivel]);

        return $querido === $nivel ? $nombre : "{$nombre} no querido";
    }

    private function comoAbandono(EventoDePartida $evento, int $asiento, string $rival): string
    {
        $porVencimientos = ($evento->datos['motivo'] ?? null) === 'vencimientos';

        if ($evento->asiento === $asiento) {
            return $porVencimientos ? 'Se te venció el turno '.Mesa::VENCIMIENTOS_PARA_PERDER.' veces seguidas: perdiste la partida.' : 'Abandonaste la partida.';
        }

        return $porVencimientos ? self::alEmpezar($rival).' dejó de jugar y perdió la partida.' : self::alEmpezar($rival).' abandonó la partida.';
    }
}
