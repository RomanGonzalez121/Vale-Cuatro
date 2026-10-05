<?php

namespace Tests\Motor;

use App\Motor\Accion;
use App\Motor\Fase;
use App\Motor\Mazo;
use App\Motor\TipoDeAccion;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Una mano jugada con cartas, sin cantos: turnos, bazas, pardas, tanteo y cierre de la partida.
 */
class ManoTest extends TestCase
{
    use Jugando;

    private const FUERTE = ['1-espada', '1-basto', '7-espada'];

    private const DEBIL = ['4-copa', '5-copa', '6-basto'];

    public function test_despues_de_la_primera_baza_sale_quien_la_gano(): void
    {
        $partida = $this->armada([self::DEBIL, self::FUERTE]);

        $partida = $this->jugar($partida, '0 4-copa');
        $this->assertSame(1, $partida->turno());

        $partida = $this->jugar($partida, '1 7-espada');
        $this->assertSame(1, $partida->turno(), 'Ganó el asiento 1: le toca salir.');
    }

    public function test_despues_de_una_parda_sale_el_mano(): void
    {
        $partida = $this->armada([['3-copa', '4-copa', '5-copa'], ['3-oro', '7-espada', '6-basto']], mano: 1);

        $partida = $this->jugar($partida, '1 3-oro', '0 3-copa');

        $this->assertSame(1, $partida->turno());
    }

    public function test_gana_la_mano_quien_gana_dos_bazas_y_suma_un_punto(): void
    {
        $partida = $this->armada([self::FUERTE, self::DEBIL]);

        $partida = $this->jugar($partida, '0 1-espada', '1 4-copa', '0 1-basto', '1 5-copa');

        $this->assertSame(Fase::PorRepartir, $partida->fase());
        $this->assertSame([1, 0], $partida->tanteo());
        $this->assertSame(0, $partida->cierre()['ganador']);
        $this->assertSame('bazas', $partida->cierre()['motivo']);
        $this->assertSame([['equipo' => 0, 'puntos' => 1, 'concepto' => 'mano']], $partida->cierre()['anotado']);
    }

    public function test_con_una_y_una_se_juega_la_tercera(): void
    {
        $partida = $this->armada([['1-espada', '4-oro', '7-oro'], ['4-copa', '7-espada', '6-basto']]);

        $partida = $this->jugar($partida, '0 1-espada', '1 4-copa', '0 4-oro', '1 7-espada');
        $this->assertSame(Fase::Jugando, $partida->fase());

        $partida = $this->jugar($partida, '1 6-basto', '0 7-oro');
        $this->assertSame([1, 0], $partida->tanteo());
    }

    /**
     * La tabla de pardas del reglamento, cada fila jugada de punta a punta.
     *
     * @param  list<list<string>>  $manos
     * @param  list<string>  $jugadas
     */
    #[DataProvider('tablaDePardas')]
    public function test_tabla_de_pardas(array $manos, int $mano, array $jugadas, int $gana): void
    {
        $partida = $this->jugar($this->armada($manos, $mano), ...$jugadas);

        $this->assertSame(Fase::PorRepartir, $partida->fase());
        $this->assertSame($gana, $partida->cierre()['ganador']);
    }

    public static function tablaDePardas(): array
    {
        return [
            'parda la primera: gana quien gana la segunda' => [
                [['3-oro', '4-copa', '5-copa'], ['3-copa', '7-espada', '6-basto']], 0,
                ['0 3-oro', '1 3-copa', '0 4-copa', '1 7-espada'], 1,
            ],
            'pardas la primera y la segunda: gana quien gana la tercera' => [
                [['3-oro', '2-oro', '4-copa'], ['3-copa', '2-copa', '5-basto']], 0,
                ['0 3-oro', '1 3-copa', '0 2-oro', '1 2-copa', '0 4-copa', '1 5-basto'], 1,
            ],
            'pardas las tres: gana el mano' => [
                [['3-oro', '2-oro', '4-oro'], ['3-copa', '2-copa', '4-copa']], 1,
                ['1 3-copa', '0 3-oro', '1 2-copa', '0 2-oro', '1 4-copa', '0 4-oro'], 1,
            ],
            'parda la segunda: gana quien ganó la primera' => [
                [['7-espada', '2-oro', '4-copa'], ['3-copa', '2-copa', '1-espada']], 0,
                ['0 7-espada', '1 3-copa', '0 2-oro', '1 2-copa'], 0,
            ],
            'parda la tercera con una y una: gana quien ganó la primera' => [
                [['7-espada', '4-copa', '2-oro'], ['3-copa', '5-basto', '2-copa']], 0,
                ['0 7-espada', '1 3-copa', '0 4-copa', '1 5-basto', '1 2-copa', '0 2-oro'], 0,
            ],
        ];
    }

    public function test_el_mano_alterna_en_cada_mano(): void
    {
        $partida = $this->armada([self::FUERTE, self::DEBIL], mano: 0);
        $partida = $this->jugar($partida, '0 1-espada', '1 4-copa', '0 1-basto', '1 5-copa');

        $this->assertSame(1, $partida->mano());

        $partida = $this->jugar($partida->repartir(Mazo::mezclado(5)), '1 mazo');

        $this->assertSame(0, $partida->mano());
    }

    public function test_irse_al_mazo_en_la_primera_baza_sin_envido_le_da_dos_al_rival(): void
    {
        $partida = $this->jugar($this->armada([self::FUERTE, self::DEBIL]), '0 1-espada', '1 mazo');

        $this->assertSame([2, 0], $partida->tanteo());
        $this->assertSame('mazo', $partida->cierre()['motivo']);
        $this->assertSame(
            [['equipo' => 0, 'puntos' => 1, 'concepto' => 'envido_no_jugado'], ['equipo' => 0, 'puntos' => 1, 'concepto' => 'mano']],
            $partida->cierre()['anotado'],
        );
    }

    public function test_irse_al_mazo_despues_de_la_primera_baza_le_da_uno_al_rival(): void
    {
        $partida = $this->jugar($this->armada([self::FUERTE, self::DEBIL]), '0 1-espada', '1 4-copa', '0 mazo');

        $this->assertSame([0, 1], $partida->tanteo());
    }

    public function test_la_partida_termina_en_cuanto_alguien_llega_a_treinta(): void
    {
        $partida = $this->armada([self::FUERTE, self::DEBIL], tanteo: [29, 12]);
        $partida = $this->jugar($partida, '0 1-espada', '1 4-copa', '0 1-basto', '1 5-copa');

        $this->assertSame(Fase::Terminada, $partida->fase());
        $this->assertSame(0, $partida->ganador());
        $this->assertSame([30, 12], $partida->tanteo());
    }

    public function test_el_tanteo_no_pasa_de_los_puntos_de_la_partida(): void
    {
        $partida = $this->jugar($this->armada([self::FUERTE, self::DEBIL], tanteo: [29, 0]), '0 1-espada', '1 mazo');

        $this->assertSame([30, 0], $partida->tanteo());
        $this->assertSame(Fase::Terminada, $partida->fase());
    }

    public function test_los_puntos_de_la_partida_son_un_parametro(): void
    {
        $partida = $this->armada([self::FUERTE, self::DEBIL], tanteo: [14, 3], puntos: 15);
        $partida = $this->jugar($partida, '0 1-espada', '1 4-copa', '0 1-basto', '1 5-copa');

        $this->assertSame(Fase::Terminada, $partida->fase());
        $this->assertSame([15, 3], $partida->tanteo());
    }

    public function test_no_se_arma_una_situacion_con_la_partida_ya_ganada(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->armada([self::FUERTE, self::DEBIL], tanteo: [30, 0]);
    }

    public function test_terminada_la_partida_no_se_juega_ni_se_reparte(): void
    {
        $partida = $this->jugar($this->armada([self::FUERTE, self::DEBIL], tanteo: [29, 0]), '0 1-espada', '1 mazo');

        $this->assertSame('La partida ya terminó.', $this->rechazo($partida, '0 1-basto'));
        $this->assertSame([], $partida->accionesPara(0));
        $this->assertSame([], $partida->accionesPara(1));

        $this->expectExceptionMessage('ya terminó');
        $partida->repartir(Mazo::mezclado(1));
    }

    public function test_no_se_juega_fuera_de_turno(): void
    {
        $partida = $this->armada([self::FUERTE, self::DEBIL]);

        $this->assertSame('No es tu turno.', $this->rechazo($partida, '1 4-copa'));
        $this->assertSame('No es tu turno.', $this->rechazo($partida, '1 mazo'));
        $this->assertSame([], $partida->accionesPara(1));
    }

    public function test_no_se_juega_una_carta_que_no_se_tiene(): void
    {
        $partida = $this->armada([self::FUERTE, self::DEBIL]);

        $this->assertSame('Esa carta no está en tu mano.', $this->rechazo($partida, '0 4-copa'));
        $this->assertSame('Esa carta no está en tu mano.', $this->rechazo($partida, '0 3-oro'));
    }

    public function test_no_se_juega_dos_veces_la_misma_carta(): void
    {
        $partida = $this->jugar($this->armada([self::FUERTE, self::DEBIL]), '0 1-espada', '1 4-copa');

        $this->assertSame('Esa carta no está en tu mano.', $this->rechazo($partida, '0 1-espada'));
    }

    public function test_con_la_mano_cerrada_hay_que_repartir(): void
    {
        $partida = $this->jugar($this->armada([self::FUERTE, self::DEBIL]), '0 mazo');

        $this->assertSame('La mano está cerrada: falta repartir.', $this->rechazo($partida, '1 4-copa'));
    }

    public function test_un_asiento_que_no_existe_se_rechaza(): void
    {
        $partida = $this->armada([self::FUERTE, self::DEBIL]);

        $this->assertSame('El asiento 2 no existe en esta mesa.', $this->rechazo($partida, '2 mazo'));
        $this->assertSame([], $partida->accionesPara(2));
    }

    public function test_una_accion_rechazada_no_deja_rastro(): void
    {
        $partida = $this->armada([self::FUERTE, self::DEBIL]);
        $foto = $partida->aArray();

        $this->rechazo($partida, '1 4-copa');

        $this->assertSame($foto, $partida->aArray());
    }

    public function test_en_el_turno_se_puede_jugar_una_carta_propia_cantar_o_irse(): void
    {
        $partida = $this->jugar($this->armada([self::FUERTE, self::DEBIL]), '0 1-espada', '1 4-copa');

        $this->assertSame(['1-basto', '7-espada', 'truco', 'mazo'], $this->opciones($partida, 0));
    }

    public function test_cada_accion_cuenta_lo_que_paso(): void
    {
        $partida = $this->jugar($this->armada([self::FUERTE, self::DEBIL]), '0 1-espada');
        $this->assertSame([['tipo' => 'carta', 'asiento' => 0, 'carta' => '1-espada']], $partida->hechos());

        $partida = $this->jugar($partida, '1 4-copa');
        $this->assertSame(
            [['tipo' => 'carta', 'asiento' => 1, 'carta' => '4-copa'], ['tipo' => 'baza', 'numero' => 1, 'ganador' => 0]],
            $partida->hechos(),
        );
    }

    public function test_una_accion_se_guarda_como_arreglo_y_vuelve_igual(): void
    {
        $partida = $this->armada([self::FUERTE, self::DEBIL]);

        foreach ($partida->accionesPara(0) as $accion) {
            $this->assertEquals($accion, Accion::desdeArray($accion->aArray()));
        }

        $this->assertSame(['tipo' => 'mazo'], Accion::de(TipoDeAccion::Mazo)->aArray());
    }

    public function test_una_accion_mal_escrita_se_rechaza(): void
    {
        foreach ([[], ['tipo' => 'bailar'], ['tipo' => 'jugar'], ['tipo' => 'jugar', 'carta' => '9-oro'], ['tipo' => 7]] as $datos) {
            try {
                Accion::desdeArray($datos);
                $this->fail('Una acción mal escrita no puede aceptarse.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
