<?php

namespace Tests\Unit;

use App\Juego\Bot;
use App\Juego\BotConMania;
use App\Juego\Nivel;
use App\Motor\Accion;
use App\Motor\Azar;
use PHPUnit\Framework\TestCase;

/**
 * Los jefes de la gira juegan con su manía encima de la máquina. Cada manía se juega en una tanda de partidas
 * sobre el motor: solo hace jugadas válidas (lo controla Enfrentando) y hace lo que dice su cartel.
 */
class BotConManiaTest extends TestCase
{
    use Enfrentando;

    /**
     * Juega una tanda con el jefe en un asiento y una máquina intermedia en el otro. Devuelve cada decisión
     * del jefe: qué valía en ese momento y qué hizo.
     *
     * @return list<array{validas: list<string>, hizo: string}>
     */
    private function tanda(string $mania, Nivel $nivel = Nivel::Dificil, int $partidas = 6): array
    {
        $espia = new class implements Bot
        {
            public ?Bot $bot = null;

            /** @var list<array{validas: list<string>, hizo: string}> */
            public array $decisiones = [];

            public function decidir(array $vista): Accion
            {
                $accion = $this->bot->decidir($vista);
                $this->decisiones[] = ['validas' => array_column($vista['acciones'], 'tipo'), 'hizo' => $accion->tipo->value];

                return $accion;
            }
        };

        for ($numero = 0; $numero < $partidas; $numero++) {
            $semilla = 400 + $numero;
            $espia->bot = new BotConMania($nivel->bot(Azar::deSemilla($semilla * 7 + 1)), $mania);
            $asiento = $numero % 2;
            $bots = [];
            $bots[$asiento] = $espia;
            $bots[1 - $asiento] = Nivel::Intermedio->bot(Azar::deSemilla($semilla * 7 + 2));

            $this->partidaEntre($bots, $semilla);
        }

        return $espia->decisiones;
    }

    public function test_el_que_nunca_se_va_juega_todas_las_manos(): void
    {
        $decisiones = $this->tanda('nunca_se_va', Nivel::Intermedio);

        $this->assertNotContains('mazo', array_column($decisiones, 'hizo'));
    }

    public function test_el_que_quiere_todo_nunca_dice_que_no(): void
    {
        $decisiones = $this->tanda('quiere_todo');
        $hizo = array_column($decisiones, 'hizo');

        $this->assertNotContains('no_quiero', $hizo);
        $this->assertContains('quiero', $hizo);
    }

    public function test_el_que_canta_todo_canta_envido_y_truco_en_vez_de_tirar_una_carta(): void
    {
        foreach ($this->tanda('canta_todo') as $decision) {
            if ($decision['hizo'] === 'jugar') {
                $this->assertNotContains('envido', $decision['validas'], 'Pudiendo cantar envido, tiró una carta.');
                $this->assertNotContains('truco', $decision['validas'], 'Pudiendo cantar truco, tiró una carta.');
            }
        }
    }

    public function test_el_que_miente_siempre_canta_truco_con_cualquier_cosa(): void
    {
        $decisiones = $this->tanda('miente_siempre');

        foreach ($decisiones as $decision) {
            if ($decision['hizo'] === 'jugar') {
                $this->assertNotContains('truco', $decision['validas'], 'Pudiendo cantar truco, tiró una carta.');
            }
        }

        $this->assertContains('truco', array_column($decisiones, 'hizo'));
    }

    public function test_una_mania_que_no_existe_no_se_acepta(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new BotConMania(Nivel::Facil->bot(Azar::deSemilla(1)), 'hace_trampa');
    }
}
