<?php

namespace Tests\Feature;

use App\Juego\Mesa;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Motor\Accion;
use App\Motor\AccionInvalida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Entre dos personas el servidor puede avanzar la partida sin que una mesa lo haya mostrado todavía: resuelve
 * un turno vencido, reparte solo. Acá se prueba que cada mesa sigue la partida correcta y que una jugada
 * decidida sobre una pantalla atrasada no entra.
 */
class MesaAlDiaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Los trabajos con demora quedan registrados y no corren: acá se llama a resolverPlazo() a mano.
        Queue::fake();
    }

    public function test_quien_se_sienta_teniendo_una_partida_mas_nueva_ya_cerrada_sigue_la_de_la_sala(): void
    {
        $creador = Jugador::factory()->invitado()->create();
        $invitado = Jugador::factory()->invitado()->create();

        // La sala recibe su número al abrirse. Después, quien va a sentarse juega contra el bot y abandona:
        // esa partida tiene un número más alto que la sala en la que termina jugando.
        $sala = $this->mesa()->crearSala($creador);
        $contraElBot = $this->mesa()->abrir($invitado);
        $this->mesa()->abandonar($contraElBot);
        $this->assertGreaterThan($sala->id, $contraElBot->id);

        $this->travel(5)->seconds();
        $partida = $this->mesa()->sentarse($sala->codigo, $invitado);

        // La mesa pregunta por la partida que muestra y recibe sus pasos, no un "no tenés partida".
        $pasos = $this->actingAs($invitado)->getJson("/mesa/estado?partida={$partida->id}&desde=0")->assertOk()->json('pasos');
        $this->assertNotEmpty($pasos);
        $this->assertSame($partida->id, $pasos[0]['partida']);

        $this->actingAs($invitado)->getJson('/mesa/estado')->assertOk()->assertJsonPath('vista.partida', $partida->id);

        // Y cuando esa partida termina, lo que se le cuenta al volver es de ella y no de la que jugó contra el bot.
        $this->travel(5)->seconds();
        $this->mesa()->abandonar($partida->fresh(), 0);

        $this->actingAs($invitado)->get('/mesa')->assertRedirect(route('modos'))->assertSessionHas('aviso', 'Tu rival abandonó la partida: ganaste.');
    }

    public function test_la_consulta_de_una_partida_ajena_no_devuelve_nada(): void
    {
        [$partida] = $this->partidaEnCurso();
        $tercero = Jugador::factory()->invitado()->create();
        $this->mesa()->abrir($tercero);

        // Mandar el número de la partida de otros no alcanza: tiene que ser una en la que el jugador ocupe un asiento.
        $this->actingAs($tercero)->getJson("/mesa/estado?partida={$partida->id}&desde=0")->assertStatus(409)->assertJsonMissingPath('pasos');
    }

    public function test_una_jugada_que_dice_un_evento_viejo_se_rechaza_y_no_guarda_nada(): void
    {
        [$partida, $uno, $dos] = $this->partidaEnCurso();
        $conTurno = $this->quienTieneElTurno($partida);
        $jugador = [$uno, $dos][$conTurno];
        $ultimo = $partida->eventos()->count();
        $carta = collect($this->mesa()->vista($partida, $conTurno)['acciones'])->firstWhere('tipo', 'jugar');

        // El último evento es la llegada de quien abrió la sala, que no cambia la mesa: el anterior es el reparto.
        $this->actingAs($jugador)->postJson('/mesa/accion', [...$carta, 'desde' => $ultimo - 2])
            ->assertStatus(422)
            ->assertJsonPath('motivo', 'La mesa cambió mientras jugabas.');
        $this->assertSame($ultimo, $partida->eventos()->count());

        // Con el último evento, entra.
        $this->actingAs($jugador)->postJson('/mesa/accion', [...$carta, 'desde' => $ultimo])->assertOk();
        $this->assertSame($ultimo + 1, $partida->eventos()->count());
    }

    public function test_que_el_otro_llegue_a_la_mesa_no_hace_rebotar_la_jugada(): void
    {
        [$partida, $uno, $dos] = $this->partidaEnCurso();
        $conTurno = $this->quienTieneElTurno($partida);
        $jugador = [$uno, $dos][$conTurno];
        $ultimo = $partida->eventos()->count();
        $carta = collect($this->mesa()->vista($partida, $conTurno)['acciones'])->firstWhere('tipo', 'jugar');

        $this->assertSame(EventoDePartida::LLEGADA, $partida->eventos()->get()->last()->tipo);

        // La mesa todavía no se enteró de la llegada: lo último que mostró es el reparto. La jugada entra igual.
        $this->actingAs($jugador)->postJson('/mesa/accion', [...$carta, 'desde' => $ultimo - 1])->assertOk();
        $this->assertSame($ultimo + 1, $partida->eventos()->count());

        // Un número que la partida no tiene no vale.
        $this->actingAs([$uno, $dos][1 - $conTurno])->postJson('/mesa/accion', ['tipo' => 'mazo', 'desde' => $ultimo + 5])->assertStatus(422);
    }

    public function test_un_canto_tocado_mirando_una_mano_no_entra_en_la_siguiente(): void
    {
        [$partida, $uno, $dos] = $this->partidaEnCurso();
        $visto = $partida->eventos()->count();

        // Nadie juega: el servidor manda al mazo a quien tenía el turno y después reparte la mano siguiente.
        $this->travelTo($partida->fresh()->plazo_vence_en);
        $this->assertTrue($this->mesa()->resolverPlazo($partida->id));
        $this->travelTo($partida->fresh()->plazo_vence_en);
        $this->assertTrue($this->mesa()->resolverPlazo($partida->id));

        $ahora = $partida->eventos()->count();
        $this->assertSame($visto + 2, $ahora);

        // Quien tiene el turno en la mano nueva todavía mira la anterior. "Al mazo" vale en las dos, pero no entra.
        $conTurno = $this->quienTieneElTurno($partida);
        $jugador = [$uno, $dos][$conTurno];

        $this->actingAs($jugador)->postJson('/mesa/accion', ['tipo' => 'mazo', 'desde' => $visto])->assertStatus(422);
        $this->assertSame($ahora, $partida->eventos()->count());

        // Lo que pasó en el medio lo recibe por la consulta, y ahí sí puede jugar.
        $pasos = $this->actingAs($jugador)->getJson("/mesa/estado?partida={$partida->id}&desde={$visto}")->assertOk()->json('pasos');
        $this->assertSame([$visto + 1, $visto + 2], array_column($pasos, 'evento'));

        $this->actingAs($jugador)->postJson('/mesa/accion', ['tipo' => 'mazo', 'desde' => $ahora])->assertOk();
    }

    public function test_no_se_reparte_sobre_una_mano_que_la_mesa_no_mostro(): void
    {
        [$partida, $uno] = $this->partidaEnCurso();

        // Se cierra la primera mano (alguien deja vencer su turno) y la mesa lo ve.
        $this->travelTo($partida->fresh()->plazo_vence_en);
        $this->mesa()->resolverPlazo($partida->id);
        $visto = $partida->eventos()->count();

        // Después se reparte sola y la segunda también se cierra por un turno vencido.
        $this->travelTo($partida->fresh()->plazo_vence_en);
        $this->mesa()->resolverPlazo($partida->id);
        $this->travelTo($partida->fresh()->plazo_vence_en);
        $this->mesa()->resolverPlazo($partida->id);
        $ahora = $partida->eventos()->count();

        // El "Repartir" de la primera mano no reparte la tercera.
        $this->actingAs($uno)->postJson('/mesa/repartir', ['desde' => $visto])->assertStatus(422);
        $this->assertSame($ahora, $partida->eventos()->count());

        $this->actingAs($uno)->postJson('/mesa/repartir', ['desde' => $ahora])->assertOk();
    }

    public function test_sin_decir_el_evento_la_mesa_acepta_la_jugada_y_el_pedido_directo_tambien_lo_exige(): void
    {
        [$partida] = $this->partidaEnCurso();
        $conTurno = $this->quienTieneElTurno($partida);
        $ultimo = $partida->eventos()->count();
        $carta = Accion::desdeArray(collect($this->mesa()->vista($partida, $conTurno)['acciones'])->firstWhere('tipo', 'jugar'));

        try {
            $this->mesa()->actuar($partida, $carta, $conTurno, $ultimo - 2);
            $this->fail('Una jugada sobre un evento viejo tenía que rechazarse.');
        } catch (AccionInvalida $rechazo) {
            $this->assertSame('La mesa cambió mientras jugabas.', $rechazo->getMessage());
        }

        $this->assertSame($ultimo, $partida->eventos()->count());

        // Sin el dato, como lo usan el bot y los tests de antes, la jugada vale.
        $this->mesa()->actuar($partida, $carta, $conTurno);
        $this->assertSame($ultimo + 1, $partida->eventos()->count());
    }

    public function test_la_mesa_de_quien_abrio_la_sala_avisa_que_llego_y_queda_al_dia(): void
    {
        [$partida, $creador, $invitado] = $this->partidaRecienSentada();
        $mostrado = $partida->eventos()->count();

        // Mientras no llegó, su mesa sabe que tiene que avisar; la del invitado no tiene nada que avisar.
        $this->actingAs($creador)->get('/mesa')->assertOk()->assertViewHas('faltaLlegar', true);
        $this->actingAs($invitado)->get('/mesa')->assertOk()->assertViewHas('faltaLlegar', false);

        // El aviso del invitado no anota nada.
        $this->actingAs($invitado)->postJson('/mesa/presente', ['desde' => $mostrado])->assertOk()->assertExactJson(['pasos' => []]);
        $this->assertSame($mostrado, $partida->eventos()->count());

        // El de quien abrió la sala sí, y la respuesta trae ese paso: su mesa queda al día y puede jugar.
        $pasos = $this->actingAs($creador)->postJson('/mesa/presente', ['desde' => $mostrado])->assertOk()->json('pasos');
        $this->assertSame([$mostrado + 1], array_column($pasos, 'evento'));
        $this->assertSame([], $pasos[0]['hechos']);

        // Recargar la mesa después no vuelve a avisar.
        $this->actingAs($creador)->get('/mesa')->assertOk()->assertViewHas('faltaLlegar', false);
        $this->actingAs($creador)->postJson('/mesa/presente', ['desde' => $mostrado + 1])->assertOk()->assertExactJson(['pasos' => []]);
        $this->assertSame($mostrado + 1, $partida->eventos()->count());
    }

    public function test_el_aviso_de_llegada_no_sirve_sin_sesion_ni_sin_partida_ni_sin_decir_que_se_mostro(): void
    {
        [, $creador] = $this->partidaRecienSentada();

        $this->postJson('/mesa/presente', ['desde' => 0])->assertUnauthorized();
        $this->actingAs(Jugador::factory()->invitado()->create())->postJson('/mesa/presente', ['desde' => 0])->assertStatus(409);
        $this->actingAs($creador)->postJson('/mesa/presente')->assertStatus(422);
    }

    public function test_lo_que_falta_del_plazo_se_lee_junto_con_los_eventos(): void
    {
        [$partida] = $this->partidaEnCurso();
        $conTurno = $this->quienTieneElTurno($partida);

        // El pedido cargó la partida cuando al turno le quedaban 15 segundos…
        $this->travel(Mesa::SEGUNDOS_DE_TURNO - 15)->seconds();
        $cargadaAntes = Partida::query()->findOrFail($partida->id);

        // …y antes de que leyera los eventos, quien tenía el turno jugó: ahora corre el plazo del otro, entero.
        $carta = collect($this->mesa()->vista($partida, $conTurno)['acciones'])->firstWhere('tipo', 'jugar');
        $this->mesa()->actuar($partida, Accion::desdeArray($carta), $conTurno);

        // La vista trae el turno nuevo con su plazo, no con lo que le quedaba al anterior.
        $vista = $this->mesa()->vista($cargadaAntes, 1 - $conTurno);
        $this->assertNotSame([], $vista['acciones']);
        $this->assertSame(Mesa::SEGUNDOS_DE_TURNO, $vista['restan']);

        $pasos = $this->mesa()->pasosDesde(Partida::query()->findOrFail($partida->id), 0, 1 - $conTurno);
        $this->assertSame(Mesa::SEGUNDOS_DE_TURNO, $pasos[count($pasos) - 1]['restan']);
    }

    private function mesa(): Mesa
    {
        return $this->app->make(Mesa::class);
    }

    /**
     * Una partida en curso con los dos en la mesa: quien abrió la sala ya avisó que llegó.
     *
     * @return array{0: Partida, 1: Jugador, 2: Jugador}
     */
    private function partidaEnCurso(): array
    {
        [$partida, $uno, $dos] = $this->partidaRecienSentada();
        $this->mesa()->llegar($partida, 0);

        return [$partida, $uno, $dos];
    }

    /**
     * @return array{0: Partida, 1: Jugador, 2: Jugador}
     */
    private function partidaRecienSentada(): array
    {
        $uno = Jugador::factory()->invitado()->create();
        $dos = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($uno);

        return [$this->mesa()->sentarse($sala->codigo, $dos), $uno, $dos];
    }

    private function quienTieneElTurno(Partida $partida): ?int
    {
        foreach ([0, 1] as $asiento) {
            if ($this->mesa()->vista($partida->fresh(), $asiento)['acciones'] !== []) {
                return $asiento;
            }
        }

        return null;
    }
}
