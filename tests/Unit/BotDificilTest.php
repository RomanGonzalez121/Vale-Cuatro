<?php

namespace Tests\Unit;

use App\Juego\BotDificil;
use App\Juego\Lectura;
use App\Juego\Probabilidades;
use App\Motor\Accion;
use App\Motor\Azar;
use App\Motor\Partida;
use App\Motor\TipoDeAccion;
use PHPUnit\Framework\TestCase;
use Tests\Motor\Jugando;

/**
 * El bot Difícil: calcula probabilidades y miente. Está en el asiento 1.
 */
class BotDificilTest extends TestCase
{
    use Jugando;

    private const BOT = 1;

    private const RIVAL = ['3-copa', '4-copa', '5-basto'];

    public function test_con_33_de_mano_el_envido_es_seguro_y_con_cero_de_pie_esta_perdido(): void
    {
        $con33 = $this->armada([self::RIVAL, ['7-oro', '6-oro', '4-basto']], mano: self::BOT);
        $conCero = $this->armada([self::RIVAL, ['10-oro', '11-basto', '12-espada']]);

        $this->assertSame(1.0, Probabilidades::deGanarElEnvido($this->lectura($con33)));
        $this->assertSame(0.0, Probabilidades::deGanarElEnvido($this->lectura($conCero)), 'Empatando en cero gana el mano.');
    }

    public function test_si_el_rival_canto_envido_se_le_cree_y_la_probabilidad_baja(): void
    {
        $manos = [self::RIVAL, ['7-oro', '2-oro', '4-basto']];

        $sinCanto = Probabilidades::deGanarElEnvido($this->lectura($this->armada($manos, mano: self::BOT)));
        $conCanto = Probabilidades::deGanarElEnvido($this->lectura($this->jugar($this->armada($manos), '0 envido')));

        $this->assertGreaterThan(0.8, $sinCanto, 'Con 29, contra una mano cualquiera, gana casi siempre.');
        $this->assertLessThan($sinCanto - 0.2, $conCanto, 'Contra alguien que cantó envido, 29 ya no es tanto.');
    }

    public function test_con_las_tres_cartas_mas_altas_la_mano_es_segura_y_con_tres_cuatros_esta_perdida(): void
    {
        $lasMasAltas = $this->armada([self::RIVAL, ['1-espada', '1-basto', '7-espada']], mano: self::BOT);
        $tresCuatros = $this->armada([['3-copa', '5-copa', '5-basto'], ['4-oro', '4-basto', '4-espada']], mano: self::BOT);

        $this->assertSame(1.0, Probabilidades::deGanarLaMano($this->lectura($lasMasAltas)));
        $this->assertSame(0.0, Probabilidades::deGanarLaMano($this->lectura($tresCuatros)));
    }

    public function test_la_probabilidad_de_ganar_la_mano_cuenta_las_bazas_ya_jugadas(): void
    {
        // Ganó la primera con el 3 y le queda el ancho de espada: la segunda es suya, salga quien salga.
        $partida = $this->jugar(
            $this->armada([['2-copa', '5-copa', '5-basto'], ['3-oro', '1-espada', '4-espada']]),
            '0 2-copa', '1 3-oro',
        );

        $this->assertSame(1.0, Probabilidades::deGanarLaMano($this->lectura($partida)));
    }

    public function test_creerle_al_que_canta_baja_la_probabilidad(): void
    {
        $this->assertEqualsWithDelta(1 / 3, Probabilidades::creyendole(0.5, 2), 0.0001);
        $this->assertSame(1.0, Probabilidades::creyendole(1.0, 4), 'Lo seguro sigue siendo seguro.');
        $this->assertSame(0.0, Probabilidades::creyendole(0.0, 2));
    }

    public function test_gana_la_baza_sin_gastar_el_ancho(): void
    {
        // Con el truco querido ya no hay envido, y con 28 puntos la mano le alcanza: no tiene nada para cantar.
        $partida = $this->jugar(
            $this->armada([['10-oro', '4-copa', '5-copa'], ['1-espada', '12-basto', '4-basto']], tanteo: [0, 28]),
            '0 truco', '1 quiero', '0 10-oro',
        );

        $this->assertSame('12-basto', $this->decide($partida)->carta->id());
    }

    public function test_con_cartas_muy_fuertes_contesta_el_truco_subiendo_y_con_flojas_no_lo_quiere(): void
    {
        $fuertes = $this->jugar($this->armada([self::RIVAL, ['1-espada', '1-basto', '7-oro']]), '0 truco');
        $flojas = $this->jugar($this->armada([self::RIVAL, ['4-oro', '5-espada', '10-basto']]), '0 truco');

        $this->assertSame(TipoDeAccion::Retruco, $this->decide($fuertes)->tipo);
        $this->assertSame(TipoDeAccion::NoQuiero, $this->decide($flojas)->tipo);
    }

    public function test_si_no_querer_le_da_la_partida_al_rival_quiere_aunque_tenga_poco(): void
    {
        // Al rival le falta un punto: no querer el truco es perder la partida.
        $partida = $this->jugar(
            $this->armada([self::RIVAL, ['4-oro', '5-espada', '10-basto']], tanteo: [29, 10]),
            '0 truco',
        );

        $this->assertNotSame(TipoDeAccion::NoQuiero, $this->decide($partida)->tipo);
    }

    public function test_con_mucho_tanto_canta_el_envido_y_con_poco_casi_nunca(): void
    {
        $mucho = $this->armada([self::RIVAL, ['7-oro', '6-oro', '4-basto']], mano: self::BOT);
        $poco = $this->armada([self::RIVAL, ['10-oro', '11-basto', '4-espada']], mano: self::BOT);
        $mentiras = 0;

        foreach (range(1, 300) as $semilla) {
            $this->assertContains($this->decide($mucho, $semilla)->tipo, [TipoDeAccion::Envido, TipoDeAccion::RealEnvido]);
            $mentiras += (int) ($this->decide($poco, $semilla)->tipo === TipoDeAccion::Envido);
        }

        // Miente una de cada seis veces: de 300, alrededor de 50.
        $this->assertGreaterThan(25, $mentiras, 'Con poco tanto tiene que mentir cada tanto.');
        $this->assertLessThan(80, $mentiras, 'Si miente tan seguido deja de ser una mentira.');
    }

    public function test_canta_la_flor_salvo_que_le_canten_un_envido_grande_y_tenga_32_o_33(): void
    {
        $con33 = ['7-oro', '6-oro', '2-oro'];
        $con29 = ['5-oro', '4-oro', '10-oro'];

        $enSuTurno = $this->armada([self::RIVAL, $con33], mano: self::BOT);
        $envidoChico = $this->jugar($this->armada([self::RIVAL, $con33]), '0 envido');
        $envidoGrande = $this->jugar($this->armada([self::RIVAL, $con33]), '0 falta_envido');
        $grandeConPocoTanto = $this->jugar($this->armada([self::RIVAL, $con29]), '0 falta_envido');

        $this->assertSame(TipoDeAccion::Flor, $this->decide($enSuTurno)->tipo);
        $this->assertSame(TipoDeAccion::Flor, $this->decide($envidoChico)->tipo, 'Un envido vale 2: la flor deja más.');
        $this->assertSame(TipoDeAccion::Quiero, $this->decide($envidoGrande)->tipo, 'Calla la flor y juega la falta con 33.');
        $this->assertSame(TipoDeAccion::Flor, $this->decide($grandeConPocoTanto)->tipo, 'Con 29 no se arriesga: canta la flor.');
    }

    public function test_la_misma_semilla_decide_siempre_lo_mismo(): void
    {
        $partida = $this->armada([self::RIVAL, ['10-oro', '11-basto', '4-espada']], mano: self::BOT);

        foreach (range(1, 30) as $semilla) {
            $this->assertEquals($this->decide($partida, $semilla), $this->decide($partida, $semilla));
        }
    }

    private function decide(Partida $partida, int $semilla = 1): Accion
    {
        return (new BotDificil(Azar::deSemilla($semilla)))->decidir($partida->vistaPara(self::BOT));
    }

    private function lectura(Partida $partida): Lectura
    {
        return new Lectura($partida->vistaPara(self::BOT));
    }
}
