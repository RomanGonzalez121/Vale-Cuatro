<?php

namespace Tests\Feature;

use App\Juego\Bot;
use App\Juego\Camino;
use App\Juego\Estadisticas;
use App\Juego\Historial;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Resultado;
use App\Motor\Accion;
use App\Motor\Azar;
use App\Motor\Carta;
use App\Motor\Fase;
use App\Motor\TipoDeAccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * El gallito contra la máquina en la mesa del servidor: la persona en el asiento 0, la máquina de su
 * derecha en el 1 y la de su izquierda en el 2. En cada mano el gallo es el mano y juega solo.
 */
class MesaGallitoTest extends TestCase
{
    use RefreshDatabase;

    public function test_abrir_un_gallito_reparte_tres_cartas_a_cada_uno_de_los_tres(): void
    {
        Queue::fake();

        $jugador = Jugador::factory()->create();
        $partida = $this->mesa()->abrir($jugador, Nivel::Dificil, enSerie: true, asientos: 3);

        $this->assertTrue($partida->esGallito());
        $this->assertTrue($partida->enEquipos());
        $this->assertContains($partida->primer_mano, [0, 1, 2]);
        // En el gallito no hay series, aunque se pida.
        $this->assertNull($partida->serie_id);

        $manos = $partida->eventos()->sole()->datos['manos'];
        $this->assertCount(3, $manos);
        $this->assertCount(9, array_unique(array_merge(...$manos)));

        $vista = $this->mesa()->vista($partida);
        $this->assertSame([3, 0], [$vista['asientos'], $vista['asiento']]);
        $this->assertSame([0, 0, 0], $vista['tanteo']);
        $this->assertSame($manos[0], $vista['misCartas']);
    }

    public function test_se_juegan_gallitos_enteros_contra_cada_nivel_y_cuentan_para_el_nivel_y_la_libreta(): void
    {
        Exceptions::fake();

        foreach (Nivel::cases() as $numero => $nivel) {
            $jugador = Jugador::factory()->create();
            $partida = $this->mesa()->abrir($jugador, $nivel, asientos: 3);
            $azar = Azar::deSemilla(60 + $numero);

            for ($pedidos = 0; $partida->fresh()->enCurso(); $pedidos++) {
                $this->assertLessThan(4000, $pedidos, 'El gallito no termina.');
                $this->unPedidoAlAzar($partida, $azar);
            }

            $partida->refresh();
            $motor = $this->mesa()->reconstruir($partida);

            $this->assertSame(Partida::TERMINADA, $partida->estado);
            $this->assertContains($partida->ganador, [0, 1, 2], 'En el gallito gana un jugador.');
            $this->assertSame($partida->ganador, $motor->ganador());

            // Gana la persona si el ganador es su asiento: ganar 3 fósforos, perder 1.
            $gano = $partida->ganador === Mesa::JUGADOR;
            $this->assertSame($gano ? Camino::POR_GANAR : Camino::POR_PERDER, (new Camino)->fosforosEn($partida, Mesa::JUGADOR, $this->mesa()));
            $this->assertSame((int) $gano, $jugador->fresh()->gallitos_ganados);

            // No cuenta para el ranking.
            $this->assertFalse((new Estadisticas)->cuenta($partida));
        }

        $this->assertSame(0, Resultado::query()->count());
        Exceptions::assertNothingReported();
    }

    public function test_a_la_persona_no_le_llega_ninguna_carta_ajena_que_no_se_haya_jugado_o_mostrado(): void
    {
        $revisados = 0;

        foreach ([3, 4] as $semilla) {
            $partida = $this->mesa()->abrir(Jugador::factory()->create(), Nivel::Intermedio, asientos: 3);
            $azar = Azar::deSemilla($semilla);

            for ($pedidos = 0; $pedidos < 120 && $partida->fresh()->enCurso(); $pedidos++) {
                foreach ($this->unPedidoAlAzar($partida, $azar) as $paso) {
                    $this->assertSoloCartasQuePuedeVer($paso);
                    $revisados++;
                }
            }
        }

        $this->assertGreaterThan(100, $revisados);
    }

    public function test_cuando_el_gallo_le_canta_a_la_pareja_contesta_la_persona_y_su_companera_espera(): void
    {
        // Una máquina que canta truco apenas puede, y si no, tira una carta o quiere.
        $bot = new class implements Bot
        {
            public function decidir(array $vista): Accion
            {
                $acciones = collect($vista['acciones']);

                return Accion::desdeArray(
                    $acciones->firstWhere('tipo', TipoDeAccion::Truco->value)
                        ?? $acciones->firstWhere('tipo', TipoDeAccion::Jugar->value)
                        ?? $acciones->firstWhere('tipo', TipoDeAccion::Quiero->value),
                );
            }
        };

        // El gallo es la máquina de la derecha (asiento 1): la persona va en la pareja con la de la izquierda.
        $partida = $this->partidaArmada([
            ['12-espada', '11-copa', '4-copa'],
            ['2-oro', '6-basto', '5-basto'],
            ['7-oro', '6-oro', '4-basto'],
        ], gallo: 1);
        $mesa = new Mesa($bot);

        Queue::fake();
        // Sale el gallo y canta truco.
        $mesa->despertarAlBot($partida);
        $vista = $mesa->vista($partida->fresh());

        $this->assertSame('truco', $vista['pendiente']['canto']);
        $this->assertContains(TipoDeAccion::Quiero->value, array_column($vista['acciones'], 'tipo'));
        // El motor dejaría contestar a la compañera, pero no lo hace: espera a la persona.
        $this->assertFalse($mesa->turnoDelBot($partida->id));

        $mesa->actuar($partida, Accion::de(TipoDeAccion::Quiero), Mesa::JUGADOR);
        $this->assertTrue($mesa->turnoDelBot($partida->id));
    }

    public function test_el_historial_cuenta_los_tres_tanteos(): void
    {
        $jugador = Jugador::factory()->create();
        $partida = $this->mesa()->abrir($jugador, Nivel::Intermedio, asientos: 3);
        $this->mesa()->abandonar($partida);

        $fila = $this->app->make(Historial::class)->resumen($partida->fresh(), Mesa::JUGADOR);

        $this->assertSame(3, $fila['asientos']);
        $this->assertFalse($fila['gano'], 'Abandonar es perder.');
        $this->assertSame(['izquierda', 'derecha'], array_keys($fila['otros']));
        $this->assertSame('la máquina, nivel intermedio', $fila['rival']);
    }

    private function mesa(): Mesa
    {
        return $this->app->make(Mesa::class);
    }

    /**
     * Un gallito con las cartas que se indiquen y ese gallo de arranque, guardado como lo guardaría la mesa.
     *
     * @param  list<list<string>>  $manos  Las de cada asiento, del 0 al 2.
     */
    private function partidaArmada(array $manos, int $gallo): Partida
    {
        $partida = Partida::create([
            'jugador_id' => Jugador::factory()->create()->id,
            'primer_mano' => $gallo,
            'puntos' => 30,
            'asientos' => 3,
        ]);

        $partida->eventos()->create(['numero' => 1, 'tipo' => EventoDePartida::REPARTO, 'datos' => ['manos' => $manos], 'creado_en' => now()]);

        return $partida;
    }

    /**
     * Hace lo que haría alguien que toca cualquier botón válido: una acción al azar, o repartir si la mano
     * está cerrada. Con la cola corriendo en el momento, al volver ya jugaron las máquinas. Devuelve los pasos.
     *
     * @return list<array<string, mixed>>
     */
    private function unPedidoAlAzar(Partida $partida, Azar $azar): array
    {
        $mesa = $this->mesa();
        $vista = $mesa->vista($partida->fresh());

        if ($vista['fase'] === Fase::PorRepartir->value) {
            $mesa->repartir($partida);
        } elseif ($vista['acciones'] !== []) {
            $mesa->actuar($partida, Accion::desdeArray($vista['acciones'][$azar->entero(0, count($vista['acciones']) - 1)]));
        } else {
            $this->assertContains(Mesa::JUGADOR, $vista['enMazo'], 'La mano está en juego, la persona sigue en ella y no le toca: tendría que haber jugado una máquina.');
            $this->fail('La mano quedó esperando a alguien que no es la persona.');
        }

        return $mesa->pasosDesde($partida, $vista['evento']);
    }

    /**
     * @param  array<string, mixed>  $paso
     */
    private function assertSoloCartasQuePuedeVer(array $paso): void
    {
        $permitidas = [
            ...$paso['misCartas'],
            ...array_column(array_merge(...array_column($paso['bazas'], 'jugadas')), 1),
            ...array_merge([], ...array_values($paso['cierre']['mostradas'] ?? [])),
        ];

        preg_match_all('/\d+-(?:espada|basto|oro|copa)/', json_encode($paso), $nombradas);

        $this->assertSame([], array_values(array_diff(array_unique($nombradas[0]), $permitidas)), 'El paso nombra cartas que la persona no puede ver.');
    }
}
