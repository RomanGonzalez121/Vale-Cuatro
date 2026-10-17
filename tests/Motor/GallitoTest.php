<?php

namespace Tests\Motor;

use App\Motor\Fase;
use App\Motor\Partida;
use PHPUnit\Framework\TestCase;

/**
 * El gallito: tres jugadores, uno contra dos. En cada mano el gallo es el mano y juega solo
 * (equipo 0) contra la pareja (equipo 1). Cada uno suma aparte. Reglamento fijado por Román
 * el 17 de octubre de 2026.
 */
class GallitoTest extends TestCase
{
    use Jugando;

    /** El gallo (asiento 0) tiene 27 de envido, el asiento 1 tiene 31 y el 2 tiene 33. */
    private const PARA_EL_ENVIDO = [
        ['7-oro', '12-oro', '4-basto'],
        ['6-copa', '5-copa', '1-espada'],
        ['7-espada', '6-espada', '3-oro'],
    ];

    public function test_el_gallo_es_el_mano_y_juega_solo_contra_los_otros_dos(): void
    {
        $partida = Partida::nueva(asientos: 3, mano: 1);

        $this->assertSame(1, $partida->mesa()->gallo);
        $this->assertSame([1], $partida->mesa()->asientosDe(0));
        $this->assertSame([0, 2], $partida->mesa()->asientosDe(1));
        $this->assertSame([0, 0, 0], $partida->tanteo(), 'Cada uno suma aparte.');
    }

    public function test_se_reparten_tres_cartas_a_cada_uno_empezando_por_el_gallo(): void
    {
        $mazo = $this->cartas([
            '1-espada', '2-espada', '3-espada', '4-espada', '5-espada', '6-espada', '7-espada', '10-espada', '11-espada',
        ]);

        $partida = Partida::nueva(asientos: 3, mano: 2)->repartir($mazo);

        $this->assertSame(['1-espada', '4-espada', '7-espada'], $partida->aArray()['repartidas'][2]);
        $this->assertSame(['2-espada', '5-espada', '10-espada'], $partida->aArray()['repartidas'][0]);
        $this->assertSame(['3-espada', '6-espada', '11-espada'], $partida->aArray()['repartidas'][1]);
        $this->assertSame(2, $partida->turno(), 'Sale el gallo.');
    }

    public function test_el_gallo_pasa_al_asiento_siguiente_en_cada_mano(): void
    {
        $partida = $this->jugar($this->armada(self::PARA_EL_ENVIDO, mano: 1), '1 mazo');

        $this->assertSame(2, $partida->mano());
        $this->assertSame(2, $partida->mesa()->gallo);
        $this->assertSame([0, 1], $partida->mesa()->asientosDe(1), 'La pareja cambia con el gallo.');
    }

    public function test_cuando_gana_la_pareja_los_dos_suman_todo(): void
    {
        // El gallo se va al mazo en la primera sin envido: la pareja gana la mano y el envido que no se jugó.
        $partida = $this->jugar($this->armada(self::PARA_EL_ENVIDO), '0 mazo');

        $this->assertSame([0, 2, 2], $partida->tanteo());
        $this->assertSame(1, $partida->cierre()['ganador']);
        $this->assertSame([1, 2], $partida->hechos()[1]['asientos'], 'El punto se anota a los dos de la pareja.');
    }

    public function test_cuando_gana_el_gallo_suma_solo_el(): void
    {
        $partida = $this->jugar($this->armada(self::PARA_EL_ENVIDO), '0 7-oro', '1 mazo', '2 mazo');

        $this->assertSame([2, 0, 0], $partida->tanteo());
        $this->assertSame(0, $partida->cierre()['ganador']);
    }

    public function test_la_baza_la_gana_la_carta_mas_alta_y_abre_la_siguiente_quien_la_gano(): void
    {
        $partida = $this->armada([
            ['4-copa', '5-oro', '6-basto'],
            ['3-basto', '6-copa', '7-basto'],
            ['12-oro', '11-basto', '10-copa'],
        ]);

        $partida = $this->jugar($partida, '0 4-copa', '1 3-basto', '2 12-oro');

        $this->assertCount(3, $partida->aArray()['bazas'][0]['jugadas']);
        $this->assertSame(1, $partida->aArray()['bazas'][0]['ganador']);
        $this->assertSame(1, $partida->turno());

        // Y se sigue en ronda desde ahí.
        $partida = $this->jugar($partida, '1 6-copa');
        $this->assertSame(2, $partida->turno());
    }

    public function test_si_la_mas_alta_la_comparten_los_dos_de_la_pareja_no_es_parda(): void
    {
        $partida = $this->armada([
            ['4-copa', '5-oro', '6-basto'],
            ['3-basto', '6-copa', '7-basto'],
            ['3-copa', '12-oro', '11-basto'],
        ]);

        $partida = $this->jugar($partida, '0 4-copa', '1 3-basto', '2 3-copa');

        $this->assertSame(1, $partida->aArray()['bazas'][0]['ganador'], 'Gana la pareja, con la que salió primero.');
    }

    public function test_si_el_gallo_empata_con_la_pareja_es_parda_y_despues_sale_el_gallo(): void
    {
        $partida = $this->armada([
            ['3-espada', '4-copa', '5-oro'],
            ['3-basto', '6-copa', '7-basto'],
            ['3-copa', '12-oro', '11-basto'],
        ]);

        $partida = $this->jugar($partida, '0 3-espada', '1 3-basto', '2 3-copa');
        $this->assertNull($partida->aArray()['bazas'][0]['ganador']);
        $this->assertSame(0, $partida->turno());

        // Parda la primera: gana quien gana la segunda.
        $partida = $this->jugar($partida, '0 4-copa', '1 6-copa', '2 12-oro');
        $this->assertSame(1, $partida->cierre()['ganador']);
        $this->assertSame([0, 1, 1], $partida->tanteo());
    }

    public function test_pardas_las_tres_gana_el_gallo(): void
    {
        $partida = $this->armada([
            ['3-espada', '2-oro', '12-copa'],
            ['3-basto', '2-copa', '12-oro'],
            ['4-copa', '5-oro', '6-basto'],
        ]);

        $partida = $this->jugar($partida,
            '0 3-espada', '1 3-basto', '2 4-copa',
            '0 2-oro', '1 2-copa', '2 5-oro',
            '0 12-copa', '1 12-oro', '2 6-basto',
        );

        $this->assertSame(0, $partida->cierre()['ganador']);
        $this->assertSame([1, 0, 0], $partida->tanteo());
    }

    public function test_el_envido_lo_juega_la_pareja_con_el_mejor_tanto_de_los_dos(): void
    {
        $partida = $this->jugar($this->armada(self::PARA_EL_ENVIDO), '0 envido', '1 quiero');

        $this->assertSame(
            [['asiento' => 0, 'tanto' => 27], ['asiento' => 1, 'tanto' => 31], ['asiento' => 2, 'tanto' => 33]],
            $partida->aArray()['envido']['tantos'],
        );
        $this->assertSame([0, 2, 2], $partida->tanteo());
    }

    public function test_si_va_ganando_el_companero_no_hace_falta_cantar_el_tanto(): void
    {
        $manos = self::PARA_EL_ENVIDO;
        $manos[2] = ['4-espada', '12-espada', '3-oro'];

        $partida = $this->jugar($this->armada($manos), '0 envido', '1 quiero');

        $this->assertSame([['asiento' => 0, 'tanto' => 27], ['asiento' => 1, 'tanto' => 31]], $partida->aArray()['envido']['tantos']);
    }

    public function test_con_tantos_iguales_gana_el_gallo_porque_es_el_mano(): void
    {
        $manos = self::PARA_EL_ENVIDO;
        $manos[1] = ['6-copa', '1-copa', '1-espada'];
        $manos[2] = ['4-espada', '12-espada', '3-oro'];

        $partida = $this->jugar($this->armada($manos), '0 envido', '2 quiero');

        $this->assertSame(0, $partida->aArray()['envido']['ganador']);
        $this->assertSame([2, 0, 0], $partida->tanteo());
    }

    public function test_cada_flor_de_la_pareja_suma_tres_a_los_dos(): void
    {
        $partida = $this->armada([
            ['1-espada', '2-oro', '3-basto'],
            ['5-copa', '4-copa', '12-copa'],
            ['7-oro', '5-oro', '10-oro'],
        ]);

        // Las flores se anotan cuando el gallo ya no las puede contestar: jugó su primera carta.
        $partida = $this->jugar($partida, '0 1-espada', '1 flor', '1 5-copa', '2 flor');

        $this->assertSame([0, 6, 6], $partida->tanteo());
    }

    public function test_la_contraflor_compara_la_flor_del_gallo_con_la_de_la_pareja(): void
    {
        $partida = $this->armada([
            ['5-copa', '4-copa', '12-copa'],      // 29
            ['7-espada', '6-espada', '2-espada'], // 35
            ['1-basto', '2-oro', '3-oro'],
        ]);

        $partida = $this->jugar($partida, '0 flor', '0 5-copa', '1 contraflor', '0 quiero');

        $this->assertSame(1, $partida->aArray()['flor']['ganador']);
        $this->assertSame([0, 6, 6], $partida->tanteo());
    }

    public function test_un_canto_a_la_pareja_lo_contesta_cualquiera_y_el_quiero_es_de_los_dos(): void
    {
        $partida = $this->jugar($this->armada(self::PARA_EL_ENVIDO), '0 truco', '2 quiero');

        $this->assertSame('No es tu turno.', $this->rechazo($partida, '1 quiero'), 'Ya contestó el compañero.');
        $this->assertSame(1, $partida->aArray()['truco']['quiero']);

        // Sube el asiento 1, que no fue el que quiso, en su turno.
        $partida = $this->jugar($partida, '0 7-oro', '1 retruco');
        $this->assertSame('retruco', $partida->hechos()[0]['canto']);
    }

    public function test_el_mazo_es_de_cada_uno_y_el_companero_sigue_solo(): void
    {
        $partida = $this->jugar($this->armada(self::PARA_EL_ENVIDO), '0 7-oro', '1 mazo');

        $this->assertSame(Fase::Jugando, $partida->fase());
        $this->assertSame(2, $partida->turno());

        // Se va el otro: la pareja pierde la mano. En la primera, sin envido, son 2 para el gallo.
        $partida = $this->jugar($partida, '2 mazo');
        $this->assertSame([2, 0, 0], $partida->tanteo());
    }

    public function test_la_falta_es_lo_que_le_falta_al_puntero_de_los_tres(): void
    {
        $partida = $this->armada(self::PARA_EL_ENVIDO, tanteo: [10, 5, 20]);

        $partida = $this->jugar($partida, '0 falta_envido', '1 quiero');

        $this->assertSame([10, 15, 30], $partida->tanteo(), 'La falta vale 10: lo que le falta al asiento 2.');
        $this->assertSame(Fase::Terminada, $partida->fase());
        $this->assertSame(2, $partida->ganador(), 'En el gallito gana un jugador, no un equipo.');
        $this->assertSame(1, $partida->cierre()['ganador'], 'La mano la cerró la pareja.');
    }

    public function test_si_los_dos_de_la_pareja_pasan_los_30_gana_el_que_quedo_mas_arriba(): void
    {
        $partida = $this->armada(self::PARA_EL_ENVIDO, tanteo: [5, 29, 28]);

        $partida = $this->jugar($partida, '0 envido', '1 quiero');

        $this->assertSame([5, 31, 30], $partida->tanteo(), 'Sin tope en 30: hace falta compararlos.');
        $this->assertSame(1, $partida->ganador());
    }

    public function test_si_los_dos_de_la_pareja_pasan_iguales_se_sigue_jugando(): void
    {
        $partida = $this->armada(self::PARA_EL_ENVIDO, tanteo: [5, 28, 28]);

        $partida = $this->jugar($partida, '0 envido', '1 quiero');
        $this->assertSame([5, 30, 30], $partida->tanteo());
        $this->assertSame(Fase::Jugando, $partida->fase());
        $this->assertNull($partida->ganador());

        // Se va el gallo: la pareja suma la mano y siguen iguales.
        $partida = $this->jugar($partida, '0 mazo');
        $this->assertSame([5, 31, 31], $partida->tanteo());
        $this->assertSame(Fase::PorRepartir, $partida->fase());

        // En la mano siguiente el gallo es el asiento 1 y el 2 está en la pareja: el primero que suma, gana.
        $partida = $this->jugar($partida->conManos(array_map($this->cartas(...), [
            ['4-copa', '5-oro', '6-basto'],
            ['3-basto', '6-copa', '7-basto'],
            ['12-oro', '11-basto', '10-copa'],
        ])), '1 mazo');

        $this->assertSame([6, 31, 32], $partida->tanteo(), 'Termina con el primer punto: el del envido que no se jugó.');
        $this->assertSame(2, $partida->ganador());
    }
}
