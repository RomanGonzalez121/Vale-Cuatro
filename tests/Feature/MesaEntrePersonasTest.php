<?php

namespace Tests\Feature;

use App\Jobs\TurnoDelBot;
use App\Juego\Mesa;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Motor\Accion;
use App\Motor\AccionInvalida;
use App\Motor\Azar;
use App\Motor\Fase;
use App\Motor\TipoDeAccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

/**
 * La partida entre dos personas sobre la misma mesa de siempre: cada una juega desde su asiento
 * y ve solo lo suyo. No hay bot, así que ningún turno se deja en la cola.
 */
class MesaEntrePersonasTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_asiento_ve_su_mano_y_nada_de_la_del_otro(): void
    {
        [$partida] = $this->partidaEntreDos();
        $mesa = $this->mesa();

        $vistas = [$mesa->vista($partida, 0), $mesa->vista($partida, 1)];

        $this->assertSame(0, $vistas[0]['asiento']);
        $this->assertSame(1, $vistas[1]['asiento']);
        $this->assertCount(3, $vistas[0]['misCartas']);
        $this->assertCount(3, $vistas[1]['misCartas']);
        $this->assertSame([], array_intersect($vistas[0]['misCartas'], $vistas[1]['misCartas']), 'Las dos manos no pueden repetir cartas.');

        // Del otro solo se sabe cuántas cartas le quedan.
        $this->assertSame([3, 3], $vistas[0]['cartasEnMano']);

        foreach ([[0, 1], [1, 0]] as [$yo, $otro]) {
            preg_match_all('/\b(?:1[0-2]|[1-7])-(?:espada|basto|oro|copa)\b/', json_encode($vistas[$yo]), $nombradas);

            $this->assertSame([], array_intersect($nombradas[0], $vistas[$otro]['misCartas']), "El asiento {$yo} ve cartas del asiento {$otro}.");
        }
    }

    public function test_juegan_por_turnos_y_quien_no_tiene_el_turno_no_puede_mover(): void
    {
        [$partida] = $this->partidaEntreDos();
        $mesa = $this->mesa();

        $conTurno = $mesa->vista($partida, 0)['acciones'] !== [] ? 0 : 1;
        $antes = $partida->eventos()->count();

        try {
            $mesa->actuar($partida, Accion::de(TipoDeAccion::Truco), 1 - $conTurno);
            $this->fail('Jugó quien no tenía el turno.');
        } catch (AccionInvalida) {
            $this->assertSame($antes, $partida->eventos()->count(), 'Una jugada rechazada no guarda nada.');
        }

        $mesa->actuar($partida, Accion::de(TipoDeAccion::Truco), $conTurno);

        $this->assertSame($antes + 1, $partida->eventos()->count());
        $this->assertSame($conTurno, $partida->eventos()->get()->last()->asiento, 'La jugada queda a nombre de quien la hizo.');
    }

    public function test_una_partida_entera_entre_dos_personas_termina_y_ninguna_vista_trae_cartas_ajenas(): void
    {
        $mesa = $this->mesa();
        $azar = Azar::deSemilla(31);
        $revisados = 0;

        // Una partida puede cerrarse enseguida (un falta envido querido de entrada): se juegan las que hagan falta.
        for ($partidas = 0; $revisados < 200; $partidas++) {
            $this->assertLessThan(40, $partidas, 'No se llegó a revisar lo suficiente.');

            [$partida] = $this->partidaEntreDos();

            for ($pedidos = 0; $partida->fresh()->enCurso(); $pedidos++) {
                $this->assertLessThan(3000, $pedidos, 'La partida no termina.');

                $antes = (int) $partida->eventos()->max('numero');
                $quien = $this->quienJuega($mesa, $partida);

                if ($quien === null) {
                    $paso = $mesa->repartir($partida, 0)[0];
                    $actor = 0;
                } else {
                    $acciones = $mesa->vista($partida, $quien)['acciones'];
                    $paso = $mesa->actuar($partida, Accion::desdeArray($acciones[$azar->entero(0, count($acciones) - 1)]), $quien)[0];
                    $actor = $quien;
                }

                // Lo que recibe quien jugó y lo que se entera el otro después: las dos vistas, evento por evento.
                $revisados += $this->sinCartasAjenas($partida, $paso, $actor);

                foreach ($mesa->pasosDesde($partida, $antes, 1 - $actor) as $delOtro) {
                    $revisados += $this->sinCartasAjenas($partida, $delOtro, 1 - $actor);
                }
            }

            $partida->refresh();
            $motor = $mesa->reconstruir($partida);

            $this->assertSame(Partida::TERMINADA, $partida->estado);
            $this->assertSame(Fase::Terminada, $motor->fase());
            $this->assertSame($motor->ganador(), $partida->ganador);
            $this->assertSame(30, $motor->tanteo()[$partida->ganador]);
            $this->assertSame(range(1, $partida->eventos()->count()), $partida->eventos()->pluck('numero')->all());
        }
    }

    public function test_entre_personas_nunca_se_deja_el_turno_de_un_bot_en_la_cola(): void
    {
        Queue::fake();
        [$partida] = $this->partidaEntreDos();
        $mesa = $this->mesa();
        $azar = Azar::deSemilla(5);

        for ($pedidos = 0; $pedidos < 60 && $partida->fresh()->enCurso(); $pedidos++) {
            $quien = $this->quienJuega($mesa, $partida);

            if ($quien === null) {
                $mesa->repartir($partida, 0);

                continue;
            }

            $acciones = $mesa->vista($partida, $quien)['acciones'];
            $mesa->actuar($partida, Accion::desdeArray($acciones[$azar->entero(0, count($acciones) - 1)]), $quien);
        }

        Queue::assertNotPushed(TurnoDelBot::class);

        // Y si alguien lo pidiera igual, el bot no juega por el asiento 1.
        $eventos = $partida->eventos()->count();
        $this->assertFalse($mesa->turnoDelBot($partida->id));
        $this->assertSame($eventos, $partida->eventos()->count());
    }

    public function test_entre_personas_hay_que_decir_el_asiento_y_contra_el_bot_el_1_no_es_de_una_persona(): void
    {
        [$entreDos] = $this->partidaEntreDos();
        $mesa = $this->mesa();

        foreach ([
            fn () => $mesa->vista($entreDos),
            fn () => $mesa->pasosDesde($entreDos, 0),
            fn () => $mesa->actuar($entreDos, Accion::de(TipoDeAccion::Truco)),
            fn () => $mesa->repartir($entreDos),
            fn () => $mesa->abandonar($entreDos),
        ] as $sinAsiento) {
            try {
                $sinAsiento();
                $this->fail('Se aceptó un pedido sin decir el asiento.');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $contraElBot = $mesa->abrir(Jugador::factory()->invitado()->create());

        // Sin decirlo, contra el bot vale el asiento 0 de siempre.
        $this->assertSame(0, $mesa->vista($contraElBot)['asiento']);

        // El asiento 1 es del bot: una persona no puede mirar ni mover desde ahí.
        $this->expectException(InvalidArgumentException::class);
        $mesa->vista($contraElBot, 1);
    }

    public function test_si_alguien_abandona_gana_el_otro_asiento(): void
    {
        $mesa = $this->mesa();

        foreach ([0, 1] as $quienSeVa) {
            [$partida] = $this->partidaEntreDos();
            $antes = $partida->eventos()->count();

            $mesa->abandonar($partida, $quienSeVa);
            $partida->refresh();

            $this->assertSame(Partida::ABANDONADA, $partida->estado);
            $this->assertSame(1 - $quienSeVa, $partida->ganador);
            $this->assertSame($antes + 1, $partida->eventos()->count());

            $ultimo = $partida->eventos()->get()->last();
            $this->assertSame(EventoDePartida::ABANDONO, $ultimo->tipo);
            $this->assertSame($quienSeVa, $ultimo->asiento);

            try {
                $mesa->actuar($partida, Accion::de(TipoDeAccion::Truco), 1 - $quienSeVa);
                $this->fail('Se jugó en una partida cerrada.');
            } catch (AccionInvalida $rechazo) {
                $this->assertSame('La partida ya terminó.', $rechazo->getMessage());
            }
        }
    }

    public function test_recargar_devuelve_el_mismo_estado_que_ya_habia_recibido(): void
    {
        [$partida] = $this->partidaEntreDos();
        $mesa = $this->mesa();
        $azar = Azar::deSemilla(8);

        for ($i = 0; $i < 12 && $partida->fresh()->enCurso(); $i++) {
            $quien = $this->quienJuega($mesa, $partida);

            if ($quien === null) {
                $mesa->repartir($partida, 0);

                continue;
            }

            $acciones = $mesa->vista($partida, $quien)['acciones'];
            $mesa->actuar($partida, Accion::desdeArray($acciones[$azar->entero(0, count($acciones) - 1)]), $quien);
        }

        foreach ([0, 1] as $asiento) {
            $recibidos = $mesa->pasosDesde($partida, 0, $asiento);

            // Quien recarga recibe lo mismo que se le fue contando, evento por evento.
            $this->assertSame($mesa->vista($partida, $asiento), $recibidos[count($recibidos) - 1]);
            $this->assertSame([], $mesa->pasosDesde($partida, $partida->eventos()->max('numero'), $asiento));
        }
    }

    public function test_cada_persona_encuentra_su_partida_y_un_tercero_no(): void
    {
        [$partida, $uno, $dos] = $this->partidaEntreDos();
        $tercero = Jugador::factory()->invitado()->create();
        $mesa = $this->mesa();

        $this->assertTrue($mesa->enCursoDe($uno)->is($partida));
        $this->assertTrue($mesa->enCursoDe($dos)->is($partida));
        $this->assertTrue($mesa->ultimaDe($dos)->is($partida));
        $this->assertNull($mesa->enCursoDe($tercero));
        $this->assertNull($mesa->ultimaDe($tercero));
    }

    public function test_por_http_el_asiento_sale_de_quien_pide_y_no_de_lo_que_mande(): void
    {
        [$partida, $uno, $dos] = $this->partidaEntreDos();
        $tercero = Jugador::factory()->invitado()->create();
        $mesa = $this->mesa();

        [$jugador, $asiento] = $mesa->vista($partida, 0)['acciones'] !== [] ? [$uno, 0] : [$dos, 1];
        $accion = $mesa->vista($partida, $asiento)['acciones'][0];

        // Manda "asiento" del otro: se ignora, la jugada queda a nombre del asiento real.
        $this->actingAs($jugador)->postJson('/mesa/accion', [...$accion, 'asiento' => 1 - $asiento])->assertOk();

        $this->assertSame($asiento, $partida->eventos()->get()->last()->asiento);

        // Un tercero no tiene partida: no ve, no mueve, no reparte.
        $this->actingAs($tercero)->getJson('/mesa/estado')->assertStatus(409);
        $this->actingAs($tercero)->postJson('/mesa/accion', $accion)->assertStatus(409);
        $this->actingAs($tercero)->postJson('/mesa/repartir')->assertStatus(409);
    }

    public function test_por_http_cada_persona_recibe_su_vista_y_nunca_las_cartas_del_otro(): void
    {
        [$partida, $uno, $dos] = $this->partidaEntreDos();
        $manos = $partida->eventos()->where('tipo', EventoDePartida::REPARTO)->first()->datos['manos'];

        foreach ([[$uno, 0], [$dos, 1]] as [$jugador, $asiento]) {
            $respuesta = $this->actingAs($jugador)->getJson('/mesa/estado')->assertOk()->json('vista');

            $this->assertSame($asiento, $respuesta['asiento']);
            $this->assertSame($manos[$asiento], $respuesta['misCartas'], 'Cada una recibe su propia mano.');

            preg_match_all('/\b(?:1[0-2]|[1-7])-(?:espada|basto|oro|copa)\b/', json_encode($respuesta), $nombradas);
            $this->assertSame([], array_intersect($nombradas[0], $manos[1 - $asiento]), 'La respuesta trae cartas del otro.');
        }
    }

    private function mesa(): Mesa
    {
        return $this->app->make(Mesa::class);
    }

    /**
     * Una partida entre dos personas ya en curso, con la primera mano repartida.
     *
     * @return array{0: Partida, 1: Jugador, 2: Jugador}
     */
    private function partidaEntreDos(): array
    {
        $uno = Jugador::factory()->invitado()->create();
        $dos = Jugador::factory()->invitado()->create();

        $partida = new Partida([
            'jugador_id' => $uno->id,
            'invitado_id' => $dos->id,
            'primer_mano' => 0,
            'puntos' => 30,
            'entre_personas' => true,
            'codigo' => Partida::codigoNuevo(),
            'nivel_bot' => null,
        ]);
        $partida->estado = Partida::EN_CURSO;
        $partida->save();

        $this->mesa()->repartir($partida, 0);

        return [$partida, $uno, $dos];
    }

    /**
     * El asiento al que le toca, o null si la mano está cerrada y falta repartir.
     */
    private function quienJuega(Mesa $mesa, Partida $partida): ?int
    {
        foreach ([0, 1] as $asiento) {
            if ($mesa->vista($partida->fresh(), $asiento)['acciones'] !== []) {
                return $asiento;
            }
        }

        return null;
    }

    /**
     * Revisa que el paso que recibe un asiento no nombre ninguna carta del otro que no se haya jugado ni mostrado.
     * Devuelve 1 para ir contando cuántos pasos se revisaron.
     *
     * @param  array<string, mixed>  $paso
     */
    private function sinCartasAjenas(Partida $partida, array $paso, int $asiento): int
    {
        $repartos = $partida->eventos()->where('tipo', EventoDePartida::REPARTO)->get()->values();
        $delOtro = $repartos[$paso['numeroDeMano'] - 1]->datos['manos'][1 - $asiento];

        $aLaVista = array_merge(
            array_merge(...array_map(fn (array $baza) => array_column($baza['jugadas'], 1), $paso['bazas'])),
            array_merge([], ...array_values($paso['cierre']['mostradas'] ?? [])),
        );

        preg_match_all('/\b(?:1[0-2]|[1-7])-(?:espada|basto|oro|copa)\b/', json_encode($paso), $nombradas);

        $this->assertSame(
            [],
            array_values(array_diff(array_intersect($nombradas[0], $delOtro), $aLaVista)),
            "El paso {$paso['evento']} del asiento {$asiento} trae cartas del otro.",
        );
        $this->assertSame($asiento, $paso['asiento']);

        return 1;
    }
}
