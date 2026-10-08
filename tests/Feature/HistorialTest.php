<?php

namespace Tests\Feature;

use App\Juego\Historial;
use App\Juego\Nivel;
use App\Models\Jugador;
use App\Models\Partida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La lista del historial: qué partidas entran y qué dice de cada una. Todo sale de la fila de la partida
 * y de sus eventos; no hay columnas de tanteo, de manos ni de duración.
 */
class HistorialTest extends TestCase
{
    use JugandoPartidas;
    use RefreshDatabase;

    public function test_resume_una_partida_terminada_con_lo_que_dicen_sus_eventos(): void
    {
        $this->travelTo(now()->startOfMinute());
        $jugador = Jugador::factory()->create();
        [$partida] = $this->partidaContraElBot($jugador, Nivel::Dificil);
        $estado = $this->laMesa()->reconstruir($partida)->aArray();

        // La partida duró lo que haya entre su primer evento y su cierre.
        $partida->eventos()->getQuery()->where('numero', 1)->update(['creado_en' => now()->subMinutes(9)]);

        $resumen = $this->historial()->resumen($partida->fresh(), 0);

        $this->assertSame($partida->id, $resumen['id']);
        $this->assertSame($partida->ganador === 0, $resumen['gano']);
        $this->assertSame($estado['tanteo'][0], $resumen['vos']);
        $this->assertSame($estado['tanteo'][1], $resumen['ellos']);
        $this->assertSame(30, max($resumen['vos'], $resumen['ellos']));
        $this->assertSame($estado['numeroDeMano'], $resumen['manos']);
        $this->assertSame('Bot difícil', $resumen['rival']);
        $this->assertTrue($resumen['bot']);
        $this->assertNull($resumen['cierre'], 'Una partida que llegó a los puntos no necesita aclaración.');
        $this->assertSame('Hoy', $resumen['dia']);
        $this->assertSame(9, $resumen['minutos']);
    }

    public function test_entre_personas_cada_uno_la_ve_desde_su_asiento(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(['apodo' => 'Roman']), Jugador::factory()->create(['apodo' => 'La Tana'])];
        $partida = $this->partidaEntrePersonas($uno, $dos);

        $deUno = $this->historial()->resumen($partida, 0);
        $deDos = $this->historial()->resumen($partida, 1);

        $this->assertSame(['La Tana', 'Roman'], [$deUno['rival'], $deDos['rival']]);
        $this->assertFalse($deUno['bot']);
        $this->assertSame([$deUno['vos'], $deUno['ellos']], [$deDos['ellos'], $deDos['vos']]);
        $this->assertNotSame($deUno['gano'], $deDos['gano']);
    }

    public function test_entran_las_partidas_cerradas_y_no_las_que_se_juegan_ni_las_salas_canceladas(): void
    {
        $jugador = Jugador::factory()->create();
        $mesa = $this->laMesa();

        [$terminada] = $this->partidaContraElBot($jugador);

        $this->travel(1)->minutes();
        $abandonada = $mesa->abrir($jugador);
        $mesa->abandonar($abandonada);

        $this->travel(1)->minutes();
        $sala = $mesa->crearSala($jugador);
        $mesa->cancelarSala($sala);

        $this->travel(1)->minutes();
        $enCurso = $mesa->abrir($jugador);

        // De la más nueva a la más vieja. La sala sin rival nunca fue una partida, y la que se juega todavía no cerró.
        $this->assertSame([$abandonada->id, $terminada->id], $this->historial()->cerradasDe($jugador)->pluck('id')->all());
        $this->assertNull($this->historial()->cerradaDe($jugador, $enCurso->id));
        $this->assertNull($this->historial()->cerradaDe($jugador, $sala->id));
        $this->assertNotNull($this->historial()->cerradaDe($jugador, $terminada->id));

        // La de otro no es suya, aunque sepa el número.
        $this->assertNull($this->historial()->cerradaDe(Jugador::factory()->create(), $terminada->id));
    }

    public function test_una_partida_que_no_llego_al_final_dice_por_que_se_cerro(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        $mesa = $this->laMesa();

        $partida = $mesa->sentarse($mesa->crearSala($uno)->codigo, $dos);
        $mesa->abandonar($partida, 1);

        $this->assertSame('Tu rival la abandonó.', $this->historial()->resumen($partida->fresh(), 0)['cierre']);
        $this->assertSame('La abandonaste.', $this->historial()->resumen($partida->fresh(), 1)['cierre']);
        $this->assertTrue($this->historial()->resumen($partida->fresh(), 0)['gano']);

        // Tres turnos vencidos seguidos del mismo asiento cierran la partida.
        $otra = $mesa->sentarse($mesa->crearSala($uno)->codigo, $dos);
        $mesa->llegar($otra, 0);
        $perdio = null;

        for ($i = 0; $i < 40 && $otra->fresh()->enCurso(); $i++) {
            $perdio ??= $mesa->vista($otra->fresh(), 0)['acciones'] !== [] ? 0 : ($mesa->vista($otra->fresh(), 1)['acciones'] !== [] ? 1 : null);
            $this->travelTo($otra->fresh()->plazo_vence_en);
            $mesa->resolverPlazo($otra->id);
        }

        $otra->refresh();
        $this->assertSame(Partida::ABANDONADA, $otra->estado);

        $perdedor = 1 - $otra->ganador;
        $this->assertSame('Se te venció el turno 3 veces.', $this->historial()->resumen($otra, $perdedor)['cierre']);
        $this->assertSame('Tu rival dejó de jugar.', $this->historial()->resumen($otra, 1 - $perdedor)['cierre']);
    }

    public function test_quien_juega_sin_cuenta_ve_solo_lo_que_cerro_desde_que_empezo_su_sesion(): void
    {
        $invitado = Jugador::factory()->invitado()->create();
        $mesa = $this->laMesa();

        $vieja = $mesa->abrir($invitado);
        $mesa->abandonar($vieja);

        $this->travel(3)->hours();
        $sesion = now();

        $this->travel(5)->minutes();
        $nueva = $mesa->abrir($invitado);
        $mesa->abandonar($nueva);

        // Con la marca de la sesión, solo la de esta sesión. Sin marca (quien tiene cuenta), todas.
        $this->assertSame([$nueva->id], $this->historial()->cerradasDe($invitado, $sesion)->pluck('id')->all());
        $this->assertSame([$nueva->id, $vieja->id], $this->historial()->cerradasDe($invitado)->pluck('id')->all());
        $this->assertNull($this->historial()->cerradaDe($invitado, $vieja->id, $sesion));

        // No se borró nada: la partida vieja sigue en la base con sus eventos.
        $this->assertGreaterThan(0, $vieja->eventos()->count());
    }

    public function test_el_dia_se_dice_como_hablando(): void
    {
        $this->travelTo('2026-10-08 15:00:00');
        $jugador = Jugador::factory()->create();
        $mesa = $this->laMesa();
        $dias = [];

        foreach (['2026-10-08 10:00:00', '2026-10-07 23:30:00', '2026-10-02 19:48:00', '2025-12-24 21:00:00'] as $cuando) {
            $partida = $mesa->abrir($jugador);
            $mesa->abandonar($partida);
            $partida->forceFill(['terminada_en' => $cuando])->save();

            $resumen = $this->historial()->resumen($partida->fresh(), 0);
            $dias[] = "{$resumen['dia']}, {$resumen['hora']}";
        }

        $this->assertSame(['Hoy, 10:00', 'Ayer, 23:30', '2 de octubre, 19:48', '24 de diciembre de 2025, 21:00'], $dias);
    }

    private function historial(): Historial
    {
        return $this->app->make(Historial::class);
    }
}
