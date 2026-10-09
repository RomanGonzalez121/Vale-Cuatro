<?php

namespace Tests\Feature;

use App\Juego\Mesa;
use App\Juego\SalaNoDisponible;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La sala de una partida entre personas: se abre, se le manda el link a alguien, esa persona se sienta
 * y empieza la partida. Antes de que se siente nadie, no hay mano ni cartas repartidas.
 */
class SalaTest extends TestCase
{
    use RefreshDatabase;

    public function test_abrir_una_sala_deja_la_partida_esperando_con_su_link_y_sin_cartas(): void
    {
        $creador = Jugador::factory()->invitado()->create();

        $sala = $this->mesa()->crearSala($creador)->fresh();

        $this->assertTrue($sala->esperando());
        $this->assertTrue($sala->entre_personas);
        $this->assertSame($creador->id, $sala->jugador_id);
        $this->assertNull($sala->invitado_id);
        $this->assertNull($sala->primer_mano);
        $this->assertNull($sala->nivel_bot);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{16}$/', $sala->codigo);
        $this->assertSame(0, $sala->eventos()->count(), 'Todavía no se repartió nada.');
    }

    public function test_al_sentarse_empieza_la_partida_con_las_cartas_repartidas_y_cada_uno_en_su_asiento(): void
    {
        $creador = Jugador::factory()->invitado()->create();
        $rival = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($creador);

        $partida = $this->mesa()->sentarse($sala->codigo, $rival)->fresh();

        $this->assertTrue($partida->enCurso());
        $this->assertSame($rival->id, $partida->invitado_id);
        $this->assertContains($partida->primer_mano, [0, 1]);
        $this->assertSame(0, $partida->asientoDe($creador));
        $this->assertSame(1, $partida->asientoDe($rival));

        $reparto = $partida->eventos()->get();
        $this->assertCount(1, $reparto);
        $this->assertSame(EventoDePartida::REPARTO, $reparto[0]->tipo);

        // Cada uno ve su mano, y las dos son distintas.
        $manos = [$this->mesa()->vista($partida, 0)['misCartas'], $this->mesa()->vista($partida, 1)['misCartas']];
        $this->assertCount(3, $manos[0]);
        $this->assertCount(3, $manos[1]);
        $this->assertSame([], array_intersect($manos[0], $manos[1]));
    }

    public function test_el_primer_mano_se_sortea_entre_los_dos_asientos(): void
    {
        $salio = [];

        foreach (range(1, 40) as $i) {
            $creador = Jugador::factory()->invitado()->create();
            $sala = $this->mesa()->crearSala($creador);

            $salio[$this->mesa()->sentarse($sala->codigo, Jugador::factory()->invitado()->create())->primer_mano] = true;
        }

        ksort($salio);

        $this->assertSame([0, 1], array_keys($salio), 'En 40 partidas tiene que haber salido mano cada uno.');
    }

    public function test_abrir_el_link_otra_vez_no_hace_nada_y_quien_abrio_la_sala_en_su_propio_link_tampoco(): void
    {
        $creador = Jugador::factory()->invitado()->create();
        $rival = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($creador);

        // Quien abrió la sala toca su propio link mientras espera: sigue esperando, sin rival.
        $this->assertTrue($this->mesa()->sentarse($sala->codigo, $creador)->esperando());
        $this->assertNull($sala->fresh()->invitado_id);

        $this->mesa()->sentarse($sala->codigo, $rival);
        $eventos = $sala->eventos()->count();

        // Recargar la página de la invitación: no reparte de nuevo ni cambia nada.
        $this->mesa()->sentarse($sala->codigo, $rival);
        $this->mesa()->sentarse($sala->codigo, $creador);

        $this->assertSame($eventos, $sala->eventos()->count());
        $this->assertSame($rival->id, $sala->fresh()->invitado_id);
    }

    public function test_no_se_entra_a_una_partida_terminada_ni_a_un_link_que_no_existe(): void
    {
        $creador = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($creador);
        $this->mesa()->cancelarSala($sala);

        try {
            $this->mesa()->sentarse($sala->codigo, Jugador::factory()->invitado()->create());
            $this->fail('Se sentó en una sala cerrada.');
        } catch (SalaNoDisponible $motivo) {
            $this->assertSame('Esa partida ya terminó.', $motivo->getMessage());
        }

        $this->expectException(ModelNotFoundException::class);
        $this->mesa()->sentarse('estenoexiste0000', Jugador::factory()->invitado()->create());
    }

    public function test_quien_tiene_otra_partida_sin_terminar_no_se_sienta_en_la_sala(): void
    {
        $ocupado = Jugador::factory()->invitado()->create();
        $this->mesa()->abrir($ocupado);
        $sala = $this->mesa()->crearSala(Jugador::factory()->invitado()->create());

        try {
            $this->mesa()->sentarse($sala->codigo, $ocupado);
            $this->fail('Se sentó quien ya estaba jugando.');
        } catch (SalaNoDisponible $motivo) {
            $this->assertStringContainsString('otra partida sin terminar', $motivo->getMessage());
        }

        $this->assertTrue($sala->fresh()->esperando());
    }

    public function test_dos_personas_a_la_vez_no_pueden_sentarse_en_la_misma_sala(): void
    {
        $sala = $this->mesa()->crearSala(Jugador::factory()->invitado()->create());
        $primero = Jugador::factory()->invitado()->create();
        $segundo = Jugador::factory()->invitado()->create();

        $this->mesa()->sentarse($sala->codigo, $primero);

        // El segundo llega con la sala ya ocupada: aunque su pedido se haya armado antes, el estado manda.
        $this->expectException(SalaNoDisponible::class);
        $this->mesa()->sentarse($sala->codigo, $segundo);
    }

    public function test_las_salas_que_esperaron_demasiado_se_cierran_solas_y_las_demas_no(): void
    {
        $vieja = $this->mesa()->crearSala(Jugador::factory()->invitado()->create());
        $reciente = $this->mesa()->crearSala(Jugador::factory()->invitado()->create());
        $conRival = $this->mesa()->crearSala(Jugador::factory()->invitado()->create());
        $this->mesa()->sentarse($conRival->codigo, Jugador::factory()->invitado()->create());
        $contraElBot = $this->mesa()->abrir(Jugador::factory()->invitado()->create());

        $hace = now()->subMinutes(Mesa::MINUTOS_DE_SALA + 1);

        // Las cuatro tienen más de 30 minutos: solo la que sigue esperando se cierra.
        Partida::query()->update(['created_at' => $hace]);
        $reciente->forceFill(['created_at' => now()->subMinutes(Mesa::MINUTOS_DE_SALA - 1)])->saveQuietly();

        $this->artisan('salas:limpiar')->expectsOutput('Salas cerradas: 1.')->assertSuccessful();

        $this->assertSame(Partida::ABANDONADA, $vieja->fresh()->estado);
        $this->assertNotNull($vieja->fresh()->terminada_en);
        $this->assertTrue($reciente->fresh()->esperando());
        $this->assertTrue($conRival->fresh()->enCurso());
        $this->assertTrue($contraElBot->fresh()->enCurso());
    }

    private function mesa(): Mesa
    {
        return $this->app->make(Mesa::class);
    }
}
