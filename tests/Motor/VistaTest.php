<?php

namespace Tests\Motor;

use App\Motor\Partida;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Lo que recibe cada jugador. De acá sale lo que viaja al navegador: si una carta ajena
 * aparece en una vista, el rival la puede leer aunque la pantalla no la dibuje.
 */
class VistaTest extends TestCase
{
    use Jugando;
    use Simulando;

    private const CARTA = '/\b(?:1[0-2]|[1-7])-(?:espada|basto|oro|copa)\b/';

    public function test_cada_asiento_ve_sus_cartas_y_de_los_demas_solo_cuantas_quedan(): void
    {
        $partida = $this->mano();

        $this->assertSame(['7-oro', '6-oro', '1-espada'], $partida->vistaPara(0)['misCartas']);
        $this->assertSame(['12-copa', '5-copa', '3-basto'], $partida->vistaPara(1)['misCartas']);
        $this->assertSame([3, 3], $partida->vistaPara(0)['cartasEnMano']);
        $this->assertSame([], $this->cartasAjenasEn($partida, 0));
        $this->assertSame([], $this->cartasAjenasEn($partida, 1));
    }

    public function test_una_carta_jugada_la_ven_todos(): void
    {
        $partida = $this->jugar($this->mano(), '0 1-espada');

        foreach ([0, 1, null] as $asiento) {
            $vista = $partida->vistaPara($asiento);

            $this->assertSame([[0, '1-espada']], $vista['bazas'][0]['jugadas']);
            $this->assertSame([2, 3], $vista['cartasEnMano']);
        }
    }

    public function test_son_buenas_no_revela_el_tanto_del_que_pierde(): void
    {
        $partida = $this->jugar($this->mano(), '0 envido', '1 quiero');

        // El asiento 1 tiene 25: ese número no puede aparecer en lo que recibe el 0.
        $this->assertStringNotContainsString('25', json_encode($partida->vistaPara(0)));
        $this->assertSame([['asiento' => 0, 'tanto' => 33], ['asiento' => 1, 'tanto' => null]], $partida->vistaPara(0)['envido']['tantos']);
    }

    public function test_al_cerrar_la_mano_se_ven_las_cartas_del_tanto_que_gano_y_ninguna_otra(): void
    {
        $partida = $this->jugar($this->mano(), '0 envido', '1 quiero', '0 mazo');

        $loQueVeElUno = $this->cartasEn($partida->vistaPara(1));

        $this->assertContains('7-oro', $loQueVeElUno);
        $this->assertContains('6-oro', $loQueVeElUno);
        $this->assertNotContains('1-espada', $loQueVeElUno, 'La tercera carta del ganador vuelve al mazo boca abajo.');
        $this->assertSame([], array_intersect(['12-copa', '5-copa', '3-basto'], $this->cartasEn($partida->vistaPara(0))));
    }

    public function test_un_espectador_no_ve_la_mano_de_nadie_ni_puede_hacer_nada(): void
    {
        $partida = $this->jugar($this->mano(), '0 1-espada');
        $vista = $partida->vistaPara();

        $this->assertNull($vista['asiento']);
        $this->assertSame([], $vista['misCartas']);
        $this->assertSame([], $vista['acciones']);
        $this->assertSame(['1-espada'], $this->cartasEn($vista));
    }

    public function test_la_vista_trae_las_acciones_validas_de_ese_asiento_y_de_ninguno_mas(): void
    {
        $partida = $this->jugar($this->mano(), '0 truco');

        $this->assertSame([], $partida->vistaPara(0)['acciones']);
        $this->assertContains(['tipo' => 'quiero'], $partida->vistaPara(1)['acciones']);
        $this->assertSame(['canto' => 'truco', 'responde' => 1], $partida->vistaPara(0)['pendiente']);
    }

    public function test_la_vista_es_un_arreglo_plano_que_se_puede_mandar_como_json(): void
    {
        $vista = $this->jugar($this->mano(), '0 envido', '1 quiero', '0 1-espada')->vistaPara(1);

        $this->assertSame($vista, json_decode(json_encode($vista, JSON_THROW_ON_ERROR), true));
    }

    public function test_no_hay_vista_para_un_asiento_que_no_existe(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->mano()->vistaPara(2);
    }

    /**
     * La garantía de fondo: en partidas enteras jugadas al azar, de a dos y de a cuatro,
     * ninguna vista trae jamás una carta ajena que no esté jugada o mostrada.
     */
    public function test_en_partidas_enteras_ninguna_vista_trae_una_carta_ajena_sin_jugar_ni_mostrar(): void
    {
        $revisadas = 0;

        foreach ([2, 4] as $asientos) {
            foreach (range(1, 15) as $semilla) {
                $this->simular($semilla, $asientos, enCadaPaso: function (Partida $partida) use ($asientos, $semilla, &$revisadas): void {
                    foreach ([...range(0, $asientos - 1), null] as $asiento) {
                        $this->assertSame(
                            [],
                            $this->cartasAjenasEn($partida, $asiento),
                            "Semilla {$semilla}, {$asientos} asientos: la vista de ".($asiento ?? 'un espectador').' trae cartas que no puede ver.',
                        );
                        $revisadas++;
                    }
                });
            }
        }

        $this->assertGreaterThan(3000, $revisadas, 'Las partidas simuladas fueron demasiado cortas para probar algo.');
    }

    /**
     * Las cartas que aparecen en la vista de un asiento y que ese asiento no tendría que conocer.
     *
     * @return list<string>
     */
    private function cartasAjenasEn(Partida $partida, ?int $asiento): array
    {
        $estado = $partida->aArray();
        $puedeVer = $asiento === null ? [] : ($estado['repartidas'][$asiento] ?? []);

        foreach ($estado['bazas'] as $baza) {
            $puedeVer = [...$puedeVer, ...array_column($baza['jugadas'], 1)];
        }

        foreach ($estado['cierre']['mostradas'] ?? [] as $mostradas) {
            $puedeVer = [...$puedeVer, ...$mostradas];
        }

        return array_values(array_diff($this->cartasEn($partida->vistaPara($asiento)), $puedeVer));
    }

    /**
     * Todas las cartas que nombra una vista, esté donde esté el dato.
     *
     * @param  array<string, mixed>  $vista
     * @return list<string>
     */
    private function cartasEn(array $vista): array
    {
        preg_match_all(self::CARTA, json_encode($vista, JSON_THROW_ON_ERROR), $encontradas);

        return array_values(array_unique($encontradas[0]));
    }

    private function mano(): Partida
    {
        return $this->armada([['7-oro', '6-oro', '1-espada'], ['12-copa', '5-copa', '3-basto']]);
    }
}
