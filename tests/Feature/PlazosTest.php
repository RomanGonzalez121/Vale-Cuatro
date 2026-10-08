<?php

namespace Tests\Feature;

use App\Jobs\ResolverPlazo;
use App\Juego\Mesa;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Motor\Accion;
use App\Motor\Fase;
use App\Motor\TipoDeAccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Lo que una partida entre personas espera y se resuelve solo en el servidor: el turno de quien tiene que
 * jugar (45 segundos, después se va al mazo; con 3 vencimientos seguidos pierde la partida) y el reparto de la
 * mano siguiente (6 segundos). El reloj se controla en cada test: ninguno espera de verdad.
 */
class PlazosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Los trabajos con demora quedan registrados y no corren: cada test llama a resolverPlazo() cuando quiere.
        Queue::fake();
    }

    public function test_al_empezar_la_partida_corre_el_plazo_del_primer_turno(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$partida] = $this->partidaEnCurso();

        $this->assertSame(now()->addSeconds(Mesa::SEGUNDOS_DE_TURNO)->getTimestamp(), $partida->fresh()->plazo_vence_en->getTimestamp());

        // Los dos ven cuánto falta, y es lo mismo.
        $this->assertSame(Mesa::SEGUNDOS_DE_TURNO, $this->mesa()->vista($partida, 0)['restan']);
        $this->assertSame(Mesa::SEGUNDOS_DE_TURNO, $this->mesa()->vista($partida, 1)['restan']);
    }

    public function test_el_trabajo_sale_a_la_hora_del_plazo_con_el_ultimo_evento(): void
    {
        [$partida] = $this->partidaEnCurso();

        Queue::assertPushed(ResolverPlazo::class, function (ResolverPlazo $trabajo) use ($partida) {
            return $trabajo->partidaId === $partida->id
                && $trabajo->evento === 1
                && $trabajo->delay->getTimestamp() === $partida->fresh()->plazo_vence_en->getTimestamp();
        });
    }

    public function test_lo_que_falta_baja_con_el_tiempo_y_nunca_es_negativo(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$partida] = $this->partidaEnCurso();

        $this->travel(10)->seconds();
        $this->assertSame(Mesa::SEGUNDOS_DE_TURNO - 10, $this->mesa()->vista($partida, 0)['restan']);

        $this->travel(100)->seconds();
        $this->assertSame(0, $this->mesa()->vista($partida, 0)['restan']);
    }

    public function test_antes_del_plazo_no_se_resuelve_nada(): void
    {
        [$partida] = $this->partidaEnCurso();
        $eventos = $partida->eventos()->count();

        $this->travelTo($partida->fresh()->plazo_vence_en->copy()->subSecond());

        $this->assertFalse($this->mesa()->resolverPlazo($partida->id));
        $this->assertSame($eventos, $partida->eventos()->count());
    }

    public function test_vencido_el_turno_el_servidor_manda_al_mazo_a_quien_lo_tenia(): void
    {
        [$partida] = $this->partidaEnCurso();
        $conTurno = $this->quienTieneElTurno($partida);
        $eventos = $partida->eventos()->count();

        $this->travelTo($partida->fresh()->plazo_vence_en);

        $this->assertTrue($this->mesa()->resolverPlazo($partida->id));

        $ultimo = $partida->eventos()->get()->last();
        $this->assertSame($eventos + 1, $ultimo->numero);
        $this->assertSame(EventoDePartida::VENCIMIENTO, $ultimo->tipo);
        $this->assertSame($conTurno, $ultimo->asiento);
        $this->assertSame(['tipo' => 'mazo'], $ultimo->datos);

        // El mazo cierra la mano y el rival suma: sin envido jugado, 2 en la primera baza.
        $motor = $this->mesa()->reconstruir($partida);
        $this->assertSame(Fase::PorRepartir, $motor->fase());
        $this->assertSame(2, $motor->tanteo()[1 - $conTurno]);
        $this->assertSame(0, $motor->tanteo()[$conTurno]);
    }

    public function test_un_vencimiento_queda_en_los_eventos_y_se_reconstruye_igual(): void
    {
        [$partida] = $this->partidaEnCurso();
        $this->travelTo($partida->fresh()->plazo_vence_en);
        $this->mesa()->resolverPlazo($partida->id);

        // El estado reconstruido desde los eventos es el mismo que ve cada jugador.
        foreach ([0, 1] as $asiento) {
            $pasos = $this->mesa()->pasosDesde($partida, 0, $asiento);

            $this->assertSame($this->mesa()->vista($partida, $asiento), $pasos[count($pasos) - 1]);
        }
    }

    public function test_con_un_canto_sin_contestar_el_vencimiento_vale_como_no_querer(): void
    {
        [$partida] = $this->partidaEnCurso();
        $canta = $this->quienTieneElTurno($partida);

        $this->mesa()->actuar($partida, Accion::de(TipoDeAccion::Truco), $canta);

        // Ahora le toca contestar al otro, que no lo hace.
        $this->assertSame(1 - $canta, $this->quienTieneElTurno($partida));
        $this->travelTo($partida->fresh()->plazo_vence_en);
        $this->assertTrue($this->mesa()->resolverPlazo($partida->id));

        $ultimo = $partida->eventos()->get()->last();
        $this->assertSame(EventoDePartida::VENCIMIENTO, $ultimo->tipo);
        $this->assertSame(1 - $canta, $ultimo->asiento);

        // Quien no contestó pierde la mano y el truco no querido le da punto a quien cantó.
        $motor = $this->mesa()->reconstruir($partida);
        $this->assertSame(Fase::PorRepartir, $motor->fase());
        $this->assertGreaterThan(0, $motor->tanteo()[$canta]);
        $this->assertSame(0, $motor->tanteo()[1 - $canta]);
    }

    public function test_si_alguien_juega_antes_el_trabajo_viejo_no_hace_nada(): void
    {
        [$partida] = $this->partidaEnCurso();
        $conTurno = $this->quienTieneElTurno($partida);
        $plazoViejo = $partida->fresh()->plazo_vence_en;

        // Juega a tiempo: ahora el turno es del otro, con un plazo nuevo.
        $this->mesa()->actuar($partida, Accion::de(TipoDeAccion::Truco), $conTurno);
        $eventos = $partida->eventos()->count();

        $this->travelTo($plazoViejo->copy()->addMinute());

        // El trabajo que había quedado con el evento 1 ya no corresponde.
        $this->assertFalse($this->mesa()->resolverPlazo($partida->id, 1));
        $this->assertSame($eventos, $partida->eventos()->count());
    }

    public function test_con_la_mano_cerrada_se_reparte_sola_a_los_6_segundos(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$partida] = $this->partidaEnCurso();
        $primero = $this->quienTieneElTurno($partida);

        $this->mesa()->actuar($partida, Accion::de(TipoDeAccion::Mazo), $primero);

        $partida->refresh();
        $this->assertSame(now()->addSeconds(Mesa::SEGUNDOS_PARA_REPARTIR)->getTimestamp(), $partida->plazo_vence_en->getTimestamp());
        $this->assertSame(Mesa::SEGUNDOS_PARA_REPARTIR, $this->mesa()->vista($partida, 0)['restan']);

        // A los 5 segundos todavía no.
        $this->travel(Mesa::SEGUNDOS_PARA_REPARTIR - 1)->seconds();
        $this->assertFalse($this->mesa()->resolverPlazo($partida->id));
        $this->assertSame(Fase::PorRepartir, $this->mesa()->reconstruir($partida)->fase());

        // A los 6, se reparte la segunda mano, con el otro de mano, y vuelve a correr el plazo del turno.
        $this->travel(1)->seconds();
        $this->assertTrue($this->mesa()->resolverPlazo($partida->id));

        $motor = $this->mesa()->reconstruir($partida);
        $this->assertSame(Fase::Jugando, $motor->fase());
        $this->assertSame(2, $this->mesa()->vista($partida, 0)['numeroDeMano']);
        $this->assertSame(2, $partida->eventos()->where('tipo', EventoDePartida::REPARTO)->count());
        $this->assertSame(1 - $primero, $this->quienTieneElTurno($partida), 'En la segunda mano sale el otro.');
        $this->assertSame(Mesa::SEGUNDOS_DE_TURNO, $this->mesa()->vista($partida->fresh(), 0)['restan']);
    }

    public function test_si_alguien_apura_el_reparto_el_trabajo_no_reparte_otra_vez(): void
    {
        [$partida] = $this->partidaEnCurso();
        $this->mesa()->actuar($partida, Accion::de(TipoDeAccion::Mazo), $this->quienTieneElTurno($partida));

        // Toca la mesa y reparte ya.
        $this->mesa()->repartir($partida, 0);
        $repartos = $partida->eventos()->where('tipo', EventoDePartida::REPARTO)->count();

        $this->travelTo(now()->addMinute());

        // El trabajo que esperaba el reparto automático, con el evento del cierre, ya no hace nada.
        $this->assertFalse($this->mesa()->resolverPlazo($partida->id, 2));
        $this->assertSame($repartos, $partida->eventos()->where('tipo', EventoDePartida::REPARTO)->count());
    }

    public function test_a_los_tres_vencimientos_seguidos_pierde_la_partida(): void
    {
        [$partida] = $this->partidaEnCurso();
        $lento = 0;

        for ($vencimiento = 1; $vencimiento <= Mesa::VENCIMIENTOS_PARA_PERDER; $vencimiento++) {
            $this->dejarVencerA($lento, $partida);

            $partida->refresh();

            if ($vencimiento < Mesa::VENCIMIENTOS_PARA_PERDER) {
                $this->assertTrue($partida->enCurso(), "Con {$vencimiento} vencimientos todavía no se pierde.");
            }
        }

        $this->assertSame(Partida::ABANDONADA, $partida->estado);
        $this->assertSame(1, $partida->ganador, 'Gana el otro asiento.');
        $this->assertNull($partida->plazo_vence_en);

        $ultimo = $partida->eventos()->get()->last();
        $this->assertSame(EventoDePartida::ABANDONO, $ultimo->tipo);
        $this->assertSame($lento, $ultimo->asiento);
        $this->assertSame(['motivo' => 'vencimientos'], $ultimo->datos);
    }

    public function test_una_jugada_propia_en_el_medio_corta_la_cuenta_de_vencimientos(): void
    {
        [$partida] = $this->partidaEnCurso();
        $lento = 0;

        $this->dejarVencerA($lento, $partida);
        $this->dejarVencerA($lento, $partida);

        // Juega de verdad una vez: la cuenta vuelve a cero.
        $this->esperarSuTurno($lento, $partida);
        $acciones = $this->mesa()->vista($partida, $lento)['acciones'];
        $this->mesa()->actuar($partida, Accion::desdeArray($acciones[0]), $lento);

        // Dos vencimientos más no alcanzan para perder: hacen falta tres seguidos.
        $this->dejarVencerA($lento, $partida);
        $this->dejarVencerA($lento, $partida);

        $this->assertTrue($partida->fresh()->enCurso());

        $this->dejarVencerA($lento, $partida);

        $this->assertSame(Partida::ABANDONADA, $partida->fresh()->estado);
    }

    public function test_una_partida_cerrada_no_espera_nada(): void
    {
        [$partida] = $this->partidaEnCurso();

        $this->mesa()->abandonar($partida, 0);

        $this->assertNull($partida->fresh()->plazo_vence_en);
        $this->travelTo(now()->addHour());
        $this->assertFalse($this->mesa()->resolverPlazo($partida->id));
    }

    public function test_la_partida_contra_el_bot_no_tiene_plazo(): void
    {
        $partida = $this->mesa()->abrir(Jugador::factory()->invitado()->create());

        $this->assertNull($partida->fresh()->plazo_vence_en);
        $this->assertArrayNotHasKey('restan', $this->mesa()->vista($partida));

        $this->travelTo(now()->addHour());
        $this->assertFalse($this->mesa()->resolverPlazo($partida->id));
        Queue::assertNotPushed(ResolverPlazo::class);
    }

    public function test_al_cerrarse_la_partida_no_queda_un_trabajo_nuevo_en_la_cola(): void
    {
        [$partida] = $this->partidaEnCurso();

        $this->mesa()->abandonar($partida, 1);

        // El único trabajo es el del primer turno, que se encoló al sentarse: cerrar no suma otro.
        Queue::assertPushed(ResolverPlazo::class, 1);
        $this->assertNull($partida->fresh()->plazo_vence_en);
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
     * Hace pasar las manos hasta que le toca a ese asiento: el otro juega lo primero que puede y, con la
     * mano cerrada, se espera el reparto automático.
     */
    private function esperarSuTurno(int $asiento, Partida $partida): void
    {
        for ($i = 0; $i < 200; $i++) {
            $partida->refresh();
            $conTurno = $this->quienTieneElTurno($partida);

            if ($conTurno === $asiento) {
                return;
            }

            if ($conTurno === null) {
                // Mano cerrada: se espera el reparto automático.
                $this->travelTo($partida->plazo_vence_en);
                $this->assertTrue($this->mesa()->resolverPlazo($partida->id));

                continue;
            }

            $acciones = $this->mesa()->vista($partida, $conTurno)['acciones'];
            $this->mesa()->actuar($partida, Accion::desdeArray($acciones[0]), $conTurno);
        }

        $this->fail('No llegó nunca el turno del asiento '.$asiento);
    }

    /**
     * Lleva la partida hasta el turno de ese asiento y lo deja pasar sin jugar: vence y el servidor lo manda al mazo.
     */
    private function dejarVencerA(int $asiento, Partida $partida): void
    {
        $this->esperarSuTurno($asiento, $partida);

        $this->travelTo($partida->fresh()->plazo_vence_en);
        $this->assertTrue($this->mesa()->resolverPlazo($partida->id));
    }
}
