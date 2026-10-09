<?php

namespace Tests\Feature;

use App\Jobs\TurnoDelBot;
use App\Juego\Bot;
use App\Juego\BotIntermedio;
use App\Juego\Mesa;
use App\Juego\Recuerda;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Motor\Accion;
use App\Motor\AccionInvalida;
use App\Motor\Azar;
use App\Motor\Carta;
use App\Motor\Fase;
use App\Motor\TipoDeAccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * La partida contra el bot, guardada como una secuencia de eventos.
 */
class MesaTest extends TestCase
{
    use RefreshDatabase;

    public function test_abrir_crea_la_partida_y_reparte_la_primera_mano(): void
    {
        $partida = $this->mesa()->abrir($jugador = Jugador::factory()->invitado()->create());

        $this->assertTrue($partida->enCurso());
        $this->assertSame($jugador->id, $partida->jugador_id);
        $this->assertContains($partida->primer_mano, [Mesa::JUGADOR, Mesa::BOT]);

        $reparto = $partida->eventos()->first();

        $this->assertSame(EventoDePartida::REPARTO, $reparto->tipo);
        $this->assertSame(1, $reparto->numero);
        $this->assertCount(2, $reparto->datos['manos']);
        $this->assertCount(6, array_unique(array_merge(...$reparto->datos['manos'])));
    }

    public function test_el_estado_reconstruido_desde_los_eventos_es_igual_al_estado_en_curso(): void
    {
        $mesa = $this->mesa();
        $azar = Azar::deSemilla(5);
        $comparados = 0;

        // Después de cada pedido, la vista que devolvió la mesa (el estado en curso) tiene que ser idéntica a la
        // que sale de leer los eventos de la base y aplicarlos de cero. Una partida al azar puede terminar en
        // pocas jugadas (un falta envido querido de entrada), así que se juegan las que hagan falta.
        for ($partidas = 0; $comparados < 80; $partidas++) {
            $this->assertLessThan(40, $partidas, 'Las partidas terminan demasiado rápido para comparar nada.');
            $partida = $mesa->abrir(Jugador::factory()->invitado()->create());

            for ($pedidos = 0; $pedidos < 60 && $partida->fresh()->enCurso(); $pedidos++) {
                $pasos = $this->unPedidoAlAzar($mesa, $partida, $azar);

                $this->assertSame($pasos[count($pasos) - 1], $mesa->vista($partida->fresh()));
                $comparados++;
            }
        }
    }

    public function test_una_partida_entera_termina_con_un_ganador_y_queda_cerrada(): void
    {
        $mesa = $this->mesa();

        foreach ([1, 2, 3] as $semilla) {
            $partida = $mesa->abrir(Jugador::factory()->invitado()->create());
            $azar = Azar::deSemilla($semilla);

            for ($pedidos = 0; $partida->fresh()->enCurso(); $pedidos++) {
                $this->assertLessThan(3000, $pedidos, 'La partida no termina.');
                $this->unPedidoAlAzar($mesa, $partida, $azar);
            }

            $partida->refresh();
            $motor = $mesa->reconstruir($partida);

            $this->assertSame(Partida::TERMINADA, $partida->estado);
            $this->assertSame(Fase::Terminada, $motor->fase());
            $this->assertSame($motor->ganador(), $partida->ganador);
            $this->assertSame(30, $motor->tanteo()[$partida->ganador]);
            $this->assertNotNull($partida->terminada_en);

            // Los eventos quedaron numerados de corrido, sin huecos ni repetidos.
            $this->assertSame(range(1, $partida->eventos()->count()), $partida->eventos()->pluck('numero')->all());
        }
    }

    public function test_terminada_la_partida_no_se_juega_mas(): void
    {
        $mesa = $this->mesa();
        $partida = $mesa->abrir(Jugador::factory()->invitado()->create());
        $mesa->abandonar($partida);

        $this->expectException(AccionInvalida::class);
        $this->expectExceptionMessage('La partida ya terminó.');

        $mesa->actuar($partida, Accion::de(TipoDeAccion::Mazo));
    }

    public function test_abandonar_cierra_la_partida_como_perdida_sin_borrar_nada(): void
    {
        $mesa = $this->mesa();
        $jugador = Jugador::factory()->invitado()->create();
        $partida = $mesa->abrir($jugador);
        $antes = $partida->eventos()->count();

        $mesa->abandonar($partida);
        $partida->refresh();

        $this->assertSame(Partida::ABANDONADA, $partida->estado);
        $this->assertSame(Mesa::BOT, $partida->ganador);
        $this->assertSame($antes + 1, $partida->eventos()->count());
        $this->assertSame(EventoDePartida::ABANDONO, $partida->eventos()->get()->last()->tipo);

        // La próxima vez que abre, arranca una partida nueva.
        $this->assertFalse($mesa->abrir($jugador)->is($partida));
    }

    public function test_ningun_paso_le_muestra_al_jugador_una_carta_del_bot_que_no_se_jugo_ni_se_mostro(): void
    {
        $mesa = $this->mesa();
        $azar = Azar::deSemilla(9);
        $revisados = 0;

        // Se juegan las partidas que hagan falta hasta revisar pasos de sobra: alguna puede terminar enseguida.
        for ($pedidos = 0; $revisados < 120; $pedidos++) {
            $this->assertLessThan(2000, $pedidos, 'No se llegó a revisar nada.');

            if (! isset($partida) || ! $partida->fresh()->enCurso()) {
                $partida = $mesa->abrir(Jugador::factory()->invitado()->create());
            }

            $pasos = $this->unPedidoAlAzar($mesa, $partida, $azar);

            // Lo que recibió el bot en cada mano está en el reparto guardado: el primero es la mano 1, el segundo la 2.
            $repartos = $partida->eventos()->where('tipo', EventoDePartida::REPARTO)->get()->values();

            foreach ($pasos as $paso) {
                $delBot = $repartos[$paso['numeroDeMano'] - 1]->datos['manos'][Mesa::BOT];

                $aLaVista = array_merge(
                    array_merge(...array_map(fn (array $baza) => array_column($baza['jugadas'], 1), $paso['bazas'])),
                    array_merge([], ...array_values($paso['cierre']['mostradas'] ?? [])),
                );

                preg_match_all('/\b(?:1[0-2]|[1-7])-(?:espada|basto|oro|copa)\b/', json_encode($paso), $nombradas);

                $this->assertSame([], array_values(array_diff(array_intersect($nombradas[0], $delBot), $aLaVista)));
                $revisados++;
            }
        }
    }

    public function test_el_bot_decide_solo_con_la_vista_de_su_asiento(): void
    {
        // Un bot que anota todo lo que le muestran y juega como el Intermedio.
        $espia = new class implements Bot
        {
            /** @var list<array<string, mixed>> */
            public array $vistas = [];

            public function decidir(array $vista): Accion
            {
                $this->vistas[] = $vista;

                return (new BotIntermedio)->decidir($vista);
            }
        };

        // El job del bot pide la mesa al contenedor: tiene que recibir esta, la del espía.
        $this->app->instance(Mesa::class, $mesa = new Mesa($espia));
        $azar = Azar::deSemilla(4);

        for ($pedidos = 0; count($espia->vistas) < 40; $pedidos++) {
            $this->assertLessThan(2000, $pedidos, 'El bot casi no llegó a jugar.');

            if (! isset($partida) || ! $partida->fresh()->enCurso()) {
                $partida = $mesa->abrir(Jugador::factory()->invitado()->create());
            }

            $this->unPedidoAlAzar($mesa, $partida, $azar);
        }

        foreach ($espia->vistas as $vista) {
            $this->assertSame(Mesa::BOT, $vista['asiento']);
            $this->assertLessThanOrEqual(3, count($vista['misCartas']));
            // De la mano del jugador solo sabe cuántas cartas le quedan.
            $this->assertIsInt($vista['cartasEnMano'][Mesa::JUGADOR]);
        }
    }

    public function test_el_bot_que_recuerda_recibe_las_manos_anteriores_sin_las_cartas_que_el_jugador_no_mostro(): void
    {
        // Un bot que anota qué le recuerdan antes de cada jugada y juega como el Intermedio.
        $espia = new class implements Bot, Recuerda
        {
            /** @var list<array{mano: int, anteriores: list<array<string, mixed>>}> */
            public array $jugadas = [];

            /** @var list<array<string, mixed>>|null */
            private ?array $anteriores = null;

            public function recordar(array $manos): void
            {
                $this->anteriores = $manos;
            }

            public function decidir(array $vista): Accion
            {
                $this->jugadas[] = ['mano' => $vista['numeroDeMano'], 'anteriores' => $this->anteriores];
                $this->anteriores = null;

                return (new BotIntermedio)->decidir($vista);
            }
        };

        $this->app->instance(Mesa::class, $mesa = new Mesa($espia));
        $azar = Azar::deSemilla(11);
        $revisadas = 0;
        $vistas = 0;

        // Se juegan las partidas que hagan falta hasta revisar manos recordadas de sobra.
        for ($pedidos = 0; $revisadas < 150; $pedidos++) {
            $this->assertLessThan(4000, $pedidos, 'El bot casi no llegó a recordar nada.');

            if (! isset($partida) || ! $partida->fresh()->enCurso()) {
                $partida = $mesa->abrir(Jugador::factory()->invitado()->create());
            }

            $this->unPedidoAlAzar($mesa, $partida, $azar);
            $repartos = $partida->eventos()->where('tipo', EventoDePartida::REPARTO)->get()->values();

            foreach (array_slice($espia->jugadas, $vistas) as $jugada) {
                // Se le recuerda antes de cada jugada, y son todas las manos anteriores a la que está jugando.
                $this->assertNotNull($jugada['anteriores'], 'El bot jugó sin que le recordaran la partida.');
                $this->assertCount($jugada['mano'] - 1, $jugada['anteriores']);

                foreach ($jugada['anteriores'] as $orden => $cerrada) {
                    $this->assertSame(Mesa::BOT, $cerrada['asiento']);
                    $this->assertSame($orden + 1, $cerrada['numeroDeMano']);
                    $this->assertNotNull($cerrada['cierre'], 'Se le recordó una mano que no estaba cerrada.');

                    $delJugador = $repartos[$orden]->datos['manos'][Mesa::JUGADOR];

                    $aLaVista = array_merge(
                        array_merge(...array_map(fn (array $baza) => array_column($baza['jugadas'], 1), $cerrada['bazas'])),
                        array_merge([], ...array_values($cerrada['cierre']['mostradas'] ?? [])),
                    );

                    preg_match_all('/\b(?:1[0-2]|[1-7])-(?:espada|basto|oro|copa)\b/', json_encode($cerrada), $nombradas);

                    $this->assertSame([], array_values(array_diff(array_intersect($nombradas[0], $delJugador), $aLaVista)));
                    $revisadas++;
                }
            }

            $vistas = count($espia->jugadas);
        }
    }

    public function test_cuando_le_toca_al_bot_su_turno_queda_en_la_cola_y_sale_al_confirmarse_la_jugada(): void
    {
        Queue::fake();

        $mesa = $this->mesa();
        $partida = $this->partidaArmada([['4-copa', '5-copa', '6-basto'], ['1-espada', '3-oro', '10-basto']]);

        // El jugador es mano y tira una carta: le toca al bot.
        $pasos = $mesa->actuar($partida, Accion::jugar(Carta::de('4-copa')));

        $this->assertCount(1, $pasos, 'La respuesta trae solo la jugada propia: el bot todavía no jugó.');
        $this->assertSame(2, $partida->eventos()->count());

        // Sale de la cola recién cuando la jugada quedó guardada, y sin demora: el bot juega apenas le toca, y la
        // pausa para que parezca que piensa la pone la mesa del navegador, según el ritmo que eligió quien juega.
        Queue::assertPushed(TurnoDelBot::class, 1);
        Queue::assertPushed(
            TurnoDelBot::class,
            fn (TurnoDelBot $turno) => $turno->partidaId === $partida->id && $turno->delay === null && $turno->afterCommit === true,
        );
    }

    public function test_si_no_le_toca_al_bot_no_se_encola_nada(): void
    {
        Queue::fake();

        $mesa = $this->mesa();
        $partida = $this->partidaArmada([['4-copa', '5-copa', '6-basto'], ['1-espada', '3-oro', '10-basto']]);

        // Se va al mazo: la mano se cierra y nadie tiene nada que jugar hasta que se reparta.
        $mesa->actuar($partida, Accion::de(TipoDeAccion::Mazo));

        Queue::assertNothingPushed();

        // En la mano siguiente el mano es el bot: al repartir, su turno va a la cola.
        $mesa->repartir($partida);

        Queue::assertPushed(TurnoDelBot::class, 1);
    }

    public function test_el_job_juega_una_sola_accion_y_si_le_sigue_tocando_encola_otro(): void
    {
        Queue::fake();

        $mesa = $this->mesa();
        $partida = $this->partidaArmada([['4-copa', '5-copa', '6-basto'], ['1-espada', '6-oro', '10-basto']]);

        // El jugador sale con un 4. El bot lo gana con el 6 y le toca salir en la segunda: dos jugadas seguidas.
        $mesa->actuar($partida, Accion::jugar(Carta::de('4-copa')));
        $antes = $partida->eventos()->count();

        (new TurnoDelBot($partida->id))->handle($mesa);

        $this->assertSame($antes + 1, $partida->eventos()->count(), 'El job juega una acción, no todas.');
        $this->assertSame(['tipo' => 'jugar', 'carta' => '6-oro'], $partida->eventos()->get()->last()->datos);

        // Uno por la jugada del jugador y otro que dejó el propio job.
        Queue::assertPushed(TurnoDelBot::class, 2);
    }

    public function test_un_job_repetido_o_tardio_no_hace_nada(): void
    {
        Queue::fake();

        $mesa = $this->mesa();
        $partida = $this->partidaArmada([['4-copa', '5-copa', '6-basto'], ['1-espada', '3-oro', '10-basto']]);

        // Le toca al jugador: un job que llega ahora no tiene nada que jugar.
        (new TurnoDelBot($partida->id))->handle($mesa);
        $this->assertSame(1, $partida->eventos()->count());

        // Le toca al bot y llegan dos jobs iguales: juega una sola vez por turno.
        $mesa->actuar($partida, Accion::jugar(Carta::de('4-copa')));
        (new TurnoDelBot($partida->id))->handle($mesa);
        $jugadas = $partida->eventos()->count();

        while ($mesa->turnoDelBot($partida->id)) {
            $jugadas = $partida->eventos()->count();
        }

        (new TurnoDelBot($partida->id))->handle($mesa);
        $this->assertSame($jugadas, $partida->eventos()->count());

        // Con la partida cerrada tampoco, y no falla. Ni con una partida que no existe.
        $mesa->abandonar($partida);
        $cerrada = $partida->eventos()->count();

        (new TurnoDelBot($partida->id))->handle($mesa);
        (new TurnoDelBot($partida->id + 1000))->handle($mesa);

        $this->assertSame($cerrada, $partida->eventos()->count());
    }

    public function test_la_red_de_seguridad_hace_jugar_al_bot_todo_lo_que_le_toca_sin_pasar_por_la_cola(): void
    {
        // La cola no corre: es lo que pasa si el proceso que la atiende está caído.
        Queue::fake();

        $mesa = $this->mesa();
        $partida = $this->partidaArmada([['4-copa', '5-copa', '6-basto'], ['1-espada', '3-oro', '10-basto']]);

        $mesa->actuar($partida, Accion::jugar(Carta::de('4-copa')));
        $this->assertSame([], $mesa->vista($partida)['acciones'], 'Le toca al bot.');

        $mesa->despertarAlBot($partida);
        $vista = $mesa->vista($partida);

        $this->assertNotSame([], $vista['acciones'], 'El bot jugó y le devolvió el turno al jugador.');
        $this->assertGreaterThan(2, $partida->eventos()->count());

        // Despertarlo cuando no le toca no cambia nada.
        $mesa->despertarAlBot($partida);
        $this->assertSame($vista, $mesa->vista($partida));
    }

    public function test_si_el_bot_falla_la_partida_no_se_traba_juega_algo_valido_y_el_error_queda_anotado(): void
    {
        Queue::fake();
        Exceptions::fake();

        // Un bot roto: una vez tira un error y otra devuelve una jugada que el reglamento no permite.
        $roto = new class implements Bot
        {
            public int $veces = 0;

            public function decidir(array $vista): Accion
            {
                return $this->veces++ % 2 === 0 ? throw new RuntimeException('El bot se rompió.') : Accion::de(TipoDeAccion::ValeCuatro);
            }
        };

        $mesa = new Mesa($roto);
        $partida = $this->partidaArmada([['4-copa', '5-copa', '6-basto'], ['1-espada', '6-oro', '10-basto']]);

        // Le toca jugar una carta: tira una de las suyas.
        $mesa->actuar($partida, Accion::jugar(Carta::de('4-copa')));
        $this->assertTrue($mesa->turnoDelBot($partida->id));

        $jugada = $partida->eventos()->get()->last();

        $this->assertSame(Mesa::BOT, $jugada->asiento);
        $this->assertSame('jugar', $jugada->datos['tipo']);
        $this->assertContains($jugada->datos['carta'], ['1-espada', '6-oro', '10-basto']);

        // Le cantan truco: no lo quiere, y la mano se cierra.
        $otra = $this->partidaArmada([['4-copa', '5-copa', '6-basto'], ['1-espada', '6-oro', '10-basto']]);
        $mesa->actuar($otra, Accion::de(TipoDeAccion::Truco));
        $this->assertTrue($mesa->turnoDelBot($otra->id));

        $this->assertSame(['tipo' => 'no_quiero'], $otra->eventos()->get()->last()->datos);

        Exceptions::assertReported(RuntimeException::class);
        Exceptions::assertReported(AccionInvalida::class);
    }

    public function test_los_pasos_desde_un_evento_son_la_vista_del_jugador_despues_de_cada_evento_posterior(): void
    {
        $mesa = $this->mesa();
        $partida = $this->partidaArmada([['4-copa', '5-copa', '6-basto'], ['1-espada', '3-oro', '10-basto']]);

        $mesa->actuar($partida, Accion::jugar(Carta::de('4-copa')));
        $eventos = $partida->eventos()->count();
        $pasos = $mesa->pasosDesde($partida, 1);

        $this->assertSame(range(2, $eventos), array_column($pasos, 'evento'));
        $this->assertSame($mesa->vista($partida), $pasos[count($pasos) - 1]);
        $this->assertSame([], $mesa->pasosDesde($partida, $eventos));

        foreach ($pasos as $paso) {
            $this->assertSame(Mesa::JUGADOR, $paso['asiento']);
        }
    }

    private function mesa(): Mesa
    {
        return $this->app->make(Mesa::class);
    }

    /**
     * Una partida con el jugador de mano y las cartas que se indiquen, guardada como la guardaría la mesa.
     *
     * @param  array{0: list<string>, 1: list<string>}  $manos  Las del jugador y las del bot.
     */
    private function partidaArmada(array $manos): Partida
    {
        $partida = Partida::create([
            'jugador_id' => Jugador::factory()->invitado()->create()->id,
            'primer_mano' => Mesa::JUGADOR,
            'puntos' => 30,
        ]);

        $partida->eventos()->create([
            'numero' => 1,
            'tipo' => EventoDePartida::REPARTO,
            'datos' => ['manos' => $manos],
            'creado_en' => now(),
        ]);

        return $partida;
    }

    /**
     * Hace lo que haría un jugador que toca cualquier botón válido: una acción al azar,
     * o repartir si la mano está cerrada. Devuelve los pasos que respondió la mesa.
     *
     * @return list<array<string, mixed>>
     */
    private function unPedidoAlAzar(Mesa $mesa, Partida $partida, Azar $azar): array
    {
        $vista = $mesa->vista($partida->fresh());

        if ($vista['fase'] === Fase::PorRepartir->value) {
            $mesa->repartir($partida);
        } else {
            $this->assertNotEmpty($vista['acciones'], 'La mano está en juego y al jugador no le toca: el bot tendría que haber jugado.');

            $mesa->actuar($partida, Accion::desdeArray($vista['acciones'][$azar->entero(0, count($vista['acciones']) - 1)]));
        }

        // En los tests la cola corre en el momento: acá ya están la jugada propia y las del bot.
        return $mesa->pasosDesde($partida, $vista['evento']);
    }
}
