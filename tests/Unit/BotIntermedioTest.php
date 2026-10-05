<?php

namespace Tests\Unit;

use App\Juego\BotIntermedio;
use App\Motor\Accion;
use App\Motor\Partida;
use App\Motor\TipoDeAccion;
use PHPUnit\Framework\TestCase;
use Tests\Motor\Jugando;

/**
 * El bot Intermedio: juega bien y de frente. Está en el asiento 1 y decide con la vista de ese asiento.
 */
class BotIntermedioTest extends TestCase
{
    use Jugando;

    private const BOT = 1;

    public function test_con_el_ancho_en_la_mesa_y_una_baza_ganada_sube_el_truco_contando_la_carta_que_ya_tiro(): void
    {
        // Van una y una. El bot sale en la tercera con el ancho de espada y se queda sin cartas en la mano.
        $partida = $this->jugar(
            $this->armada([['3-oro', '4-copa', '5-copa'], ['4-basto', '2-oro', '1-espada']]),
            '0 3-oro', '1 4-basto', '0 4-copa', '1 2-oro', '1 1-espada', '0 truco',
        );

        $this->assertSame([], $partida->vistaPara(self::BOT)['misCartas']);
        $this->assertSame(TipoDeAccion::Retruco, $this->decide($partida)->tipo);
    }

    public function test_quiere_el_truco_con_un_dos_cuando_el_canto_recien_empieza(): void
    {
        $partida = $this->jugar(
            $this->armada([['3-oro', '1-espada', '5-copa'], ['4-basto', '2-oro', '10-copa']]),
            '0 3-oro', '1 4-basto', '0 truco',
        );

        $this->assertSame(TipoDeAccion::Quiero, $this->decide($partida)->tipo);
    }

    public function test_no_quiere_el_truco_sin_cartas_con_que_ganar(): void
    {
        $partida = $this->jugar(
            $this->armada([['3-oro', '1-espada', '5-copa'], ['4-basto', '6-oro', '10-copa']]),
            '0 3-oro', '1 4-basto', '0 truco',
        );

        $this->assertSame(TipoDeAccion::NoQuiero, $this->decide($partida)->tipo);
    }

    public function test_canta_truco_con_una_brava_bien_acompanada_sin_esperar_a_ganar_una_baza(): void
    {
        $acompanada = $this->armada([['4-oro', '5-copa', '6-copa'], ['7-espada', '3-basto', '5-oro']], mano: self::BOT);
        $sola = $this->armada([['4-oro', '5-copa', '6-copa'], ['7-espada', '6-basto', '5-oro']], mano: self::BOT);

        $this->assertSame(TipoDeAccion::Truco, $this->decide($acompanada)->tipo);
        $this->assertSame(TipoDeAccion::Jugar, $this->decide($sola)->tipo, 'Con una brava sola no canta: no miente.');
    }

    public function test_con_dos_bravas_contesta_el_truco_con_retruco(): void
    {
        $partida = $this->jugar(
            $this->armada([['3-oro', '4-copa', '5-copa'], ['1-basto', '7-oro', '4-basto']]),
            '0 truco',
        );

        $this->assertSame(TipoDeAccion::Retruco, $this->decide($partida)->tipo);
    }

    public function test_gana_la_baza_con_la_carta_mas_baja_que_alcanza(): void
    {
        $partida = $this->jugar(
            $this->armada([['10-oro', '4-copa', '5-copa'], ['1-espada', '12-basto', '4-basto']]),
            '0 10-oro',
        );

        $this->assertSame('12-basto', $this->decide($partida)->carta->id(), 'Le alcanza con el rey: no gasta el ancho.');
    }

    public function test_si_no_puede_ganar_la_baza_tira_la_mas_baja(): void
    {
        $partida = $this->jugar(
            $this->armada([['1-espada', '4-copa', '5-copa'], ['3-oro', '12-basto', '4-basto']]),
            '0 1-espada',
        );

        $this->assertSame('4-basto', $this->decide($partida)->carta->id());
    }

    public function test_con_flor_la_canta_antes_que_nada(): void
    {
        $partida = $this->jugar(
            $this->armada([['1-espada', '4-copa', '5-copa'], ['7-oro', '6-oro', '2-oro']]),
            '0 envido',
        );

        // Le cantaron envido y tiene flor: contesta con la flor, que lo anula.
        $this->assertSame(TipoDeAccion::Flor, $this->decide($partida)->tipo);
    }

    public function test_canta_envido_con_27_y_real_envido_con_31(): void
    {
        $con = fn (string ...$cartas) => $this->decide($this->armada([['1-espada', '4-copa', '5-copa'], $cartas], mano: self::BOT))->tipo;

        $this->assertSame(TipoDeAccion::RealEnvido, $con('7-oro', '4-oro', '3-basto'), 'Con 31 canta real envido.');
        $this->assertSame(TipoDeAccion::Envido, $con('5-oro', '2-oro', '4-basto'), 'Con 27 canta envido.');
        $this->assertSame(TipoDeAccion::Jugar, $con('5-oro', '1-oro', '4-basto'), 'Con 26 no canta.');
    }

    public function test_quiere_el_envido_solo_con_tanto_suficiente_y_con_mucho_lo_sube(): void
    {
        $con = fn (string ...$cartas) => $this->decide($this->jugar($this->armada([['1-espada', '4-copa', '5-copa'], $cartas]), '0 envido'))->tipo;

        $this->assertSame(TipoDeAccion::RealEnvido, $con('7-oro', '6-oro', '3-basto'), 'Con 33 lo sube.');
        $this->assertSame(TipoDeAccion::Quiero, $con('5-oro', '3-oro', '3-basto'), 'Con 28 lo quiere.');
        $this->assertSame(TipoDeAccion::NoQuiero, $con('7-oro', '6-basto', '3-espada'), 'Con 7 no lo quiere.');
    }

    public function test_el_envido_esta_primero_si_le_cantan_truco_y_tiene_tanto(): void
    {
        $partida = $this->jugar(
            $this->armada([['1-espada', '4-copa', '5-copa'], ['6-oro', '3-oro', '4-basto']]),
            '0 truco',
        );

        $this->assertSame(TipoDeAccion::Envido, $this->decide($partida)->tipo);
    }

    private function decide(Partida $partida): Accion
    {
        return (new BotIntermedio)->decidir($partida->vistaPara(self::BOT));
    }
}
