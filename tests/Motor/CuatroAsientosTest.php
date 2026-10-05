<?php

namespace Tests\Motor;

use App\Motor\Fase;
use App\Motor\Mesa;
use App\Motor\Partida;
use PHPUnit\Framework\TestCase;

/**
 * El truco de a cuatro con las reglas fijadas hasta ahora. Los asientos 0 y 2 son un equipo
 * y los asientos 1 y 3 el otro. Lo que falta fijar se completa en M19.
 */
class CuatroAsientosTest extends TestCase
{
    use Jugando;

    private const FLOR_DE_COPA = ['5-copa', '4-copa', '12-copa'];

    private const FLOR_DE_ESPADA = ['7-espada', '6-espada', '2-espada'];

    private const FLOR_DE_ORO = ['7-oro', '5-oro', '10-oro'];

    public function test_los_companeros_se_sientan_enfrentados(): void
    {
        $mesa = new Mesa(4);

        $this->assertSame([0, 2], $mesa->asientosDe(0));
        $this->assertSame([1, 3], $mesa->asientosDe(1));
        $this->assertSame([2, 3, 0, 1], $mesa->rondaDesde(2));
    }

    /**
     * Una mano entera: envido con subida y tantos en ronda, truco y retruco contestados por
     * cualquiera del equipo, un jugador que se va al mazo y una baza de tres cartas.
     */
    public function test_una_mano_de_cuatro_jugada_de_punta_a_punta(): void
    {
        $partida = $this->armada([
            ['7-oro', '6-oro', '4-basto'],       // asiento 0: 33 de envido
            ['3-espada', '12-basto', '5-copa'],  // asiento 1, el mano: 5
            ['1-espada', '2-copa', '10-copa'],   // asiento 2: 22
            ['7-copa', '6-copa', '11-oro'],      // asiento 3: 33
        ], mano: 1);

        // Sale el mano. El asiento 2 canta envido antes de su primera carta; sube el 3, que no
        // es el que sigue en el turno, y quiere el 0.
        $partida = $this->jugar($partida, '1 12-basto', '2 envido', '3 real_envido', '0 quiero');

        // Tantos en ronda desde el mano: 5, 22 son mejores, 33 son mejores y el último empata
        // en 33, así que dice "son buenas": gana el que está más cerca del mano.
        $this->assertSame(
            [['asiento' => 1, 'tanto' => 5], ['asiento' => 2, 'tanto' => 22], ['asiento' => 3, 'tanto' => 33], ['asiento' => 0, 'tanto' => null]],
            $partida->aArray()['envido']['tantos'],
        );
        $this->assertSame([0, 5], $partida->tanteo());
        $this->assertSame(2, $partida->turno(), 'Sigue jugando el que tenía el turno.');

        // El 0 canta truco en su turno y lo quiere el 1. Gana la primera baza el 2.
        $partida = $this->jugar($partida, '2 2-copa', '3 7-copa', '0 truco', '1 quiero', '0 4-basto');
        $this->assertSame(2, $partida->turno());

        // Segunda baza: el 0 se va al mazo y su compañero sigue solo. La baza se cierra con tres cartas.
        $partida = $this->jugar($partida, '2 10-copa', '3 11-oro', '0 mazo');
        $this->assertSame(Fase::Jugando, $partida->fase());
        $this->assertSame(1, $partida->turno());

        $partida = $this->jugar($partida, '1 3-espada');
        $this->assertCount(3, $partida->aArray()['bazas'][1]['jugadas']);
        $this->assertSame(1, $partida->aArray()['bazas'][1]['ganador']);

        // Tercera baza: el quiero es del equipo del 1, que sube a retruco. Contesta el 2, el único que queda.
        $partida = $this->jugar($partida, '1 retruco', '2 quiero', '1 5-copa', '2 1-espada', '3 6-copa');

        $this->assertSame(Fase::PorRepartir, $partida->fase());
        $this->assertSame(0, $partida->cierre()['ganador']);
        $this->assertSame([3, 5], $partida->tanteo());
        $this->assertSame(
            [['equipo' => 1, 'puntos' => 5, 'concepto' => 'envido'], ['equipo' => 0, 'puntos' => 3, 'concepto' => 'truco']],
            $partida->cierre()['anotado'],
        );
        $this->assertSame([3 => ['7-copa', '6-copa']], $partida->cierre()['mostradas']);
        $this->assertSame(2, $partida->mano(), 'El mano pasa al asiento siguiente.');
    }

    public function test_se_juega_en_ronda_desde_el_mano_y_abre_quien_gano_la_baza(): void
    {
        $partida = $this->sinCantos(mano: 2);

        $this->assertSame(2, $partida->turno());

        $partida = $this->jugar($partida, '2 4-copa');
        $this->assertSame(3, $partida->turno());

        $partida = $this->jugar($partida, '3 1-basto', '0 4-oro');
        $this->assertSame(1, $partida->turno());

        $partida = $this->jugar($partida, '1 5-oro');
        $this->assertSame(3, $partida->turno(), 'Ganó el 3 con el 1 de basto: abre la segunda.');

        $partida = $this->jugar($partida, '3 6-basto');
        $this->assertSame(0, $partida->turno(), 'Y la ronda sigue desde él.');
    }

    public function test_si_va_ganando_el_companero_no_hace_falta_cantar_el_tanto(): void
    {
        $partida = $this->armada([
            ['7-oro', '6-oro', '4-basto'],     // 33
            ['12-copa', '5-copa', '3-basto'],  // 25
            ['10-espada', '11-espada', '1-oro'], // 20
            ['7-copa', '10-copa', '2-basto'],  // 27
        ]);

        $partida = $this->jugar($partida, '0 envido', '1 quiero');

        // El 2 no canta: va ganando su compañero. El 3 no supera los 33.
        $this->assertSame(
            [['asiento' => 0, 'tanto' => 33], ['asiento' => 1, 'tanto' => null], ['asiento' => 3, 'tanto' => null]],
            $partida->aArray()['envido']['tantos'],
        );
    }

    public function test_un_canto_al_equipo_lo_contesta_cualquiera_de_los_dos_y_vale_la_primera_respuesta(): void
    {
        $partida = $this->jugar($this->sinCantos(), '0 truco');

        $this->assertContains('quiero', $this->opciones($partida, 1));
        $this->assertContains('quiero', $this->opciones($partida, 3));
        $this->assertSame([], $partida->accionesPara(2), 'El compañero del que cantó espera.');

        $partida = $this->jugar($partida, '3 quiero');

        $this->assertSame('No es tu turno.', $this->rechazo($partida, '1 quiero'));
        $this->assertSame(0, $partida->turno());
    }

    public function test_el_quiero_es_del_equipo_y_sube_cualquiera_de_los_dos_en_su_turno(): void
    {
        $partida = $this->jugar($this->sinCantos(), '0 truco', '3 quiero', '0 4-oro');

        // Quiso el 3, pero el que tiene el turno es su compañero, el 1.
        $this->assertContains('retruco', $this->opciones($partida, 1));

        $partida = $this->jugar($partida, '1 5-oro');

        $this->assertSame('Solo puede subir el canto quien tiene el quiero.', $this->rechazo($partida, '2 retruco'));
    }

    public function test_cada_flor_suma_tres_para_su_equipo(): void
    {
        $partida = $this->armada([self::FLOR_DE_COPA, ['3-basto', '10-oro', '4-oro'], self::FLOR_DE_ESPADA, ['1-basto', '11-oro', '6-basto']]);

        $partida = $this->jugar($partida, '0 flor', '0 5-copa', '1 4-oro', '2 flor');
        $this->assertSame([0, 0], $partida->tanteo(), 'Falta que juegue el 3: todavía podría contestar.');

        $partida = $this->jugar($partida, '2 2-espada', '3 6-basto');
        $this->assertSame([6, 0], $partida->tanteo());
    }

    public function test_con_flor_en_los_dos_equipos_la_contraflor_compara_la_mejor_de_cada_uno(): void
    {
        // El 0 tiene 29, el 1 tiene 35 y el 2 tiene 32: la mejor del equipo del 0 es la del 2.
        $partida = $this->armada([self::FLOR_DE_COPA, self::FLOR_DE_ESPADA, self::FLOR_DE_ORO, ['1-basto', '11-basto', '6-basto']]);

        $partida = $this->jugar($partida, '0 flor', '0 5-copa', '1 contraflor');

        $this->assertContains('quiero', $this->opciones($partida, 0));
        $this->assertContains('quiero', $this->opciones($partida, 2));

        $partida = $this->jugar($partida, '2 quiero');

        $this->assertSame([0, 6], $partida->tanteo());
        $this->assertSame(1, $partida->aArray()['flor']['ganador']);
        $this->assertSame('La flor de esta mano ya se resolvió.', $this->rechazo($this->jugar($partida, '1 2-espada'), '2 flor'));
    }

    public function test_si_dos_companeros_cantan_flor_compite_la_mejor_de_las_dos(): void
    {
        // Cantan el 0 (29) y el 2 (32); el 3 contesta con 31: pierde contra la del 2.
        $partida = $this->armada([self::FLOR_DE_COPA, ['3-basto', '10-espada', '4-espada'], self::FLOR_DE_ORO, ['7-basto', '4-basto', '11-basto']]);

        $partida = $this->jugar($partida, '0 flor', '0 5-copa', '1 4-espada', '2 flor', '2 10-oro', '3 contraflor', '0 quiero');

        $this->assertSame([6, 0], $partida->tanteo());
        $this->assertSame(2, $partida->aArray()['flor']['ganador']);
    }

    public function test_la_flor_cantada_sigue_valiendo_en_la_contraflor_aunque_su_dueno_se_haya_ido(): void
    {
        // El 0 canta flor (29), el 1 contraflor (35) y los dos se van al mazo. Sube el 2, que no tiene flor,
        // y quiere el 3: se comparan igual las dos flores que se cantaron.
        $partida = $this->armada([self::FLOR_DE_COPA, self::FLOR_DE_ESPADA, ['3-basto', '10-oro', '4-oro'], ['1-basto', '11-basto', '6-basto']]);

        $partida = $this->jugar($partida, '0 flor', '0 5-copa', '1 contraflor', '0 mazo', '2 contraflor_al_resto', '1 mazo', '3 quiero');

        $this->assertSame(1, $partida->aArray()['flor']['ganador']);
        $this->assertSame(Fase::Terminada, $partida->fase(), 'Al resto desde cero a cero vale la partida.');
        $this->assertSame(1, $partida->ganador());
    }

    public function test_irse_al_mazo_es_de_un_jugador_y_el_companero_sigue_solo(): void
    {
        $partida = $this->jugar($this->sinCantos(), '0 4-oro', '1 mazo');

        $this->assertSame(Fase::Jugando, $partida->fase());
        $this->assertSame([0, 0], $partida->tanteo());
        $this->assertSame(2, $partida->turno(), 'El turno pasa al que sigue.');
        $this->assertSame('Ya te fuiste al mazo.', $this->rechazo($partida, '1 5-oro'));
        $this->assertSame([], $partida->accionesPara(1));
        $this->assertSame(0, $partida->vistaPara(3)['cartasEnMano'][1], 'Sus cartas quedaron muertas.');
    }

    public function test_el_equipo_pierde_la_mano_cuando_se_van_los_dos(): void
    {
        $partida = $this->jugar($this->sinCantos(), '0 4-oro', '1 mazo', '2 4-copa', '3 mazo');

        $this->assertSame(Fase::PorRepartir, $partida->fase());
        $this->assertSame(0, $partida->cierre()['ganador']);
        $this->assertSame([2, 0], $partida->tanteo(), 'En primera y sin envido: 1 de la mano y 1 del envido que no se jugó.');
    }

    public function test_quien_se_fue_al_mazo_no_contesta_los_cantos_de_su_equipo(): void
    {
        $partida = $this->jugar($this->sinCantos(), '0 4-oro', '1 mazo', '2 truco');

        $this->assertContains('quiero', $this->opciones($partida, 3));
        $this->assertSame([], $partida->accionesPara(1));
        $this->assertSame('Ya te fuiste al mazo.', $this->rechazo($partida, '1 quiero'));
    }

    public function test_si_el_que_queda_se_va_con_un_canto_sin_contestar_vale_como_no_quererlo(): void
    {
        $partida = $this->jugar($this->sinCantos(), '0 4-oro', '1 mazo', '2 4-copa', '3 1-basto', '3 6-basto', '0 truco', '3 mazo');

        $this->assertSame([1, 0], $partida->tanteo());
    }

    public function test_despues_de_una_parda_sale_el_mano_y_si_se_fue_el_que_le_sigue(): void
    {
        $manos = [['3-oro', '4-oro', '5-oro'], ['3-copa', '5-copa', '6-copa'], ['4-copa', '4-basto', '6-oro'], ['4-espada', '5-basto', '6-basto']];

        $parda = $this->jugar($this->armada($manos, mano: 1), '1 3-copa', '2 4-copa', '3 4-espada', '0 3-oro');
        $this->assertSame(1, $parda->turno());

        // Misma baza, pero el mano se va al mazo después de jugar: abre el siguiente que sigue en la mano.
        $sinElMano = $this->jugar($this->armada($manos, mano: 1), '1 3-copa', '2 4-copa', '3 4-espada', '0 3-oro', '1 mazo');
        $this->assertSame(2, $sinElMano->turno());
    }

    public function test_las_cartas_que_jugo_antes_de_irse_siguen_valiendo_en_la_baza(): void
    {
        $partida = $this->jugar($this->sinCantos(), '0 4-oro', '1 5-oro', '2 4-copa', '3 1-basto', '3 mazo');

        // El 3 ganó la primera con el 1 de basto y se fue: la baza queda como estaba y abre el siguiente.
        $this->assertSame(3, $partida->aArray()['bazas'][0]['ganador']);
        $this->assertSame(0, $partida->turno());
    }

    private function sinCantos(int $mano = 0): Partida
    {
        return $this->armada([
            ['4-oro', '10-basto', '11-copa'],
            ['5-oro', '12-espada', '2-oro'],
            ['4-copa', '10-espada', '3-oro'],
            ['1-basto', '6-basto', '7-copa'],
        ], $mano);
    }
}
