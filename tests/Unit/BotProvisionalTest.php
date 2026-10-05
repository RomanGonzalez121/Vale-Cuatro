<?php

namespace Tests\Unit;

use App\Juego\BotProvisional;
use App\Motor\Accion;
use App\Motor\Carta;
use App\Motor\Partida;
use App\Motor\TipoDeAccion;
use PHPUnit\Framework\TestCase;

/**
 * El bot de M3. Está en el asiento 1 y decide con la vista de ese asiento, armada con el motor.
 */
class BotProvisionalTest extends TestCase
{
    private const BOT = 1;

    public function test_quiere_el_truco_contando_la_carta_que_ya_tiro_en_la_baza(): void
    {
        // Van una y una. El bot sale en la tercera con el ancho de espada y se queda sin cartas en la mano.
        $partida = $this->jugar(
            [['3-oro', '4-copa', '5-copa'], ['4-basto', '2-oro', '1-espada']],
            ['0 3-oro', '1 4-basto', '0 4-copa', '1 2-oro', '1 1-espada', '0 truco'],
        );

        $vista = $partida->vistaPara(self::BOT);

        $this->assertSame([], $vista['misCartas']);
        $this->assertSame(TipoDeAccion::Quiero, (new BotProvisional)->decidir($vista)->tipo);
    }

    public function test_no_quiere_el_truco_sin_cartas_con_que_ganar(): void
    {
        $partida = $this->jugar(
            [['3-oro', '1-espada', '5-copa'], ['4-basto', '6-oro', '10-copa']],
            ['0 3-oro', '1 4-basto', '0 truco'],
        );

        $this->assertSame(TipoDeAccion::NoQuiero, (new BotProvisional)->decidir($partida->vistaPara(self::BOT))->tipo);
    }

    public function test_gana_la_baza_con_la_carta_mas_baja_que_alcanza(): void
    {
        $partida = $this->jugar(
            [['10-oro', '4-copa', '5-copa'], ['1-espada', '12-basto', '4-basto']],
            ['0 10-oro'],
        );

        $accion = (new BotProvisional)->decidir($partida->vistaPara(self::BOT));

        $this->assertSame('12-basto', $accion->carta->id(), 'Le alcanza con el rey: no gasta el ancho.');
    }

    public function test_si_no_puede_ganar_la_baza_tira_la_mas_baja(): void
    {
        $partida = $this->jugar(
            [['1-espada', '4-copa', '5-copa'], ['3-oro', '12-basto', '4-basto']],
            ['0 1-espada'],
        );

        $this->assertSame('4-basto', (new BotProvisional)->decidir($partida->vistaPara(self::BOT))->carta->id());
    }

    public function test_con_flor_la_canta_antes_que_nada(): void
    {
        $partida = $this->jugar(
            [['1-espada', '4-copa', '5-copa'], ['7-oro', '6-oro', '2-oro']],
            ['0 envido'],
        );

        // Le cantaron envido y tiene flor: contesta con la flor, que lo anula.
        $this->assertSame(TipoDeAccion::Flor, (new BotProvisional)->decidir($partida->vistaPara(self::BOT))->tipo);
    }

    public function test_quiere_el_envido_solo_con_tanto_suficiente(): void
    {
        $conTreintaYTres = $this->jugar([['1-espada', '4-copa', '5-copa'], ['7-oro', '6-oro', '3-basto']], ['0 envido']);
        $conSiete = $this->jugar([['1-espada', '4-copa', '5-copa'], ['7-oro', '6-basto', '3-espada']], ['0 envido']);

        $this->assertSame(TipoDeAccion::Quiero, (new BotProvisional)->decidir($conTreintaYTres->vistaPara(self::BOT))->tipo);
        $this->assertSame(TipoDeAccion::NoQuiero, (new BotProvisional)->decidir($conSiete->vistaPara(self::BOT))->tipo);
    }

    /**
     * @param  array{0: list<string>, 1: list<string>}  $manos
     * @param  list<string>  $jugadas  Escritas como "0 3-oro" o "0 truco".
     */
    private function jugar(array $manos, array $jugadas): Partida
    {
        $partida = Partida::armada(array_map(fn (array $ids) => array_map(Carta::de(...), $ids), $manos));

        foreach ($jugadas as $jugada) {
            [$asiento, $que] = explode(' ', $jugada, 2);

            $partida = $partida->aplicar(
                (int) $asiento,
                str_contains($que, '-') ? Accion::jugar(Carta::de($que)) : Accion::de(TipoDeAccion::from($que)),
            );
        }

        return $partida;
    }
}
