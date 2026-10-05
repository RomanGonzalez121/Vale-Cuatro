<?php

namespace Tests\Motor;

use App\Motor\Fase;
use App\Motor\Partida;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EnvidoTest extends TestCase
{
    use Jugando;

    /** Tanto 33. */
    private const TREINTA_Y_TRES = ['7-oro', '6-oro', '1-espada'];

    /** Tanto 25. */
    private const VEINTICINCO = ['12-copa', '5-copa', '3-basto'];

    /**
     * La tabla de envido jugada en la mesa: los cantos se alternan empezando por el asiento 0,
     * que tiene 33 contra 25. Con el tanteo 12 a 20 la falta vale 10.
     *
     * @param  list<string>  $cadena
     */
    #[DataProvider('tablaDeEnvido')]
    public function test_tabla_de_envido(array $cadena, int $querido, int $noQuerido): void
    {
        [$cantada, $contesta] = $this->cantarCadena($cadena);

        // Querido: gana el asiento 0, que tiene el mejor tanto.
        $this->assertSame([12 + $querido, 20], $this->jugar($cantada, "{$contesta} quiero")->tanteo());

        // No querido: suma quien cantó último, que es el que no contesta.
        $rechazada = $this->jugar($cantada, "{$contesta} no_quiero");

        $this->assertSame($contesta === 1 ? [12 + $noQuerido, 20] : [12, 20 + $noQuerido], $rechazada->tanteo());
        $this->assertSame(Fase::Jugando, $rechazada->fase(), 'Después del envido la mano sigue.');
    }

    public static function tablaDeEnvido(): array
    {
        return CadenasDeEnvidoTest::tablaDeEnvido();
    }

    public function test_el_mano_canta_su_tanto_y_el_otro_contesta_son_buenas_sin_revelar_el_suyo(): void
    {
        $partida = $this->jugar($this->mano(), '0 envido', '1 quiero');

        $this->assertSame(
            [['tipo' => 'respuesta', 'asiento' => 1, 'canto' => 'envido', 'quiere' => true],
                ['tipo' => 'tantos', 'canto' => 'envido', 'tantos' => [['asiento' => 0, 'tanto' => 33], ['asiento' => 1, 'tanto' => null]], 'ganador' => 0],
                ['tipo' => 'puntos', 'equipo' => 0, 'puntos' => 2, 'concepto' => 'envido', 'tanteo' => [2, 0]]],
            $partida->hechos(),
        );
    }

    public function test_si_el_otro_tiene_mas_canta_su_tanto_son_mejores(): void
    {
        $partida = $this->jugar($this->mano(mano: 1), '1 envido', '0 quiero');

        $this->assertSame([['asiento' => 1, 'tanto' => 25], ['asiento' => 0, 'tanto' => 33]], $partida->aArray()['envido']['tantos']);
        $this->assertSame([2, 0], $partida->tanteo());
    }

    public function test_si_empatan_gana_el_mano(): void
    {
        $iguales = [['7-oro', '6-oro', '1-espada'], ['7-copa', '6-copa', '3-basto']];

        foreach ([0, 1] as $mano) {
            $partida = $this->jugar($this->armada($iguales, $mano), "{$mano} envido", (1 - $mano).' quiero');

            $this->assertSame($mano, $partida->aArray()['envido']['ganador']);
            $this->assertSame(2, $partida->tanteo()[$mano]);
        }
    }

    public function test_sin_dos_cartas_del_mismo_palo_se_juega_con_la_mas_alta(): void
    {
        $partida = $this->armada([['3-espada', '11-basto', '4-copa'], ['10-oro', '12-copa', '2-basto']]);
        $partida = $this->jugar($partida, '0 envido', '1 quiero');

        $this->assertSame([['asiento' => 0, 'tanto' => 4], ['asiento' => 1, 'tanto' => null]], $partida->aArray()['envido']['tantos']);
    }

    public function test_el_envido_se_canta_antes_de_jugar_la_propia_primera_carta(): void
    {
        $partida = $this->mano();
        $this->assertContains('envido', $this->opciones($partida, 0));

        // El mano juega: él ya no puede, el otro todavía sí.
        $partida = $this->jugar($partida, '0 1-espada');
        $this->assertContains('envido', $this->opciones($partida, 1));

        $partida = $this->jugar($partida, '1 3-basto');
        $this->assertSame('El envido se canta antes de jugar tu primera carta.', $this->rechazo($partida, '0 envido'));
    }

    public function test_contestando_se_puede_subir_aunque_ya_se_haya_jugado_la_primera_carta(): void
    {
        $partida = $this->jugar($this->mano(), '0 1-espada', '1 envido');

        $this->assertSame(['envido', 'real_envido', 'falta_envido', 'quiero', 'no_quiero', 'mazo'], $this->opciones($partida, 0));
    }

    public function test_el_envido_se_canta_una_sola_vez_por_mano(): void
    {
        $partida = $this->jugar($this->mano(), '0 envido', '1 no_quiero');

        $this->assertSame('El envido ya se cantó en esta mano.', $this->rechazo($partida, '0 real_envido'));
    }

    public function test_el_envido_siempre_sube(): void
    {
        $partida = $this->jugar($this->mano(), '0 real_envido');

        $this->assertSame('El envido siempre sube: eso ya no se puede cantar.', $this->rechazo($partida, '1 envido'));
        $this->assertSame(['falta_envido', 'quiero', 'no_quiero', 'mazo'], $this->opciones($partida, 1));
    }

    public function test_un_envido_no_se_contesta_con_truco_ni_con_una_carta(): void
    {
        $partida = $this->jugar($this->mano(), '0 envido');

        $this->assertSame('Antes hay que contestar el canto que está pendiente.', $this->rechazo($partida, '1 truco'));
        $this->assertSame('Antes de jugar hay que contestar el canto.', $this->rechazo($partida, '1 3-basto'));
    }

    public function test_el_falta_envido_vale_lo_que_le_falta_al_puntero(): void
    {
        // Gana el que va perdiendo: suma lo que le faltaba al otro, no lo que le falta a él.
        $partida = $this->jugar($this->mano(tanteo: [5, 22]), '0 falta_envido', '1 quiero');

        $this->assertSame([13, 22], $partida->tanteo());
        $this->assertSame(Fase::Jugando, $partida->fase());
    }

    public function test_el_falta_envido_ganado_por_el_puntero_cierra_la_partida(): void
    {
        $partida = $this->jugar($this->mano(tanteo: [22, 5]), '0 falta_envido', '1 quiero');

        $this->assertSame([30, 5], $partida->tanteo());
        $this->assertSame(Fase::Terminada, $partida->fase());
        $this->assertSame(0, $partida->ganador());
    }

    public function test_el_envido_esta_primero(): void
    {
        $partida = $this->jugar($this->mano(), '0 truco');

        $this->assertSame(
            ['envido', 'real_envido', 'falta_envido', 'retruco', 'quiero', 'no_quiero', 'mazo'],
            $this->opciones($partida, 1),
        );

        // El truco queda en suspenso: primero se resuelve el envido.
        $partida = $this->jugar($partida, '1 envido');
        $this->assertSame(['envido', 'real_envido', 'falta_envido', 'quiero', 'no_quiero', 'mazo'], $this->opciones($partida, 0));

        $partida = $this->jugar($partida, '0 quiero');
        $this->assertSame([2, 0], $partida->tanteo());

        // Y después se contesta el truco, que sigue esperando.
        $this->assertSame(['retruco', 'quiero', 'no_quiero', 'mazo'], $this->opciones($partida, 1));
        $this->assertSame([], $partida->accionesPara(0));

        $partida = $this->jugar($partida, '1 quiero');
        $this->assertSame(0, $partida->turno());
    }

    public function test_el_envido_no_esta_primero_si_ya_jugaste_tu_primera_carta(): void
    {
        $partida = $this->jugar($this->mano(), '0 1-espada', '1 truco');

        $this->assertSame('El envido se canta antes de jugar tu primera carta.', $this->rechazo($partida, '0 envido'));
    }

    public function test_querido_el_truco_ya_no_se_canta_envido(): void
    {
        $partida = $this->jugar($this->mano(), '0 truco', '1 quiero');

        $this->assertSame('Querido el truco, ya no se canta envido en esta mano.', $this->rechazo($partida, '0 envido'));

        $partida = $this->jugar($partida, '0 1-espada');

        $this->assertSame('Querido el truco, ya no se canta envido en esta mano.', $this->rechazo($partida, '1 envido'));
    }

    public function test_subir_el_truco_tambien_es_quererlo_y_deja_afuera_al_envido(): void
    {
        $partida = $this->jugar($this->mano(), '0 truco', '1 retruco');

        $this->assertSame('Querido el truco, ya no se canta envido en esta mano.', $this->rechazo($partida, '0 envido'));
    }

    public function test_los_puntos_del_envido_se_anotan_antes_que_los_del_truco(): void
    {
        $partida = $this->jugar($this->mano(), '0 truco', '1 envido', '0 quiero', '1 no_quiero');

        $this->assertSame(
            [['equipo' => 0, 'puntos' => 2, 'concepto' => 'envido'], ['equipo' => 0, 'puntos' => 1, 'concepto' => 'truco']],
            $partida->cierre()['anotado'],
        );
    }

    public function test_si_el_envido_cierra_la_partida_el_truco_ya_no_se_juega(): void
    {
        $partida = $this->jugar($this->mano(tanteo: [28, 29]), '0 truco', '1 envido', '0 quiero');

        $this->assertSame(Fase::Terminada, $partida->fase());
        $this->assertSame(0, $partida->ganador());
        $this->assertSame([30, 29], $partida->tanteo());
        $this->assertSame('partida', $partida->cierre()['motivo']);
        $this->assertSame([], $partida->accionesPara(1));
    }

    public function test_quien_gana_el_envido_muestra_al_cerrar_las_cartas_de_su_tanto(): void
    {
        $partida = $this->jugar($this->mano(), '0 envido', '1 quiero', '0 mazo');

        $this->assertSame([0 => ['7-oro', '6-oro']], $partida->cierre()['mostradas']);
    }

    public function test_un_envido_no_querido_no_obliga_a_mostrar_nada(): void
    {
        $partida = $this->jugar($this->mano(), '0 envido', '1 no_quiero', '0 mazo');

        $this->assertSame([], $partida->cierre()['mostradas']);
    }

    public function test_irse_al_mazo_con_el_envido_sin_contestar_vale_como_no_quererlo_mas_la_mano(): void
    {
        $partida = $this->jugar($this->mano(), '0 envido', '1 mazo');

        $this->assertSame([2, 0], $partida->tanteo());
        $this->assertSame(
            [['equipo' => 0, 'puntos' => 1, 'concepto' => 'envido_no_querido'], ['equipo' => 0, 'puntos' => 1, 'concepto' => 'mano']],
            $partida->cierre()['anotado'],
        );
    }

    public function test_irse_al_mazo_con_una_cadena_sin_contestar_suma_lo_no_querido_de_la_cadena(): void
    {
        $partida = $this->jugar($this->mano(), '0 envido', '1 envido', '0 real_envido', '1 mazo');

        // Envido, envido, real envido no querido son 4, más 1 de la mano.
        $this->assertSame([5, 0], $partida->tanteo());
    }

    public function test_irse_al_mazo_con_envido_pendiente_y_truco_en_suspenso(): void
    {
        $partida = $this->jugar($this->mano(), '0 truco', '1 envido', '0 mazo');

        // 1 del envido no querido y 1 del truco que nadie llegó a querer.
        $this->assertSame([0, 2], $partida->tanteo());
    }

    public function test_despues_de_jugar_el_envido_el_mazo_en_primera_ya_no_suma_el_punto_extra(): void
    {
        $partida = $this->jugar($this->mano(), '0 envido', '1 no_quiero', '0 mazo');

        $this->assertSame([1, 1], $partida->tanteo());
    }

    /**
     * @param  array{0: int, 1: int}  $tanteo
     */
    private function mano(int $mano = 0, array $tanteo = [0, 0]): Partida
    {
        return $this->armada([self::TREINTA_Y_TRES, self::VEINTICINCO], $mano, $tanteo);
    }

    /**
     * Canta la cadena alternando asientos desde el 0 y devuelve la partida y a quién le toca contestar.
     *
     * @param  list<string>  $cadena
     * @return array{0: Partida, 1: int}
     */
    private function cantarCadena(array $cadena): array
    {
        $partida = $this->mano(tanteo: [12, 20]);

        foreach ($cadena as $i => $canto) {
            $partida = $this->jugar($partida, ($i % 2)." {$canto}");
        }

        return [$partida, count($cadena) % 2];
    }
}
