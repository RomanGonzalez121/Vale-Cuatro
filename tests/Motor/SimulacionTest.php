<?php

namespace Tests\Motor;

use App\Motor\Accion;
use App\Motor\AccionInvalida;
use App\Motor\Azar;
use App\Motor\Carta;
use App\Motor\Fase;
use App\Motor\Mazo;
use App\Motor\Partida;
use App\Motor\TipoDeAccion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Partidas enteras jugadas al azar. No prueban una regla puntual sino lo que tiene que valer siempre.
 */
class SimulacionTest extends TestCase
{
    use Simulando;

    #[DataProvider('mesas')]
    public function test_toda_partida_termina_con_un_solo_ganador_que_llego_justo_a_los_puntos(int $asientos, int $puntos): void
    {
        foreach (range(1, 60) as $semilla) {
            $partida = $this->simular($semilla, $asientos, $puntos);
            $tanteo = $partida->tanteo();
            $ganador = $partida->ganador();

            $this->assertSame(Fase::Terminada, $partida->fase());
            $this->assertSame($puntos, $tanteo[$ganador], "Semilla {$semilla}.");
            $this->assertLessThan($puntos, $tanteo[1 - $ganador], "Semilla {$semilla}.");
        }
    }

    #[DataProvider('mesas')]
    public function test_en_cada_paso_el_tanteo_no_baja_y_puede_actuar_un_solo_lado(int $asientos, int $puntos): void
    {
        foreach (range(1, 25) as $semilla) {
            $this->simular($semilla, $asientos, $puntos, function (Partida $partida, Partida $anterior) use ($asientos, $semilla): void {
                $this->assertGreaterThanOrEqual($anterior->tanteo()[0], $partida->tanteo()[0], "Semilla {$semilla}.");
                $this->assertGreaterThanOrEqual($anterior->tanteo()[1], $partida->tanteo()[1], "Semilla {$semilla}.");

                $pueden = array_values(array_filter(range(0, $asientos - 1), fn (int $asiento) => $partida->accionesPara($asiento) !== []));
                $equipos = array_unique(array_map($partida->mesa()->equipoDe(...), $pueden));

                if ($partida->fase() !== Fase::Jugando) {
                    $this->assertSame([], $pueden, "Semilla {$semilla}: con la mano cerrada nadie juega.");

                    return;
                }

                $this->assertCount(1, $equipos, "Semilla {$semilla}: tienen que poder actuar jugadores de un solo equipo.");

                if ($partida->vistaPara()['pendiente'] === null) {
                    $this->assertSame([$partida->turno()], $pueden, "Semilla {$semilla}: sin cantos pendientes juega solo el del turno.");
                }
            });
        }
    }

    /**
     * Se prueba todo lo que se le podría mandar al motor (las 40 cartas y cada canto) desde cada asiento,
     * en cada momento de varias partidas. Lo que no figura como válido tiene que rechazarse con un motivo.
     */
    #[DataProvider('mesas')]
    public function test_todo_lo_que_no_es_valido_se_rechaza_con_un_motivo_y_no_cambia_nada(int $asientos, int $puntos): void
    {
        $universo = array_map(Accion::jugar(...), Mazo::completo());

        foreach (TipoDeAccion::cases() as $tipo) {
            if ($tipo !== TipoDeAccion::Jugar) {
                $universo[] = Accion::de($tipo);
            }
        }

        $rechazos = 0;

        foreach (range(1, 3) as $semilla) {
            $this->simular($semilla, $asientos, $puntos, function (Partida $partida) use ($asientos, $universo, &$rechazos): void {
                $foto = $partida->aArray();

                foreach (range(0, $asientos - 1) as $asiento) {
                    $validas = array_map(fn (Accion $accion) => $accion->aArray(), $partida->accionesPara($asiento));

                    foreach ($universo as $accion) {
                        if (in_array($accion->aArray(), $validas, true)) {
                            continue;
                        }

                        try {
                            $partida->aplicar($asiento, $accion);
                            $this->fail('El motor aceptó una acción que no figuraba como válida: '.json_encode($accion->aArray()));
                        } catch (AccionInvalida $rechazo) {
                            $this->assertNotSame('', trim($rechazo->getMessage()));
                            $rechazos++;
                        }
                    }
                }

                $this->assertSame($foto, $partida->aArray(), 'Un rechazo no puede dejar rastro.');
            });
        }

        $this->assertGreaterThan(1000, $rechazos);
    }

    #[DataProvider('mesas')]
    public function test_la_misma_semilla_juega_siempre_la_misma_partida(int $asientos, int $puntos): void
    {
        $this->assertSame(
            $this->simular(2026, $asientos, $puntos)->aArray(),
            $this->simular(2026, $asientos, $puntos)->aArray(),
        );
    }

    /**
     * Lo que va a hacer M3: guardar cada reparto y cada acción, y reconstruir la partida aplicándolos de nuevo.
     */
    #[DataProvider('mesas')]
    public function test_una_partida_se_reconstruye_igual_desde_sus_repartos_y_sus_acciones(int $asientos, int $puntos): void
    {
        $azar = Azar::deSemilla(77);
        $mano = $azar->entero(0, $asientos - 1);
        $original = Partida::nueva($asientos, $mano, $puntos);
        $eventos = [];

        while ($original->fase() !== Fase::Terminada) {
            if ($original->fase() === Fase::PorRepartir) {
                $mazo = array_map(fn (Carta $carta) => $carta->id(), Mazo::mezcladoCon($azar));
                $eventos[] = ['reparto' => $mazo];
                $original = $original->repartir(array_map(Carta::de(...), $mazo));

                continue;
            }

            $asiento = $this->aQuienLeToca($original, $asientos);
            $acciones = $original->accionesPara($asiento);
            $accion = $acciones[$azar->entero(0, count($acciones) - 1)];

            $eventos[] = ['asiento' => $asiento, 'accion' => $accion->aArray()];
            $original = $original->aplicar($asiento, $accion);
        }

        // Los eventos pasan por JSON, como cuando se guardan en la base.
        $guardados = json_decode(json_encode($eventos, JSON_THROW_ON_ERROR), true);
        $reconstruida = Partida::nueva($asientos, $mano, $puntos);

        foreach ($guardados as $evento) {
            $reconstruida = isset($evento['reparto'])
                ? $reconstruida->repartir(array_map(Carta::de(...), $evento['reparto']))
                : $reconstruida->aplicar($evento['asiento'], Accion::desdeArray($evento['accion']));
        }

        $this->assertSame($original->aArray(), $reconstruida->aArray());
    }

    /**
     * Para saber que el azar llega a todos los rincones del reglamento y no solo a tirar cartas.
     */
    public function test_las_partidas_simuladas_pasan_por_todos_los_cantos(): void
    {
        $vistos = [];

        foreach ([2, 4] as $asientos) {
            foreach (range(1, 150) as $semilla) {
                $this->simular($semilla, $asientos, enCadaPaso: function (Partida $partida) use (&$vistos): void {
                    foreach ($partida->hechos() as $hecho) {
                        $vistos[self::clave($hecho)] = true;
                    }
                });
            }
        }

        $esperados = [
            'envido', 'real_envido', 'falta_envido', 'flor', 'truco', 'retruco', 'vale_cuatro', 'mazo', 'baza',
            'tantos', 'envido_no_querido', 'envido_no_jugado', 'mano', 'mano_cerrada', 'partida_terminada',
        ];

        foreach ($esperados as $clave) {
            $this->assertArrayHasKey($clave, $vistos, "Ninguna partida simulada pasó por [{$clave}].");
        }
    }

    /**
     * La contraflor necesita flor en los dos equipos, y repartiendo al azar casi nunca sale.
     * Acá todos los asientos reciben flor y la mano se juega al azar desde tanteos distintos.
     *
     * @param  list<list<string>>  $manos
     */
    #[DataProvider('manosConFlorParaTodos')]
    public function test_manos_con_flor_en_todos_los_asientos_jugadas_al_azar(array $manos): void
    {
        $vistos = [];

        foreach (range(1, 120) as $semilla) {
            $azar = Azar::deSemilla($semilla);
            $partida = Partida::armada(
                array_map(fn (array $ids) => array_map(Carta::de(...), $ids), $manos),
                mano: $azar->entero(0, count($manos) - 1),
                tanteo: [$azar->entero(0, 29), $azar->entero(0, 29)],
            );

            while ($partida->fase() === Fase::Jugando) {
                $anterior = $partida->tanteo();
                $partida = $this->pasoAlAzar($partida, $azar, "Semilla {$semilla}");

                $this->assertLessThanOrEqual(30, max($partida->tanteo()));
                $this->assertGreaterThanOrEqual(array_sum($anterior), array_sum($partida->tanteo()));

                foreach ($partida->hechos() as $hecho) {
                    $vistos[self::clave($hecho)] = true;
                }
            }
        }

        foreach (['flor', 'contraflor', 'contraflor_al_resto', 'contraflor_no_querida', 'tantos'] as $clave) {
            $this->assertArrayHasKey($clave, $vistos, "Ninguna mano pasó por [{$clave}].");
        }
    }

    public static function manosConFlorParaTodos(): array
    {
        return [
            'mano a mano' => [[['7-espada', '6-espada', '2-espada'], ['5-copa', '4-copa', '12-copa']]],
            'de a cuatro' => [[
                ['7-espada', '6-espada', '2-espada'], ['5-copa', '4-copa', '12-copa'],
                ['7-oro', '5-oro', '10-oro'], ['7-basto', '4-basto', '11-basto'],
            ]],
        ];
    }

    public static function mesas(): array
    {
        return [
            'mano a mano a 30' => [2, 30],
            'mano a mano a 15' => [2, 15],
            'de a cuatro a 30' => [4, 30],
        ];
    }

    /**
     * El nombre con el que se cuenta un hecho: el canto que se dijo, el concepto de los puntos o el tipo.
     *
     * @param  array<string, mixed>  $hecho
     */
    private static function clave(array $hecho): string
    {
        return match ($hecho['tipo']) {
            'canto' => $hecho['canto'],
            'puntos' => $hecho['concepto'],
            default => $hecho['tipo'],
        };
    }

    private function aQuienLeToca(Partida $partida, int $asientos): int
    {
        foreach (range(0, $asientos - 1) as $asiento) {
            if ($partida->accionesPara($asiento) !== []) {
                return $asiento;
            }
        }

        $this->fail('La mano está en juego y nadie puede hacer nada.');
    }
}
