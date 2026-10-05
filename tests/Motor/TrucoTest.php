<?php

namespace Tests\Motor;

use App\Motor\Fase;
use App\Motor\Partida;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TrucoTest extends TestCase
{
    use Jugando;

    /**
     * El asiento 0 gana las dos primeras bazas jugando sus cartas en orden.
     */
    private const GANA_EL_CERO = ['0 1-espada', '1 4-copa', '0 1-basto', '1 5-copa'];

    /**
     * @param  list<string>  $cantos
     */
    #[DataProvider('queridos')]
    public function test_lo_que_vale_la_mano_con_cada_canto_querido(array $cantos, int $puntos): void
    {
        $partida = $this->jugar($this->mano(), ...$cantos, ...self::GANA_EL_CERO);

        $this->assertSame([$puntos, 0], $partida->tanteo());
        $this->assertSame('bazas', $partida->cierre()['motivo']);
    }

    public static function queridos(): array
    {
        return [
            'sin truco vale 1' => [[], 1],
            'truco vale 2' => [['0 truco', '1 quiero'], 2],
            'retruco vale 3' => [['0 truco', '1 retruco', '0 quiero'], 3],
            'vale cuatro vale 4' => [['0 truco', '1 retruco', '0 vale_cuatro', '1 quiero'], 4],
        ];
    }

    /**
     * @param  list<string>  $cantos
     * @param  array{0: int, 1: int}  $tanteo
     */
    #[DataProvider('noQueridos')]
    public function test_lo_que_suma_quien_canto_si_no_se_lo_quieren(array $cantos, array $tanteo): void
    {
        $partida = $this->jugar($this->mano(), ...$cantos);

        $this->assertSame($tanteo, $partida->tanteo());
        $this->assertSame(Fase::PorRepartir, $partida->fase());
        $this->assertSame('no_quiero', $partida->cierre()['motivo']);
    }

    public static function noQueridos(): array
    {
        return [
            'truco no querido vale 1' => [['0 truco', '1 no_quiero'], [1, 0]],
            'retruco no querido vale 2' => [['0 truco', '1 retruco', '0 no_quiero'], [0, 2]],
            'vale cuatro no querido vale 3' => [['0 truco', '1 retruco', '0 vale_cuatro', '1 no_quiero'], [3, 0]],
        ];
    }

    public function test_solo_sube_el_canto_quien_tiene_el_quiero(): void
    {
        $partida = $this->jugar($this->mano(), '0 truco', '1 quiero');

        // Cantó el 0 y quiso el 1: el quiero es del 1.
        $this->assertSame('Solo puede subir el canto quien tiene el quiero.', $this->rechazo($partida, '0 retruco'));
        $this->assertNotContains('retruco', $this->opciones($partida, 0));

        $partida = $this->jugar($partida, '0 1-espada');

        $this->assertContains('retruco', $this->opciones($partida, 1));

        $partida = $this->jugar($partida, '1 retruco', '0 quiero', '1 4-copa');

        // Ahora quiso el 0: es el único que puede cantar vale cuatro.
        $this->assertContains('vale_cuatro', $this->opciones($partida, 0));

        $partida = $this->jugar($partida, '0 1-basto');

        $this->assertSame('Solo puede subir el canto quien tiene el quiero.', $this->rechazo($partida, '1 vale_cuatro'));
    }

    public function test_el_truco_se_canta_solo_en_el_turno_propio(): void
    {
        $partida = $this->mano();

        $this->assertSame('No es tu turno.', $this->rechazo($partida, '1 truco'));

        $partida = $this->jugar($partida, '0 1-espada');

        $this->assertSame('No es tu turno.', $this->rechazo($partida, '0 truco'));
        $this->assertContains('truco', $this->opciones($partida, 1));
    }

    public function test_el_truco_se_puede_cantar_en_cualquier_baza(): void
    {
        $partida = $this->armada([['1-espada', '4-oro', '7-oro'], ['4-copa', '7-espada', '6-basto']]);
        $partida = $this->jugar($partida, '0 1-espada', '1 4-copa', '0 4-oro', '1 7-espada', '1 truco', '0 quiero', '1 6-basto', '0 7-oro');

        $this->assertSame([2, 0], $partida->tanteo());
    }

    public function test_con_un_canto_sin_contestar_no_se_juega_una_carta(): void
    {
        $partida = $this->jugar($this->mano(), '0 1-espada', '1 4-copa', '0 truco');

        $this->assertSame('Antes de jugar hay que contestar el canto.', $this->rechazo($partida, '1 5-copa'));
        $this->assertSame(['retruco', 'quiero', 'no_quiero', 'mazo'], $this->opciones($partida, 1));
    }

    public function test_quien_canto_espera_la_respuesta(): void
    {
        $partida = $this->jugar($this->mano(), '0 truco');

        $this->assertSame([], $partida->accionesPara(0));
        $this->assertSame('Hay un canto sin contestar y le toca al otro equipo.', $this->rechazo($partida, '0 1-espada'));
        $this->assertSame('Hay un canto sin contestar y le toca al otro equipo.', $this->rechazo($partida, '0 mazo'));
    }

    public function test_despues_de_la_respuesta_sigue_jugando_quien_tenia_el_turno(): void
    {
        $partida = $this->jugar($this->mano(), '0 truco', '1 quiero');

        $this->assertSame(0, $partida->turno());
        $this->assertSame(Fase::Jugando, $partida->fase());
    }

    public function test_el_truco_sube_de_a_un_paso_y_no_se_repite(): void
    {
        $partida = $this->mano();

        $this->assertSame('El truco sube de a un paso: truco, retruco y vale cuatro.', $this->rechazo($partida, '0 retruco'));
        $this->assertSame('El truco sube de a un paso: truco, retruco y vale cuatro.', $this->rechazo($partida, '0 vale_cuatro'));

        $partida = $this->jugar($partida, '0 truco');

        $this->assertSame('Eso ya se cantó en esta mano.', $this->rechazo($partida, '1 truco'));
        $this->assertSame('El truco sube de a un paso: truco, retruco y vale cuatro.', $this->rechazo($partida, '1 vale_cuatro'));
    }

    public function test_despues_del_vale_cuatro_no_hay_nada_mas_para_cantar(): void
    {
        $partida = $this->jugar($this->mano(), '0 truco', '1 retruco', '0 vale_cuatro');

        $this->assertSame(['quiero', 'no_quiero', 'mazo'], $this->opciones($partida, 1));

        $partida = $this->jugar($partida, '1 quiero');

        $this->assertSame(['1-espada', '1-basto', '7-espada', 'mazo'], $this->opciones($partida, 0));
    }

    public function test_no_se_contesta_si_nadie_canto(): void
    {
        $this->assertSame('No hay ningún canto para contestar.', $this->rechazo($this->mano(), '0 quiero'));
        $this->assertSame('No hay ningún canto para contestar.', $this->rechazo($this->mano(), '0 no_quiero'));
    }

    public function test_mazo_en_primera_con_el_truco_querido_suma_solo_el_truco(): void
    {
        $partida = $this->jugar($this->mano(), '0 truco', '1 quiero', '0 mazo');

        $this->assertSame([0, 2], $partida->tanteo());
        $this->assertSame([['equipo' => 1, 'puntos' => 2, 'concepto' => 'truco']], $partida->cierre()['anotado']);
    }

    public function test_irse_al_mazo_con_el_truco_sin_contestar_vale_como_no_quererlo(): void
    {
        $partida = $this->jugar($this->mano(), '0 truco', '1 mazo');

        $this->assertSame([1, 0], $partida->tanteo());
    }

    public function test_irse_al_mazo_con_el_retruco_querido_le_da_tres_al_rival(): void
    {
        $partida = $this->jugar($this->mano(), '0 truco', '1 retruco', '0 quiero', '0 1-espada', '1 4-copa', '0 mazo');

        $this->assertSame([0, 3], $partida->tanteo());
    }

    public function test_el_truco_se_olvida_al_repartir_de_nuevo(): void
    {
        $partida = $this->jugar($this->mano(), '0 truco', '1 no_quiero');
        $partida = $partida->conManos([$this->cartas(['1-espada', '1-basto', '7-espada']), $this->cartas(['4-copa', '5-copa', '6-basto'])]);

        // Ahora es mano el asiento 1 y puede cantar truco de cero.
        $this->assertContains('truco', $this->opciones($partida, 1));
    }

    public function test_cantar_y_contestar_quedan_contados(): void
    {
        $partida = $this->jugar($this->mano(), '0 truco');
        $this->assertSame([['tipo' => 'canto', 'asiento' => 0, 'canto' => 'truco']], $partida->hechos());

        $partida = $this->jugar($partida, '1 quiero');
        $this->assertSame([['tipo' => 'respuesta', 'asiento' => 1, 'canto' => 'truco', 'quiere' => true]], $partida->hechos());
    }

    private function mano(): Partida
    {
        return $this->armada([['1-espada', '1-basto', '7-espada'], ['4-copa', '5-copa', '6-basto']]);
    }
}
