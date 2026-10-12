<?php

namespace Tests\Unit;

use App\Juego\Desafios;
use App\Juego\Escalera;
use App\Juego\JugadoresDeEjemplo;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Juego\Objetivos;
use App\Motor\Accion;
use App\Motor\Carta;
use App\Motor\Fase;
use App\Motor\TipoDeAccion;
use PHPUnit\Framework\TestCase;

/**
 * El catálogo de desafíos y la escalera del club: que cada ficha esté bien escrita, que cada mano
 * tenga solución y no salga sola, y que un desafío se defina solo con datos.
 */
class DesafiosTest extends TestCase
{
    use ResolviendoDesafios;

    public function test_cada_ficha_esta_bien_escrita(): void
    {
        $fichas = Desafios::todos();
        $claves = array_column($fichas, 'clave');

        $this->assertSame($claves, array_unique($claves), 'Hay dos desafíos con la misma clave.');

        foreach ($fichas as $ficha) {
            $clave = $ficha['clave'];

            // La clave viaja en una dirección y se guarda en la partida: corta y sin nada raro.
            $this->assertMatchesRegularExpression('/^[a-z0-9-]{1,40}$/', $clave);
            $this->assertNotSame('', trim($ficha['titulo']), $clave);
            $this->assertNotSame('', trim($ficha['consigna']), $clave);
            $this->assertNotNull(Nivel::tryFrom($ficha['nivel']), $clave);
            $this->assertContains($ficha['mano'], ['vos', 'bot'], $clave);
            $this->assertTrue(Objetivos::estaBienEscrito($ficha['objetivo']), "El objetivo de {$clave} está mal escrito.");

            // Tres cartas para cada uno, todas del mazo y ninguna repetida. Si algo está mal, el motor no la arma.
            $this->assertSame(Fase::Jugando, Desafios::motor($ficha)->fase(), $clave);
            $this->assertCount(6, array_unique(array_merge(...Desafios::manos($ficha))), $clave);
            $this->assertSame([$ficha['tanteo']['vos'], $ficha['tanteo']['bot']], Desafios::motor($ficha)->tanteo(), $clave);
            $this->assertSame($ficha['mano'] === 'vos' ? Mesa::JUGADOR : Mesa::BOT, Desafios::motor($ficha)->mano(), $clave);
        }
    }

    public function test_hay_ocho_desafios_sueltos_de_los_cuatro_temas(): void
    {
        $sueltos = Desafios::sueltos();

        $this->assertCount(8, $sueltos);
        $this->assertEqualsCanonicalizing(['Envido', 'Truco', 'Flor', 'Pardas'], array_values(array_unique(array_column($sueltos, 'tema'))));
        $this->assertNull(Desafios::de('uno-que-no-existe'));
        $this->assertSame('Parda la primera', Desafios::de('parda-la-primera')['titulo']);
    }

    public function test_el_mismo_desafio_arranca_siempre_igual(): void
    {
        foreach (Desafios::todos() as $ficha) {
            // No hay nada sorteado: las cartas, el tanteo y el mano son los de la ficha, todas las veces.
            $this->assertSame(Desafios::motor($ficha)->aArray(), Desafios::motor($ficha)->aArray(), $ficha['clave']);
            $this->assertSame(Desafios::manos($ficha), Desafios::motor($ficha)->aArray()['cartas'], $ficha['clave']);
        }
    }

    public function test_cada_desafio_tiene_solucion_y_no_sale_solo(): void
    {
        foreach (Desafios::todos() as $ficha) {
            $resultado = $this->resolver($ficha);

            $this->assertGreaterThan(0, $resultado['cumplen'], "{$ficha['clave']} no tiene solución.");
            $this->assertLessThan($resultado['lineas'], $resultado['cumplen'], "{$ficha['clave']} sale jugando de cualquier manera.");
        }
    }

    public function test_el_bot_de_un_desafio_contesta_siempre_lo_mismo_a_las_mismas_jugadas(): void
    {
        // También contra los niveles que mienten y sacan cuentas al azar: su azar sale de la clave y de la jugada.
        foreach (['vale-cuatro', 'al-resto', 'los-tres-que-faltan'] as $clave) {
            $una = $this->resolver(Desafios::de($clave));
            $otra = $this->resolver(Desafios::de($clave));

            $this->assertSame([$una['lineas'], $una['cumplen']], [$otra['lineas'], $otra['cumplen']], $clave);
            $this->assertEquals($una['cumple'], $otra['cumple'], $clave);
        }
    }

    public function test_un_desafio_se_define_con_datos_y_el_objetivo_cambia_el_resultado(): void
    {
        // Una mano que no está en el catálogo, escrita acá mismo, con dos objetivos distintos. Nada de esto
        // tiene código propio: es la misma ficha con otra condición.
        $mano = [
            'clave' => 'de-prueba', 'nivel' => Nivel::Intermedio->value,
            'tanteo' => ['vos' => 3, 'bot' => 5], 'mano' => 'vos',
            'cartas' => ['vos' => ['1-espada', '7-espada', '4-copa'], 'bot' => ['5-oro', '6-basto', '10-copa']],
        ];
        $ganarLaMano = [...$mano, 'objetivo' => [['que' => 'ganar_la_mano']]];
        $sumarCuatro = [...$mano, 'objetivo' => [['que' => 'sumar', 'puntos' => 4]]];

        // Se juega igual las dos veces: las dos más altas, sin cantar nada. Gana la mano y suma 1.
        $motor = Desafios::motor($mano);
        $hechos = $motor->hechos();

        foreach ([[Mesa::JUGADOR, '1-espada'], [Mesa::BOT, '5-oro'], [Mesa::JUGADOR, '7-espada'], [Mesa::BOT, '6-basto']] as [$asiento, $carta]) {
            $motor = $motor->aplicar($asiento, Accion::jugar(Carta::de($carta)));
            $hechos = [...$hechos, ...$motor->hechos()];
        }

        $this->assertSame([4, 5], $motor->tanteo());
        $this->assertTrue(Objetivos::cumple($ganarLaMano['objetivo'], $hechos, $motor));
        $this->assertFalse(Objetivos::cumple($sumarCuatro['objetivo'], $hechos, $motor));

        // Y cada una tiene su propia cuenta de soluciones.
        $this->assertNotSame($this->resolver($ganarLaMano)['cumplen'], $this->resolver($sumarCuatro)['cumplen']);
    }

    public function test_cada_condicion_mira_lo_que_paso_en_la_mano(): void
    {
        // El bot canta truco de entrada y la persona no quiere: la mano es del bot, que suma 1.
        $mano = [
            'clave' => 'de-prueba', 'nivel' => Nivel::Intermedio->value,
            'tanteo' => ['vos' => 0, 'bot' => 0], 'mano' => 'bot',
            'cartas' => ['vos' => ['4-copa', '5-oro', '10-basto'], 'bot' => ['1-espada', '3-oro', '2-oro']],
        ];
        $motor = Desafios::motor($mano)->aplicar(Mesa::BOT, Accion::de(TipoDeAccion::Truco));
        $hechos = [...Desafios::motor($mano)->hechos(), ...$motor->hechos()];
        $motor = $motor->aplicar(Mesa::JUGADOR, Accion::de(TipoDeAccion::NoQuiero));
        $hechos = [...$hechos, ...$motor->hechos()];

        $cumple = fn (array $condicion) => Objetivos::cumple([$condicion], $hechos, $motor);

        $this->assertFalse($cumple(['que' => 'ganar_la_mano']));
        $this->assertFalse($cumple(['que' => 'ganar_la_partida']));
        $this->assertFalse($cumple(['que' => 'ganar_el_envido']));
        $this->assertFalse($cumple(['que' => 'sumar', 'puntos' => 1]));
        $this->assertTrue($cumple(['que' => 'que_no_sume', 'mas_de' => 1]));
        $this->assertFalse($cumple(['que' => 'que_no_sume', 'mas_de' => 0]));
        // Quien no quiso fue la persona, no el bot.
        $this->assertFalse($cumple(['que' => 'que_no_quiera', 'canto' => 'truco']));

        // Una mano sin cerrar no cumple nada, y un objetivo vacío tampoco.
        $this->assertFalse(Objetivos::cumple([['que' => 'que_no_sume', 'mas_de' => 9]], [], Desafios::motor($mano)));
        $this->assertFalse(Objetivos::cumple([], $hechos, $motor));

        // Lo que no existe o viene incompleto no pasa por un objetivo bien escrito.
        $this->assertFalse(Objetivos::estaBienEscrito([['que' => 'ganar_como_sea']]));
        $this->assertFalse(Objetivos::estaBienEscrito([['que' => 'sumar']]));
        $this->assertFalse(Objetivos::estaBienEscrito([['que' => 'que_no_quiera', 'canto' => 'flor']]));
        $this->assertFalse(Objetivos::estaBienEscrito([]));
    }

    public function test_la_escalera_tiene_ocho_escalones_que_suben_de_nivel(): void
    {
        $escalones = Escalera::escalones();

        $this->assertCount(8, $escalones);
        $this->assertSame(range(1, 8), array_column($escalones, 'numero'));
        $this->assertNull(Escalera::escalon(0));
        $this->assertNull(Escalera::escalon(9));

        $partidas = array_values(array_filter($escalones, fn (array $escalon) => $escalon['tipo'] === Escalera::PARTIDA));
        $manos = array_values(array_filter($escalones, fn (array $escalon) => $escalon['tipo'] === Escalera::MANO));

        // Las partidas van de fácil a ultra difícil, cada una contra alguien del club, con su carta.
        $this->assertSame([Nivel::Facil, Nivel::Intermedio, Nivel::Dificil, Nivel::UltraDificil], array_column($partidas, 'nivel'));
        $this->assertSame(Nivel::UltraDificil, end($escalones)['nivel']);

        foreach ($partidas as $partida) {
            $this->assertSame(JugadoresDeEjemplo::lista()[$partida['rival']], $partida['nivel']);
            $this->assertSame(JugadoresDeEjemplo::cartas()[$partida['rival']], $partida['carta']);
        }

        // Las manos son desafíos del catálogo que no se juegan sueltos: la única manera de llegar es subiendo.
        $this->assertCount(4, $manos);

        foreach ($manos as $mano) {
            $this->assertFalse(Desafios::de($mano['desafio'])['suelto'], $mano['desafio']);
        }

        // Y toda mano que no es suelta ocupa un escalón.
        $this->assertEqualsCanonicalizing(
            array_column(array_filter(Desafios::todos(), fn (array $ficha) => ! $ficha['suelto']), 'clave'),
            array_column($manos, 'desafio'),
        );
    }
}
