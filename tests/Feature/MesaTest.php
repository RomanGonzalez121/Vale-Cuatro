<?php

namespace Tests\Feature;

use App\Juego\Bot;
use App\Juego\BotProvisional;
use App\Juego\Mesa;
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

    public function test_con_una_partida_sin_terminar_abrir_devuelve_la_misma(): void
    {
        $jugador = Jugador::factory()->invitado()->create();

        $primera = $this->mesa()->abrir($jugador);
        $segunda = $this->mesa()->abrir($jugador);

        $this->assertTrue($primera->is($segunda));
        $this->assertSame(1, Partida::count());
    }

    public function test_cada_jugador_tiene_su_propia_partida(): void
    {
        $una = $this->mesa()->abrir(Jugador::factory()->invitado()->create());
        $otra = $this->mesa()->abrir(Jugador::factory()->invitado()->create());

        $this->assertFalse($una->is($otra));
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

    public function test_una_accion_invalida_se_rechaza_y_no_genera_evento(): void
    {
        $mesa = $this->mesa();
        $partida = $mesa->abrir(Jugador::factory()->invitado()->create());
        $antes = $partida->eventos()->count();
        $vista = $mesa->vista($partida);

        // Una carta que el jugador no tiene, mandada a mano.
        $ajena = collect(['1-espada', '1-basto', '7-espada', '7-oro'])->first(fn (string $id) => ! in_array($id, $vista['misCartas'], true));

        try {
            $mesa->actuar($partida, Accion::jugar(Carta::de($ajena)));
            $this->fail('La mesa aceptó una carta que el jugador no tiene.');
        } catch (AccionInvalida $rechazo) {
            $this->assertNotSame('', $rechazo->getMessage());
        }

        // Y un canto que no corresponde.
        try {
            $mesa->actuar($partida, Accion::de(TipoDeAccion::ValeCuatro));
            $this->fail('La mesa aceptó un vale cuatro sin truco.');
        } catch (AccionInvalida) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($antes, $partida->eventos()->count());
        $this->assertSame($vista, $mesa->vista($partida));
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

    public function test_no_se_reparte_con_la_mano_en_juego(): void
    {
        $mesa = $this->mesa();
        $partida = $mesa->abrir(Jugador::factory()->invitado()->create());
        $antes = $partida->eventos()->count();

        try {
            $mesa->repartir($partida);
            $this->fail('La mesa repartió con una mano en juego.');
        } catch (AccionInvalida) {
            $this->assertSame($antes, $partida->eventos()->count());
        }
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
        // Un bot que anota todo lo que le muestran y juega como el provisional.
        $espia = new class implements Bot
        {
            /** @var list<array<string, mixed>> */
            public array $vistas = [];

            public function decidir(array $vista): Accion
            {
                $this->vistas[] = $vista;

                return (new BotProvisional)->decidir($vista);
            }
        };

        $mesa = new Mesa($espia);
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

    private function mesa(): Mesa
    {
        return $this->app->make(Mesa::class);
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
            return $mesa->repartir($partida);
        }

        $this->assertNotEmpty($vista['acciones'], 'La mano está en juego y al jugador no le toca: el bot tendría que haber jugado.');

        return $mesa->actuar($partida, Accion::desdeArray($vista['acciones'][$azar->entero(0, count($vista['acciones']) - 1)]));
    }
}
