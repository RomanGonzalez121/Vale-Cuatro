<?php

namespace Tests\Feature;

use App\Juego\Bot;
use App\Juego\Estadisticas;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Juego\RecibeSenas;
use App\Juego\Senas;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Resultado;
use App\Motor\Accion;
use App\Motor\AccionInvalida;
use App\Motor\Azar;
use App\Motor\Carta;
use App\Motor\Fase;
use App\Motor\TipoDeAccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * El truco de a cuatro contra bots en la mesa del servidor: la persona en el asiento 0, su compañero
 * bot enfrente (el 2) y dos bots rivales a los costados (el 1 y el 3).
 */
class MesaDeACuatroTest extends TestCase
{
    use RefreshDatabase;

    public function test_abrir_de_a_cuatro_reparte_tres_cartas_a_cada_uno_de_los_cuatro(): void
    {
        Queue::fake();

        $jugador = Jugador::factory()->create();
        $partida = $this->mesa()->abrir($jugador, Nivel::Dificil, enSerie: true, asientos: 4);

        $this->assertTrue($partida->deACuatro());
        $this->assertSame(30, $partida->puntos);
        $this->assertSame(Nivel::Dificil, $partida->nivel_bot);
        $this->assertContains($partida->primer_mano, [0, 1, 2, 3]);
        $this->assertFalse($partida->entre_personas);
        // De a cuatro no hay series, aunque se pida.
        $this->assertNull($partida->serie_id);

        $manos = $partida->eventos()->sole()->datos['manos'];

        $this->assertCount(4, $manos);
        $this->assertCount(12, array_unique(array_merge(...$manos)));

        // La persona ve cuatro asientos y solo sus tres cartas.
        $vista = $this->mesa()->vista($partida);

        $this->assertSame([4, 0], [$vista['asientos'], $vista['asiento']]);
        $this->assertSame($manos[0], $vista['misCartas']);
        $this->assertSame([3, 3, 3, 3], $vista['cartasEnMano']);
        // Con una partida de a cuatro sin terminar, "Jugar" la retoma: no abre otra.
        $this->assertTrue($this->mesa()->abrir($jugador, Nivel::Facil)->is($partida));
    }

    public function test_se_juegan_partidas_enteras_contra_cada_nivel_y_ningun_bot_falla(): void
    {
        // Si un bot fallara, la mesa juega algo válido por él y anota el error: acá no puede haber ninguno.
        Exceptions::fake();
        $jugaron = [];

        foreach ([Nivel::Facil, Nivel::Intermedio, Nivel::Dificil, Nivel::UltraDificil] as $numero => $nivel) {
            $partida = $this->mesa()->abrir(Jugador::factory()->create(), $nivel, asientos: 4);
            $azar = Azar::deSemilla(40 + $numero);

            for ($pedidos = 0; $partida->fresh()->enCurso(); $pedidos++) {
                $this->assertLessThan(4000, $pedidos, 'La partida no termina.');
                $this->unPedidoAlAzar($partida, $azar);
            }

            $partida->refresh();

            $this->assertSame(Partida::TERMINADA, $partida->estado);
            $this->assertContains($partida->ganador, [0, 1]);
            // El estado reconstruido desde los eventos es el de una partida terminada, con ese ganador.
            $motor = $this->mesa()->reconstruir($partida);
            $this->assertSame(Fase::Terminada, $motor->fase());
            $this->assertSame($partida->ganador, $motor->ganador());
            $jugaron = [...$jugaron, ...$partida->eventos()->where('tipo', EventoDePartida::ACCION)->pluck('asiento')->all()];
        }

        // Los cuatro asientos jugaron. Se mira entre las cuatro partidas y no en cada una: una partida puede
        // terminarse en la primera mano (una falta envido querida de entrada) antes de que le toque al último.
        $this->assertEqualsCanonicalizing([0, 1, 2, 3], array_values(array_unique($jugaron)));

        Exceptions::assertNothingReported();
    }

    public function test_a_la_persona_no_le_llega_ninguna_carta_ajena_que_no_se_haya_jugado_o_mostrado(): void
    {
        $revisados = 0;

        foreach ([1, 2] as $semilla) {
            $partida = $this->mesa()->abrir(Jugador::factory()->create(), Nivel::Intermedio, asientos: 4);
            $azar = Azar::deSemilla($semilla);

            for ($pedidos = 0; $pedidos < 150 && $partida->fresh()->enCurso(); $pedidos++) {
                foreach ($this->unPedidoAlAzar($partida, $azar) as $paso) {
                    $this->assertSoloCartasQuePuedeVer($partida, $paso);
                    $revisados++;
                }
            }
        }

        $this->assertGreaterThan(150, $revisados);
    }

    public function test_la_persona_solo_hace_las_senas_de_lo_que_tiene_y_una_vez_por_mano(): void
    {
        Queue::fake();

        $partida = $this->partidaArmada([
            ['1-espada', '3-oro', '4-copa'],
            ['2-oro', '6-basto', '5-basto'],
            ['7-oro', '6-oro', '4-basto'],
            ['2-basto', '6-copa', '10-oro'],
        ]);
        $mesa = $this->mesa();
        $antes = $mesa->vista($partida);

        // Ve las señas de su compañero (el siete de oro y buen envido) y las que puede hacer ella.
        $this->assertSame([Senas::SIETE_DE_ORO, Senas::BUEN_ENVIDO], $antes['senas']['companero']);
        $this->assertSame([Senas::ANCHO_DE_ESPADA, Senas::UN_TRES], $antes['senas']['tuyas']);
        $this->assertSame([], $antes['senas']['hechas']);

        [$paso] = $mesa->sena($partida, Senas::ANCHO_DE_ESPADA);

        // Queda guardada como un evento, y no cambia nada de la mesa: no hay hechos que contar ni cambia el turno.
        $evento = $partida->eventos()->where('tipo', EventoDePartida::SENA)->sole();

        $this->assertSame([Mesa::JUGADOR, ['sena' => Senas::ANCHO_DE_ESPADA]], [$evento->asiento, $evento->datos]);
        $this->assertSame([Senas::ANCHO_DE_ESPADA], $paso['senas']['hechas']);
        $this->assertSame([], $paso['hechos']);
        $this->assertSame($antes['acciones'], $paso['acciones']);
        $this->assertSame($paso, [...$mesa->vista($partida->fresh()), 'hechos' => []]);

        // La misma, otra vez: no. Una de algo que no tiene: tampoco. Una que no existe: tampoco.
        foreach ([Senas::ANCHO_DE_ESPADA, Senas::ANCHO_DE_BASTO, Senas::NADA, 'una_que_no_existe'] as $sena) {
            try {
                $mesa->sena($partida, $sena);
                $this->fail("La seña {$sena} tenía que rechazarse.");
            } catch (AccionInvalida) {
                $this->assertSame(1, $partida->eventos()->where('tipo', EventoDePartida::SENA)->count());
            }
        }

        // Una seña no hace rebotar la jugada que venía pensando: la mesa la mandó sabiendo solo del reparto.
        $mesa->actuar($partida, Accion::jugar(Carta::de('4-copa')), Mesa::JUGADOR, desde: 1);
        $this->assertSame(1, $partida->eventos()->where('tipo', EventoDePartida::ACCION)->count());
    }

    public function test_las_senas_no_existen_en_el_mano_a_mano(): void
    {
        Queue::fake();

        $partida = $this->mesa()->abrir(Jugador::factory()->create(), Nivel::Facil);

        $this->assertArrayNotHasKey('senas', $this->mesa()->vista($partida));
        $this->expectException(AccionInvalida::class);
        $this->mesa()->sena($partida, Senas::NADA);
    }

    public function test_una_sena_llega_solo_al_companero_y_cada_bot_recibe_solo_lo_que_dicen_las_senas(): void
    {
        // Un bot que anota qué señas le pasaron y qué vio, y juega la primera carta que pueda.
        $espia = new class implements Bot, RecibeSenas
        {
            /** @var list<array{asiento: int, senas: list<string>, vista: array<string, mixed>}> */
            public array $visto = [];

            /** @var list<string> */
            private array $senas = [];

            public function recibirSenas(array $senas): void
            {
                $this->senas = $senas;
            }

            public function decidir(array $vista): Accion
            {
                $this->visto[] = ['asiento' => $vista['asiento'], 'senas' => $this->senas, 'vista' => $vista];

                return Accion::desdeArray(collect($vista['acciones'])->firstWhere('tipo', TipoDeAccion::Jugar->value));
            }
        };

        $manos = [
            ['1-espada', '3-oro', '4-copa'],
            ['2-oro', '6-basto', '5-basto'],
            ['7-oro', '6-oro', '4-basto'],
            ['1-basto', '6-copa', '10-oro'],
        ];
        $partida = $this->partidaArmada($manos);
        $mesa = new Mesa($espia);

        // La persona le hace dos señas a su compañero y tira una carta: juegan los tres bots, cada uno en su turno.
        Queue::fake();
        $mesa->sena($partida, Senas::ANCHO_DE_ESPADA);
        $mesa->sena($partida, Senas::UN_TRES);
        $mesa->actuar($partida, Accion::jugar(Carta::de('4-copa')), Mesa::JUGADOR);
        $mesa->despertarAlBot($partida);

        // De cada bot, la primera vez que decidió: todavía con sus tres cartas en la mano.
        $porAsiento = collect($espia->visto)->unique('asiento')->keyBy('asiento');

        $this->assertSame([1, 2, 3], $porAsiento->keys()->sort()->values()->all());
        // Al compañero le llegan las dos señas de la persona, y a nadie más.
        $this->assertSame([Senas::ANCHO_DE_ESPADA, Senas::UN_TRES], $porAsiento[2]['senas']);
        // Los rivales reciben las de su propio compañero: lo que dicen sus cartas, no las cartas.
        $this->assertSame([Senas::ANCHO_DE_BASTO], $porAsiento[1]['senas']);
        $this->assertSame([Senas::UN_DOS, Senas::BUEN_ENVIDO], $porAsiento[3]['senas']);

        foreach ($porAsiento as $asiento => $recibido) {
            // Cada bot ve sus tres cartas y, de los demás, solo lo que ya se jugó.
            $this->assertSame($manos[$asiento], [...$recibido['vista']['misCartas']]);
            $ajenas = array_merge(...array_values(array_diff_key($manos, [$asiento => true])));
            $jugadas = array_column(array_merge(...array_column($recibido['vista']['bazas'], 'jugadas')), 1);

            foreach (array_diff($ajenas, $jugadas) as $carta) {
                $this->assertStringNotContainsString("\"{$carta}\"", json_encode($recibido), "Al bot del asiento {$asiento} le llegó el {$carta}.");
            }
        }
    }

    public function test_cuando_le_cantan_a_su_equipo_contesta_la_persona_y_su_companero_espera(): void
    {
        // Un bot que canta truco apenas puede, y si no, tira una carta o quiere.
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

        $partida = $this->partidaArmada([
            ['12-espada', '11-copa', '4-copa'],
            ['2-oro', '6-basto', '5-basto'],
            ['7-oro', '6-oro', '4-basto'],
            ['1-basto', '6-copa', '10-oro'],
        ]);
        $mesa = new Mesa($bot);

        Queue::fake();
        $mesa->actuar($partida, Accion::jugar(Carta::de('4-copa')), Mesa::JUGADOR);
        // El 1 canta truco: le cantaron al equipo de la persona.
        $mesa->despertarAlBot($partida);

        $vista = $mesa->vista($partida->fresh());

        $this->assertSame('truco', $vista['pendiente']['canto']);
        $this->assertContains(TipoDeAccion::Quiero->value, array_column($vista['acciones'], 'tipo'));
        // El motor dejaría contestar al compañero, pero el bot no lo hace: espera a la persona.
        $this->assertFalse($mesa->turnoDelBot($partida->id));
        $this->assertSame(2, $partida->eventos()->where('tipo', EventoDePartida::ACCION)->count());

        // Contesta la persona, y recién ahí sigue el juego.
        $mesa->actuar($partida, Accion::de(TipoDeAccion::Quiero), Mesa::JUGADOR);
        $this->assertTrue($mesa->turnoDelBot($partida->id));
    }

    public function test_si_la_persona_se_fue_al_mazo_su_companero_sigue_solo_y_contesta_el(): void
    {
        Queue::fake();

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

        $partida = $this->partidaArmada([
            ['12-espada', '11-copa', '4-copa'],
            ['2-oro', '6-basto', '5-basto'],
            ['7-oro', '6-oro', '4-basto'],
            ['1-basto', '6-copa', '10-oro'],
        ]);
        $mesa = new Mesa($bot);

        $mesa->actuar($partida, Accion::de(TipoDeAccion::Mazo), Mesa::JUGADOR);

        // La mano sigue: a su equipo le queda el compañero. Ya no puede hacer señas.
        $this->assertSame(Fase::Jugando->value, $mesa->vista($partida->fresh())['fase']);

        try {
            $mesa->sena($partida, Senas::NADA);
            $this->fail('Quien se fue al mazo ya no hace señas.');
        } catch (AccionInvalida) {
            // Y no le toca nada: juegan los bots hasta que la mano se cierre.
        }

        $mesa->despertarAlBot($partida);

        $this->assertSame(Fase::PorRepartir->value, $mesa->vista($partida->fresh())['fase']);
        // El compañero contestó el truco que le cantaron a su equipo: no había nadie más.
        $respuestas = $partida->eventos()->where('tipo', EventoDePartida::ACCION)->where('asiento', 2)->get()
            ->filter(fn (EventoDePartida $evento) => $evento->datos['tipo'] === TipoDeAccion::Quiero->value);

        $this->assertCount(1, $respuestas);
    }

    public function test_para_el_ranking_cuenta_como_cualquier_partida_contra_bots(): void
    {
        $jugador = Jugador::factory()->create();

        foreach ([[Nivel::Facil, false], [Nivel::Intermedio, true]] as [$nivel, $cuenta]) {
            $partida = $this->mesa()->abrir($jugador, $nivel, asientos: 4);
            $this->mesa()->abandonar($partida);

            $this->assertSame($cuenta, (new Estadisticas)->cuenta($partida->fresh()));
        }

        // Abandonar es perder, y de los tres bots no queda nada anotado.
        $resultado = Resultado::query()->sole();

        $this->assertSame($jugador->id, $resultado->jugador_id);
        $this->assertFalse($resultado->gano);
    }

    private function mesa(): Mesa
    {
        return $this->app->make(Mesa::class);
    }

    /**
     * Una partida de a cuatro con la persona de mano y las cartas que se indiquen, guardada como la guardaría la mesa.
     *
     * @param  list<list<string>>  $manos  Las de cada asiento, del 0 al 3.
     */
    private function partidaArmada(array $manos): Partida
    {
        $partida = Partida::create([
            'jugador_id' => Jugador::factory()->create()->id,
            'primer_mano' => Mesa::JUGADOR,
            'puntos' => 30,
            'asientos' => 4,
        ]);

        $partida->eventos()->create(['numero' => 1, 'tipo' => EventoDePartida::REPARTO, 'datos' => ['manos' => $manos], 'creado_en' => now()]);

        return $partida;
    }

    /**
     * Hace lo que haría alguien que toca cualquier botón válido: una acción al azar, o repartir si la mano
     * está cerrada. Con la cola corriendo en el momento, al volver ya jugaron los bots. Devuelve los pasos.
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
            // La persona se fue al mazo y la mano sigue: no le toca nada hasta que se cierre.
            $this->assertContains(Mesa::JUGADOR, $vista['enMazo'], 'La mano está en juego, la persona sigue en ella y no le toca: tendría que haber jugado un bot.');
            $this->fail('La mano quedó esperando a alguien que no es la persona.');
        }

        return $mesa->pasosDesde($partida, $vista['evento']);
    }

    /**
     * En un paso solo pueden nombrarse las cartas propias, las que ya se jugaron y las que se muestran al cerrar la mano.
     *
     * @param  array<string, mixed>  $paso
     */
    private function assertSoloCartasQuePuedeVer(Partida $partida, array $paso): void
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
