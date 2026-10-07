<?php

namespace Tests\Unit;

use App\Juego\BotDificil;
use App\Juego\BotUltraDificil;
use App\Juego\Deducciones;
use App\Juego\Lectura;
use App\Juego\Probabilidades;
use App\Motor\Azar;
use App\Motor\Carta;
use App\Motor\Partida;
use App\Motor\Tanto;
use App\Motor\TipoDeAccion;
use PHPUnit\Framework\TestCase;
use Tests\Motor\Jugando;

/**
 * El bot Ultra difícil: el Difícil más lo que deduce del rival. Está en el asiento 1.
 */
class BotUltraDificilTest extends TestCase
{
    use Jugando;

    private const BOT = 1;

    public function test_si_el_rival_dijo_su_tanto_solo_quedan_las_manos_que_dan_ese_tanto(): void
    {
        // El rival es mano y dice 33. El bot tiene el 7 de oro y el 6 de espada: ese 33 es de copa o de basto.
        $partida = $this->jugar(
            $this->armada([['7-basto', '6-basto', '4-copa'], ['7-oro', '6-espada', '2-copa']]),
            '0 envido', '1 quiero',
        );

        $posibles = Deducciones::manosPosibles($this->lectura($partida));

        $this->assertNotNull($posibles);
        $this->assertContains(['4-copa', '6-basto', '7-basto'], array_map($this->ordenadas(...), $posibles), 'La mano de verdad tiene que estar entre las posibles.');

        foreach ($posibles as $mano) {
            $this->assertSame(33, Tanto::deEnvido($mano));

            $palos = array_count_values(array_map(fn (Carta $carta) => $carta->palo->value, $mano));
            $this->assertTrue(($palos['copa'] ?? 0) >= 2 || ($palos['basto'] ?? 0) >= 2);
        }
    }

    public function test_si_el_rival_dijo_son_buenas_su_tanto_no_pasa_del_propio(): void
    {
        // El bot es mano y dice 27. El rival tiene 25 y dice "son buenas".
        $partida = $this->jugar(
            $this->armada([['3-copa', '2-copa', '5-basto'], ['7-oro', '10-oro', '4-basto']], mano: self::BOT),
            '1 envido', '0 quiero',
        );

        $posibles = Deducciones::manosPosibles($this->lectura($partida));
        $todas = Probabilidades::manosDelRival($this->lectura($partida));

        $this->assertNotNull($posibles);
        $this->assertLessThan(count($todas), count($posibles), 'Tiene que descartar las manos con más de 27.');
        $this->assertContains(['2-copa', '3-copa', '5-basto'], array_map($this->ordenadas(...), $posibles));

        foreach ($posibles as $mano) {
            $this->assertLessThanOrEqual(27, Tanto::deEnvido($mano));
        }
    }

    public function test_sin_tantos_dichos_no_descarta_nada(): void
    {
        $sinEnvido = $this->armada([['3-copa', '2-copa', '5-basto'], ['7-oro', '10-oro', '4-basto']]);
        $noQuerido = $this->jugar($sinEnvido, '0 envido', '1 no_quiero');

        $this->assertNull(Deducciones::manosPosibles($this->lectura($sinEnvido)));
        $this->assertNull(Deducciones::manosPosibles($this->lectura($noQuerido)), 'Con el envido no querido nadie dijo su tanto.');
    }

    /**
     * La cuenta de la mano tiene dos caminos: el rápido, que agrupa las cartas sin ver, y el que
     * recibe las manos una por una. Con todas las manos tienen que dar lo mismo.
     */
    public function test_contar_todas_las_manos_una_por_una_da_lo_mismo_que_la_cuenta_rapida(): void
    {
        $situaciones = [
            $this->armada([['3-copa', '2-copa', '5-basto'], ['7-oro', '6-espada', '2-basto']], mano: self::BOT),
            $this->jugar($this->armada([['3-copa', '2-copa', '5-basto'], ['7-oro', '6-espada', '2-basto']]), '0 2-copa'),
            $this->jugar($this->armada([['3-copa', '2-copa', '5-basto'], ['1-basto', '4-espada', '12-oro']]), '0 5-basto', '1 12-oro'),
        ];

        foreach ($situaciones as $partida) {
            $lectura = $this->lectura($partida);
            $todas = Probabilidades::manosDelRival($lectura);

            $this->assertEqualsWithDelta(Probabilidades::deGanarLaMano($lectura), Probabilidades::deGanarLaMano($lectura, null, $todas), 0.000001);

            foreach ($lectura->enMano() as $carta) {
                $this->assertEqualsWithDelta(Probabilidades::deGanarLaMano($lectura, $carta), Probabilidades::deGanarLaMano($lectura, $carta, $todas), 0.000001);
            }
        }
    }

    public function test_sabiendo_que_el_rival_tiene_dos_cartas_bajas_contesta_el_truco_subiendo(): void
    {
        // El 33 del rival es de copa o de basto: dos de sus tres cartas son un 7 falso y un 6.
        // Con el 7 de oro y un 10, el bot gana dos bazas tenga el rival lo que tenga de tercera.
        $partida = $this->jugar(
            $this->armada([['7-basto', '6-basto', '4-copa'], ['7-oro', '6-espada', '10-copa']]),
            '0 envido', '1 quiero', '0 truco',
        );

        foreach (range(1, 20) as $semilla) {
            $this->assertSame(TipoDeAccion::Retruco, (new BotUltraDificil(Azar::deSemilla($semilla)))->decidir($partida->vistaPara(self::BOT))->tipo);
            $this->assertNotSame(TipoDeAccion::Retruco, (new BotDificil(Azar::deSemilla($semilla)))->decidir($partida->vistaPara(self::BOT))->tipo, 'El Difícil no sabe eso y no sube.');
        }
    }

    public function test_si_el_rival_ya_jugo_sin_cantar_envido_cree_que_tiene_poco_tanto(): void
    {
        // El rival es mano y tiró su primera carta sin cantar. El bot tiene 26.
        $manos = [['3-copa', '2-espada', '5-basto'], ['6-oro', '10-oro', '4-basto']];
        $callado = $this->jugar($this->armada($manos), '0 3-copa');
        $sinJugar = $this->armada($manos, mano: self::BOT);

        $this->assertTrue(Deducciones::seCalloElEnvido($this->lectura($callado)));
        $this->assertFalse(Deducciones::seCalloElEnvido($this->lectura($sinJugar)), 'El rival todavía no tuvo su turno.');

        $lectura = $this->lectura($callado);

        $this->assertGreaterThan(Probabilidades::deGanarElEnvido($lectura) + 0.05, Probabilidades::deGanarElEnvido($lectura, 0.25));
    }

    public function test_si_el_rival_canto_envido_el_silencio_no_cuenta(): void
    {
        $partida = $this->jugar($this->armada([['3-copa', '2-copa', '5-basto'], ['6-oro', '10-oro', '4-basto']]), '0 envido');
        $lectura = $this->lectura($partida);

        $this->assertFalse(Deducciones::seCalloElEnvido($lectura));
        $this->assertSame(Probabilidades::deGanarElEnvido($lectura), Probabilidades::deGanarElEnvido($lectura, 0.25));
    }

    public function test_la_misma_semilla_decide_siempre_lo_mismo(): void
    {
        $partida = $this->jugar(
            $this->armada([['7-basto', '6-basto', '4-copa'], ['10-oro', '11-basto', '4-espada']]),
            '0 envido', '1 quiero', '0 4-copa',
        );

        foreach (range(1, 30) as $semilla) {
            $this->assertEquals(
                (new BotUltraDificil(Azar::deSemilla($semilla)))->decidir($partida->vistaPara(self::BOT)),
                (new BotUltraDificil(Azar::deSemilla($semilla)))->decidir($partida->vistaPara(self::BOT)),
            );
        }
    }

    /**
     * @param  list<Carta>  $mano
     * @return list<string>
     */
    private function ordenadas(array $mano): array
    {
        $ids = array_map(fn (Carta $carta) => $carta->id(), $mano);
        sort($ids);

        return $ids;
    }

    private function lectura(Partida $partida): Lectura
    {
        return new Lectura($partida->vistaPara(self::BOT));
    }
}
