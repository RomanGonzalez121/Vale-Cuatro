<?php

namespace Tests\Motor;

use App\Motor\Fase;
use App\Motor\Partida;
use PHPUnit\Framework\TestCase;

class FlorTest extends TestCase
{
    use Jugando;

    /** Flor de 35. */
    private const FLOR_ALTA = ['7-espada', '6-espada', '2-espada'];

    /** Flor de 29. */
    private const FLOR_BAJA = ['5-copa', '4-copa', '12-copa'];

    private const SIN_FLOR = ['3-basto', '10-oro', '4-oro'];

    public function test_la_flor_sola_vale_tres(): void
    {
        $partida = $this->jugar($this->mano(self::FLOR_ALTA, self::SIN_FLOR), '0 flor', '0 2-espada', '1 4-oro');

        $this->assertSame([3, 0], $partida->tanteo());
    }

    public function test_los_tres_puntos_caen_cuando_el_rival_ya_no_puede_contestar(): void
    {
        $partida = $this->jugar($this->mano(self::FLOR_ALTA, self::SIN_FLOR), '0 flor');
        $this->assertSame([0, 0], $partida->tanteo(), 'El rival todavía no jugó: podría cantar contraflor.');

        $partida = $this->jugar($partida, '0 2-espada');
        $this->assertSame([0, 0], $partida->tanteo());

        $partida = $this->jugar($partida, '1 4-oro');
        $this->assertSame([3, 0], $partida->tanteo(), 'Jugó su primera carta: ya no puede contestar.');
    }

    public function test_los_puntos_caen_en_el_mismo_momento_tenga_o_no_flor_el_rival(): void
    {
        // Si no fuera así, el que cantó sabría por el momento de los puntos que el rival calló una flor.
        foreach ([self::SIN_FLOR, self::FLOR_BAJA] as $rival) {
            $partida = $this->jugar($this->mano(self::FLOR_ALTA, $rival), '0 flor', '0 2-espada');

            $this->assertSame([0, 0], $partida->tanteo());
            $this->assertSame([3, 0], $this->jugar($partida, "1 {$rival[0]}")->tanteo());
        }
    }

    public function test_si_el_rival_ya_jugo_la_flor_se_anota_al_cantarla(): void
    {
        $partida = $this->jugar($this->mano(self::SIN_FLOR, self::FLOR_ALTA), '0 4-oro', '1 flor');

        $this->assertSame([0, 3], $partida->tanteo());
    }

    public function test_no_se_canta_flor_sin_tenerla(): void
    {
        $partida = $this->mano(self::SIN_FLOR, self::FLOR_ALTA);

        $this->assertSame('No tenés flor: hacen falta tres cartas del mismo palo.', $this->rechazo($partida, '0 flor'));
        $this->assertNotContains('flor', $this->opciones($partida, 0));
    }

    public function test_la_flor_se_pierde_si_no_se_canta_antes_de_la_primera_carta(): void
    {
        // El 0 gana la primera baza y vuelve a tener el turno, pero ya jugó su primera carta.
        $partida = $this->jugar($this->mano(self::FLOR_ALTA, self::SIN_FLOR), '0 2-espada', '1 4-oro');

        $this->assertSame('La flor se canta antes de jugar tu primera carta.', $this->rechazo($partida, '0 flor'));
    }

    public function test_la_flor_es_opcional_y_callada_se_juega_el_envido_con_las_dos_mejores(): void
    {
        $partida = $this->jugar($this->mano(self::FLOR_ALTA, self::SIN_FLOR), '0 envido', '1 quiero');

        $this->assertSame([['asiento' => 0, 'tanto' => 33], ['asiento' => 1, 'tanto' => null]], $partida->aArray()['envido']['tantos']);
        $this->assertSame([2, 0], $partida->tanteo());
    }

    public function test_la_flor_no_se_canta_dos_veces(): void
    {
        $partida = $this->jugar($this->mano(self::FLOR_ALTA, self::SIN_FLOR), '0 flor');

        $this->assertSame('Ya cantaste tu flor.', $this->rechazo($partida, '0 flor'));
    }

    public function test_la_flor_anula_el_envido_que_estaba_cantado(): void
    {
        $partida = $this->jugar($this->mano(self::SIN_FLOR, self::FLOR_ALTA), '0 envido', '1 flor');

        $this->assertSame('anulado', $partida->aArray()['envido']['estado']);
        $this->assertSame(0, $partida->turno(), 'El turno vuelve a quien había cantado el envido.');
        $this->assertContains('3-basto', $this->opciones($partida, 0));

        $partida = $this->jugar($partida, '0 3-basto');

        $this->assertSame([0, 3], $partida->tanteo(), 'Suma la flor y nada del envido.');
    }

    public function test_con_flor_cantada_no_hay_envido_en_la_mano(): void
    {
        $partida = $this->jugar($this->mano(self::FLOR_ALTA, self::SIN_FLOR), '0 flor');

        $this->assertSame('Con flor cantada no hay envido en esta mano.', $this->rechazo($partida, '0 envido'));

        $partida = $this->jugar($partida, '0 2-espada');

        $this->assertSame('Con flor cantada no hay envido en esta mano.', $this->rechazo($partida, '1 envido'));
    }

    public function test_despues_de_jugado_el_envido_ya_no_se_canta_flor(): void
    {
        $partida = $this->jugar($this->mano(self::FLOR_ALTA, self::SIN_FLOR), '0 envido', '1 quiero');

        $this->assertSame('El envido ya se jugó: la flor se canta antes.', $this->rechazo($partida, '0 flor'));
    }

    public function test_contestar_solo_flor_no_existe(): void
    {
        $partida = $this->jugar($this->mano(self::FLOR_ALTA, self::FLOR_BAJA), '0 flor', '0 2-espada');

        $this->assertSame('Contestar solo "Flor" no existe: se canta contraflor o se calla.', $this->rechazo($partida, '1 flor'));
        $this->assertSame(
            ['5-copa', '4-copa', '12-copa', 'contraflor', 'contraflor_al_resto', 'truco', 'mazo'],
            $this->opciones($partida, 1),
        );
    }

    public function test_quien_tiene_flor_y_le_cantan_flor_puede_callarla(): void
    {
        $partida = $this->jugar($this->mano(self::FLOR_ALTA, self::FLOR_BAJA), '0 flor', '0 2-espada', '1 5-copa');

        $this->assertSame([3, 0], $partida->tanteo());
        $this->assertSame('La flor se canta antes de jugar tu primera carta.', $this->rechazo($this->jugar($partida, '0 6-espada'), '1 contraflor'));
    }

    public function test_la_contraflor_no_existe_si_el_rival_no_canto_flor(): void
    {
        $partida = $this->mano(self::FLOR_ALTA, self::FLOR_BAJA);

        $this->assertSame('La contraflor contesta una flor del rival, y nadie la cantó.', $this->rechazo($partida, '0 contraflor'));
    }

    public function test_contraflor_querida_vale_seis_para_la_mejor_flor(): void
    {
        $partida = $this->jugar($this->enContraflor(), '0 quiero');

        $this->assertSame([6, 0], $partida->tanteo());
        $this->assertSame([['asiento' => 0, 'tanto' => 35], ['asiento' => 1, 'tanto' => null]], $partida->aArray()['flor']['tantos']);
        $this->assertSame([['equipo' => 0, 'puntos' => 6, 'concepto' => 'contraflor']], $partida->aArray()['anotado']);
    }

    public function test_contraflor_no_querida_vale_cuatro_para_quien_la_canto(): void
    {
        $partida = $this->jugar($this->enContraflor(), '0 no_quiero');

        $this->assertSame([0, 4], $partida->tanteo());
        $this->assertSame(Fase::Jugando, $partida->fase(), 'La mano sigue.');
    }

    public function test_contraflor_al_resto_querida_vale_lo_que_le_falta_al_puntero(): void
    {
        $partida = $this->mano(self::FLOR_ALTA, self::FLOR_BAJA, tanteo: [10, 18]);
        $partida = $this->jugar($partida, '0 flor', '0 2-espada', '1 contraflor_al_resto', '0 quiero');

        $this->assertSame([22, 18], $partida->tanteo());
    }

    public function test_contraflor_al_resto_no_querida_vale_seis(): void
    {
        $partida = $this->mano(self::FLOR_ALTA, self::FLOR_BAJA);
        $partida = $this->jugar($partida, '0 flor', '0 2-espada', '1 contraflor_al_resto', '0 no_quiero');

        $this->assertSame([0, 6], $partida->tanteo());
    }

    public function test_la_contraflor_se_puede_subir_al_resto(): void
    {
        $partida = $this->enContraflor();

        $this->assertSame(['contraflor_al_resto', 'quiero', 'no_quiero', 'mazo'], $this->opciones($partida, 0));

        $subida = $this->jugar($partida, '0 contraflor_al_resto');

        $this->assertSame(['quiero', 'no_quiero', 'mazo'], $this->opciones($subida, 1));
        $this->assertSame([6, 0], $this->jugar($subida, '1 no_quiero')->tanteo());
        $this->assertSame([30, 0], $this->jugar($subida, '1 quiero')->tanteo());
    }

    public function test_si_las_flores_empatan_gana_el_mano(): void
    {
        $igual = ['7-oro', '6-oro', '2-oro'];

        $partida = $this->jugar($this->mano(self::FLOR_ALTA, $igual), '0 flor', '0 2-espada', '1 contraflor', '0 quiero');
        $this->assertSame([6, 0], $partida->tanteo());

        $partida = $this->jugar($this->mano(self::FLOR_ALTA, $igual, mano: 1), '1 flor', '1 2-oro', '0 contraflor', '1 quiero');
        $this->assertSame([0, 6], $partida->tanteo());
    }

    public function test_con_una_contraflor_sin_contestar_no_se_hace_otra_cosa(): void
    {
        $partida = $this->enContraflor();

        $this->assertSame('Antes de jugar hay que contestar el canto.', $this->rechazo($partida, '0 6-espada'));
        $this->assertSame('Antes hay que contestar el canto que está pendiente.', $this->rechazo($partida, '0 truco'));
        $this->assertSame('Hay una contraflor sin contestar: se quiere, no se quiere o se sube al resto.', $this->rechazo($partida, '0 contraflor'));
        $this->assertSame([], $partida->accionesPara(1));
    }

    public function test_resuelta_la_contraflor_la_mano_sigue_donde_estaba(): void
    {
        $partida = $this->jugar($this->enContraflor(), '0 quiero');

        $this->assertSame(1, $partida->turno());
        $this->assertSame(['5-copa', '4-copa', '12-copa', 'truco', 'mazo'], $this->opciones($partida, 1));
    }

    public function test_la_flor_tambien_esta_primero_que_el_truco(): void
    {
        $partida = $this->jugar($this->mano(self::SIN_FLOR, self::FLOR_ALTA), '0 truco', '1 flor');

        // El truco sigue esperando respuesta.
        $this->assertSame(['retruco', 'quiero', 'no_quiero', 'mazo'], $this->opciones($partida, 1));

        $partida = $this->jugar($partida, '1 quiero', '0 3-basto');

        $this->assertSame([0, 3], $partida->tanteo());
    }

    public function test_querido_el_truco_ya_no_se_canta_flor(): void
    {
        $partida = $this->jugar($this->mano(self::SIN_FLOR, self::FLOR_ALTA), '0 truco', '1 quiero', '0 3-basto');

        $this->assertSame('Querido el truco, ya no se canta flor en esta mano.', $this->rechazo($partida, '1 flor'));
    }

    public function test_la_flor_que_te_cantaron_se_contesta_con_contraflor_aunque_el_truco_ya_este_querido(): void
    {
        // El 0 canta truco, el 1 le contesta flor y después quiere el truco. Al 0 le vuelve el turno
        // sin haber jugado su primera carta: todavía puede contestar esa flor.
        $partida = $this->jugar($this->mano(self::FLOR_ALTA, self::FLOR_BAJA), '0 truco', '1 flor', '1 quiero');

        $this->assertSame([0, 0], $partida->tanteo(), 'La flor todavía no suma: el 0 puede contestarla.');
        $this->assertContains('contraflor', $this->opciones($partida, 0));
        $this->assertNotContains('flor', $this->opciones($partida, 0));

        $partida = $this->jugar($partida, '0 contraflor', '1 quiero');

        $this->assertSame([6, 0], $partida->tanteo());
    }

    public function test_los_puntos_de_la_flor_se_anotan_antes_que_los_del_truco(): void
    {
        $partida = $this->jugar($this->mano(self::FLOR_ALTA, self::SIN_FLOR), '0 flor', '0 truco', '1 no_quiero');

        $this->assertSame(
            [['equipo' => 0, 'puntos' => 3, 'concepto' => 'flor'], ['equipo' => 0, 'puntos' => 1, 'concepto' => 'truco']],
            $partida->cierre()['anotado'],
        );
    }

    public function test_la_flor_puede_cerrar_la_partida(): void
    {
        $partida = $this->mano(self::FLOR_ALTA, self::SIN_FLOR, tanteo: [27, 29]);
        $partida = $this->jugar($partida, '0 flor', '0 2-espada', '1 4-oro');

        $this->assertSame(Fase::Terminada, $partida->fase());
        $this->assertSame(0, $partida->ganador());
        $this->assertSame('partida', $partida->cierre()['motivo']);
    }

    public function test_si_la_flor_cierra_la_partida_al_irse_al_mazo_el_cierre_nombra_a_quien_gano_la_partida(): void
    {
        // El 0 canta flor y se va: pierde la mano, pero los 3 de la flor se anotan antes y le dan la partida.
        $partida = $this->jugar($this->mano(self::FLOR_ALTA, self::SIN_FLOR, tanteo: [28, 5]), '0 flor', '0 mazo');

        $this->assertSame(Fase::Terminada, $partida->fase());
        $this->assertSame(0, $partida->ganador());
        $this->assertSame([30, 5], $partida->tanteo());
        $this->assertSame(0, $partida->cierre()['ganador']);
        $this->assertSame('partida', $partida->cierre()['motivo']);
        $this->assertSame([['equipo' => 0, 'puntos' => 3, 'concepto' => 'flor']], $partida->cierre()['anotado']);
    }

    public function test_la_flor_del_rival_se_contesta_en_el_turno_propio_y_si_la_mano_se_cierra_antes_se_pierde(): void
    {
        // El 0 tiene flor y elige cantar truco. El 1 contesta flor y no quiere: la mano se cierra
        // sin que al 0 le vuelva el turno, así que no llega a cantar contraflor.
        $partida = $this->jugar($this->mano(self::FLOR_ALTA, self::FLOR_BAJA), '0 truco', '1 flor', '1 no_quiero');

        $this->assertSame([1, 3], $partida->tanteo());
        $this->assertSame(
            [['equipo' => 1, 'puntos' => 3, 'concepto' => 'flor'], ['equipo' => 0, 'puntos' => 1, 'concepto' => 'truco']],
            $partida->cierre()['anotado'],
        );
    }

    public function test_irse_al_mazo_en_primera_con_flor_cantada_no_suma_el_punto_del_envido(): void
    {
        $partida = $this->jugar($this->mano(self::FLOR_ALTA, self::SIN_FLOR), '0 flor', '0 mazo');

        // La flor igual suma: 3 para quien la cantó y 1 de la mano para el rival.
        $this->assertSame([3, 1], $partida->tanteo());
    }

    public function test_irse_al_mazo_con_la_contraflor_sin_contestar_vale_como_no_quererla_mas_la_mano(): void
    {
        $partida = $this->jugar($this->enContraflor(), '0 mazo');

        $this->assertSame([0, 5], $partida->tanteo());
        $this->assertSame(
            [['equipo' => 1, 'puntos' => 4, 'concepto' => 'contraflor_no_querida'], ['equipo' => 1, 'puntos' => 1, 'concepto' => 'mano']],
            $partida->cierre()['anotado'],
        );
    }

    public function test_la_flor_que_suma_se_muestra_al_cerrar_la_mano(): void
    {
        $partida = $this->jugar($this->mano(self::FLOR_ALTA, self::SIN_FLOR), '0 flor', '0 mazo');
        $this->assertSame([0 => self::FLOR_ALTA], $partida->cierre()['mostradas']);

        $partida = $this->jugar($this->enContraflor(), '0 quiero', '1 mazo');
        $this->assertSame([0 => self::FLOR_ALTA], $partida->cierre()['mostradas'], 'En la contraflor muestra solo quien la ganó.');

        $partida = $this->jugar($this->enContraflor(), '0 no_quiero', '1 mazo');
        $this->assertSame([], $partida->cierre()['mostradas'], 'No querida, nadie muestra.');
    }

    /**
     * El asiento 0 cantó flor y jugó, y el 1 le contestó contraflor: le toca responder al 0.
     */
    private function enContraflor(): Partida
    {
        return $this->jugar($this->mano(self::FLOR_ALTA, self::FLOR_BAJA), '0 flor', '0 2-espada', '1 contraflor');
    }

    /**
     * @param  list<string>  $cero
     * @param  list<string>  $uno
     * @param  array{0: int, 1: int}  $tanteo
     */
    private function mano(array $cero, array $uno, int $mano = 0, array $tanteo = [0, 0]): Partida
    {
        return $this->armada([$cero, $uno], $mano, $tanteo);
    }
}
