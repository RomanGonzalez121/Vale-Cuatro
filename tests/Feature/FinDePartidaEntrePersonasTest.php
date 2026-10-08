<?php

namespace Tests\Feature;

use App\Juego\Mesa;
use App\Models\Jugador;
use App\Models\Partida;
use App\Motor\Accion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Cómo se entera cada persona de que la partida terminó cuando no estaba mirando, y la red de seguridad
 * con la que la mesa le pide al servidor que resuelva un plazo ya cumplido.
 */
class FinDePartidaEntrePersonasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Los trabajos con demora quedan registrados y no corren: acá se llama a resolverPlazo() a mano.
        Queue::fake();
    }

    public function test_si_el_rival_abandona_quien_se_quedo_lo_sabe_al_volver_y_quien_se_fue_no(): void
    {
        [$partida, $uno, $dos] = $this->partidaEnCurso();

        $this->mesa()->abandonar($partida, 1);

        $this->actingAs($uno)->get('/mesa')->assertRedirect(route('modos'))->assertSessionHas('aviso', 'Tu rival abandonó la partida: ganaste.');
        $this->actingAs($dos)->get('/mesa')->assertRedirect(route('modos'))->assertSessionMissing('aviso');
    }

    public function test_quien_deja_vencer_su_turno_tres_veces_pierde_y_los_dos_se_enteran_de_por_que(): void
    {
        [$partida, $uno, $dos] = $this->partidaEnCurso();

        for ($vencimiento = 0; $vencimiento < Mesa::VENCIMIENTOS_PARA_PERDER; $vencimiento++) {
            $this->dejarVencerA(0, $partida);
        }

        $this->assertSame(Partida::ABANDONADA, $partida->fresh()->estado);

        $this->actingAs($dos)->get('/mesa')->assertRedirect(route('modos'))->assertSessionHas('aviso', 'Tu rival dejó de jugar y perdió la partida.');
        $this->actingAs($uno)->get('/mesa')->assertRedirect(route('modos'))->assertSessionHas('aviso', 'Perdiste la partida: se te venció el turno 3 veces seguidas.');
    }

    public function test_una_sala_cancelada_y_una_partida_contra_el_bot_abandonada_no_dicen_nada(): void
    {
        $creador = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($creador);
        $this->mesa()->cancelarSala($sala);

        $this->actingAs($creador)->get('/mesa')->assertRedirect(route('modos'))->assertSessionMissing('aviso');

        $jugador = Jugador::factory()->invitado()->create();
        $contraElBot = $this->mesa()->abrir($jugador);
        $this->mesa()->abandonar($contraElBot);

        $this->actingAs($jugador)->get('/mesa')->assertRedirect(route('modos'))->assertSessionMissing('aviso');
    }

    public function test_la_red_de_seguridad_resuelve_el_plazo_cumplido_y_no_antes(): void
    {
        [$partida, $uno, $dos] = $this->partidaEnCurso();
        $conTurno = $this->quienTieneElTurno($partida);
        $jugador = [$uno, $dos][$conTurno];
        $otro = [$uno, $dos][1 - $conTurno];
        $eventos = $partida->eventos()->count();

        // Antes del plazo, cualquiera de los dos que lo pida no logra nada.
        $this->actingAs($otro)->postJson(route('mesa.plazo'))->assertOk()->assertExactJson(['resolvio' => false]);
        $this->assertSame($eventos, $partida->eventos()->count());

        // Cumplido el plazo, lo resuelve el pedido de quien esté mirando, sea quien sea, y una sola vez.
        $this->travelTo($partida->fresh()->plazo_vence_en);
        $this->actingAs($otro)->postJson(route('mesa.plazo'))->assertOk()->assertExactJson(['resolvio' => true]);
        $this->actingAs($jugador)->postJson(route('mesa.plazo'))->assertOk()->assertExactJson(['resolvio' => false]);

        $this->assertSame($eventos + 1, $partida->eventos()->count());
    }

    public function test_la_red_de_seguridad_no_sirve_a_un_tercero_ni_contra_el_bot(): void
    {
        [$partida] = $this->partidaEnCurso();
        $this->travelTo($partida->fresh()->plazo_vence_en->copy()->addMinute());
        $eventos = $partida->eventos()->count();

        $this->actingAs(Jugador::factory()->invitado()->create())->postJson(route('mesa.plazo'))->assertStatus(409);
        $this->assertSame($eventos, $partida->eventos()->count(), 'Un tercero no resuelve plazos ajenos.');

        $jugador = Jugador::factory()->invitado()->create();
        $this->mesa()->abrir($jugador);
        $this->actingAs($jugador)->postJson(route('mesa.plazo'))->assertOk()->assertExactJson(['resolvio' => false]);
    }

    public function test_sin_sesion_no_se_pide_la_red_de_seguridad(): void
    {
        $this->postJson(route('mesa.plazo'))->assertUnauthorized();
    }

    private function mesa(): Mesa
    {
        return $this->app->make(Mesa::class);
    }

    /**
     * @return array{0: Partida, 1: Jugador, 2: Jugador}
     */
    private function partidaEnCurso(): array
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

    /**
     * Lleva la partida hasta el turno de ese asiento y lo deja pasar sin jugar: el servidor lo manda al mazo.
     * Mientras tanto el otro juega lo primero que puede y, con la mano cerrada, se espera el reparto automático.
     */
    private function dejarVencerA(int $asiento, Partida $partida): void
    {
        for ($i = 0; $i < 200; $i++) {
            $partida->refresh();
            $conTurno = $this->quienTieneElTurno($partida);

            if ($conTurno === $asiento) {
                $this->travelTo($partida->plazo_vence_en);
                $this->assertTrue($this->mesa()->resolverPlazo($partida->id));

                return;
            }

            if ($conTurno === null) {
                $this->travelTo($partida->plazo_vence_en);
                $this->assertTrue($this->mesa()->resolverPlazo($partida->id));

                continue;
            }

            $acciones = $this->mesa()->vista($partida, $conTurno)['acciones'];
            $this->mesa()->actuar($partida, Accion::desdeArray($acciones[0]), $conTurno);
        }

        $this->fail('No llegó nunca el turno del asiento '.$asiento);
    }
}
