<?php

namespace Tests\Unit;

use App\Juego\Desafios;
use App\Juego\Escalera;
use App\Juego\FosforosDeGira;
use App\Juego\Gira;
use App\Juego\JugadoresDeEjemplo;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Juego\Objetivos;
use App\Juego\Premios;
use App\Juego\Recuerda;
use App\Motor\Fase;
use App\Motor\Partida as Motor;
use PHPUnit\Framework\TestCase;

/**
 * La gira de los clubes como datos: que cada barrio esté bien armado, que Boedo sea la escalera de siempre,
 * que cada condición de fósforo se sepa contar, y que en cada mano con objetivo se puedan ganar los tres.
 */
class GiraTest extends TestCase
{
    public function test_cada_barrio_esta_bien_armado(): void
    {
        $barrios = Gira::barrios();

        $this->assertSame(['boedo', 'san-telmo', 'la-boca', 'pompeya', 'mataderos', 'abasto'], array_column($barrios, 'clave'));

        // Boedo es la escalera: sus niveles son los escalones, uno por uno, y su jefe es el último.
        $this->assertCount(Escalera::cuantos(), $barrios[0]['niveles']);
        $this->assertSame(range(1, Escalera::cuantos()), array_column($barrios[0]['niveles'], 'escalon'));
        $this->assertSame('jefe', end($barrios[0]['niveles'])['tipo']);

        foreach (array_slice($barrios, 1) as $barrio) {
            $tipos = array_column($barrio['niveles'], 'tipo');
            $nombre = $barrio['nombre'];

            // Seis niveles: dos partidas mano a mano, una de a cuatro, una mano con objetivo, uno especial y el jefe al final.
            $this->assertCount(6, $tipos, $nombre);
            $this->assertSame('jefe', end($tipos), $nombre);
            $this->assertSame(1, count(array_keys($tipos, 'cuatro', true)), $nombre);
            $this->assertSame(1, count(array_keys($tipos, 'mano', true)), $nombre);
            $this->assertSame(1, count(array_intersect($tipos, ['serie', 'copa', 'abajo'])), $nombre);
            $this->assertGreaterThanOrEqual(2, count(array_keys($tipos, 'partida', true)), $nombre);

            $this->assertArrayHasKey($barrio['mesa'], Premios::POR_NIVEL['mesa'], $nombre);
            $this->assertArrayHasKey($barrio['jefe']['dorso'], Premios::DORSOS, $nombre);
            $this->assertArrayHasKey($barrio['jefe']['mazo'], Premios::POR_NIVEL['mazo'], $nombre);
            $this->assertNotNull(Nivel::tryFrom($barrio['jefe']['nivel']), $nombre);
        }

        foreach ($barrios as $barrio) {
            foreach ($barrio['niveles'] as $nivel) {
                $donde = "{$barrio['nombre']}, nivel {$nivel['numero']}";

                $this->assertTrue(FosforosDeGira::estaBienEscrita($nivel['segundo']), "{$donde}: el segundo fósforo.");
                $this->assertTrue(FosforosDeGira::estaBienEscrita($nivel['tercero']), "{$donde}: el tercer fósforo.");
                $this->assertNotEquals($nivel['segundo'], $nivel['tercero'], "{$donde}: los dos fósforos piden lo mismo.");
                $this->assertNotNull(Nivel::tryFrom($nivel['nivel']), $donde);

                if (isset($nivel['rival'])) {
                    $this->assertArrayHasKey($nivel['rival'], JugadoresDeEjemplo::lista(), $donde);
                }

                if ($nivel['tipo'] === 'mano') {
                    $this->assertNotNull(Desafios::de($nivel['desafio']), $donde);
                }
            }
        }

        $this->assertSame(38, Gira::cuantos());
    }

    public function test_los_premios_que_dejan_los_jefes_estan_en_la_coleccion(): void
    {
        foreach (Gira::barrios() as $barrio) {
            foreach ($barrio['jefe']['premios'] ?? [] as [$tipo, $clave]) {
                $premio = $tipo === 'dorso' ? Premios::DORSOS[$clave] : Premios::POR_NIVEL[$tipo][$clave];

                $this->assertArrayHasKey('gira', $premio, "{$tipo} {$clave} se gana en la gira.");
            }
        }
    }

    public function test_los_fosforos_se_cuentan_desde_los_hechos(): void
    {
        $nivel = ['segundo' => ['por', 8], 'tercero' => ['sin_mazo']];
        $hechos = [
            ['tipo' => 'reparto'],
            ['tipo' => 'canto', 'asiento' => 0, 'canto' => 'truco'],
            ['tipo' => 'respuesta', 'asiento' => 1, 'canto' => 'truco', 'quiere' => true],
            ['tipo' => 'puntos', 'equipo' => 0, 'puntos' => 2, 'concepto' => 'truco', 'tanteo' => [2, 0]],
        ];

        // No pasarlo no da nada, aunque se cumpla lo demás.
        $this->assertSame(0, FosforosDeGira::cuantos($nivel, false, $hechos, [0, 0], [15, 0]));
        // Pasarlo da uno; por ocho o más, otro; sin irse al mazo, el tercero.
        $this->assertSame(3, FosforosDeGira::cuantos($nivel, true, $hechos, [0, 0], [15, 6]));
        $this->assertSame(2, FosforosDeGira::cuantos($nivel, true, $hechos, [0, 0], [15, 9]));
        $this->assertSame(1, FosforosDeGira::cuantos($nivel, true, [...$hechos, ['tipo' => 'mazo', 'asiento' => 0]], [0, 0], [15, 9]));
        // Que se vaya al mazo el rival no le quita nada a nadie.
        $this->assertSame(3, FosforosDeGira::cuantos($nivel, true, [...$hechos, ['tipo' => 'mazo', 'asiento' => 1]], [0, 0], [15, 0]));

        // Un truco querido ganado; un envido ganado y otro perdido.
        $cuenta = FosforosDeGira::contar([
            ...$hechos,
            ['tipo' => 'tantos', 'canto' => 'envido', 'ganador' => 0, 'tantos' => []],
            ['tipo' => 'reparto'],
            ['tipo' => 'tantos', 'canto' => 'envido', 'ganador' => 1, 'tantos' => []],
        ]);
        $this->assertSame([1, 1, 1], [$cuenta['trucos_ganados'], $cuenta['envidos_ganados'], $cuenta['envidos_perdidos']]);
        $this->assertSame(['truco'], $cuenta['cantados']);

        // Una partida que arrancó abajo se mide por lo que sumó, y "por" mira el final.
        $this->assertSame(3, FosforosDeGira::cuantos(['segundo' => ['sumar', 15], 'tercero' => ['por', 4]], true, [], [0, 8], [15, 11]));
        $this->assertSame(2, FosforosDeGira::cuantos(['segundo' => ['sumar', 15], 'tercero' => ['por', 4]], true, [], [0, 8], [15, 12]));
    }

    /**
     * En una mano con objetivo el bot contesta siempre lo mismo: se puede recorrer entera. Alguna manera de
     * jugarla tiene que dar los tres fósforos; si no, el nivel pediría algo imposible.
     */
    public function test_en_cada_mano_de_la_gira_se_pueden_ganar_los_tres_fosforos(): void
    {
        foreach (Gira::barrios() as $barrio) {
            foreach ($barrio['niveles'] as $nivel) {
                if ($nivel['tipo'] !== 'mano') {
                    continue;
                }

                $ficha = Desafios::de($nivel['desafio']);
                $mejor = $this->mejorIntento($ficha, $nivel);

                $this->assertSame(3, $mejor, "{$barrio['nombre']}, nivel {$nivel['numero']} ({$ficha['titulo']}): como mucho da {$mejor}.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $ficha
     * @param  array<string, mixed>  $nivel
     */
    private function mejorIntento(array $ficha, array $nivel): int
    {
        $motor = Desafios::motor($ficha);
        $hechos = $motor->hechos();

        foreach (Desafios::jugadas($ficha) as [$asiento, $accion]) {
            $motor = $motor->aplicar($asiento, $accion);
            $hechos = [...$hechos, ...$motor->hechos()];
        }

        return $this->explorar($ficha, $nivel, $motor, 1 + count($ficha['jugadas'] ?? []), $hechos);
    }

    /**
     * @param  array<string, mixed>  $ficha
     * @param  array<string, mixed>  $nivel
     * @param  list<array<string, mixed>>  $hechos
     */
    private function explorar(array $ficha, array $nivel, Motor $motor, int $evento, array $hechos): int
    {
        while ($motor->fase() === Fase::Jugando && $motor->accionesPara(Mesa::JUGADOR) === [] && $motor->accionesPara(Mesa::BOT) !== []) {
            $evento++;
            $bot = Nivel::from($ficha['nivel'])->bot(Desafios::azar($ficha['clave'], $evento));

            if ($bot instanceof Recuerda) {
                $bot->recordar([]);
            }

            $motor = $motor->aplicar(Mesa::BOT, $bot->decidir($motor->vistaPara(Mesa::BOT)));
            $hechos = [...$hechos, ...$motor->hechos()];
        }

        if ($motor->fase() !== Fase::Jugando) {
            $paso = Objetivos::cumple($ficha['objetivo'], $hechos, $motor);

            return FosforosDeGira::cuantos($nivel, $paso, $hechos, Desafios::tanteo($ficha), $motor->tanteo());
        }

        $mejor = 0;

        foreach ($motor->accionesPara(Mesa::JUGADOR) as $accion) {
            $sigue = $motor->aplicar(Mesa::JUGADOR, $accion);
            $mejor = max($mejor, $this->explorar($ficha, $nivel, $sigue, $evento + 1, [...$hechos, ...$sigue->hechos()]));

            if ($mejor === FosforosDeGira::MAXIMO) {
                break;
            }
        }

        return $mejor;
    }
}
