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
                && $trabajo->evento === $partida->eventos()->count()
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

    public function test_el_paso_de_un_vencimiento_dice_a_quien_se_le_vencio_y_el_de_una_jugada_comun_no(): void
    {
        [$partida] = $this->partidaEnCurso();
        $conTurno = $this->quienTieneElTurno($partida);

        // Una jugada elegida por quien tiene el turno: nadie se quedó sin tiempo.
        $carta = collect($this->mesa()->vista($partida, $conTurno)['acciones'])->firstWhere('tipo', 'jugar');
        $jugada = $this->mesa()->actuar($partida, Accion::desdeArray($carta), $conTurno);
        $this->assertNull($jugada[0]['vencio']);

        $siguiente = $this->quienTieneElTurno($partida);
        $this->assertSame(1 - $conTurno, $siguiente);
        $antes = $partida->eventos()->count();
        $this->travelTo($partida->fresh()->plazo_vence_en);
        $this->mesa()->resolverPlazo($partida->id);

        // Los dos se enteran de lo mismo: al recargar la página y al preguntar qué pasó.
        foreach ([0, 1] as $asiento) {
            $this->assertSame($siguiente, $this->mesa()->vista($partida, $asiento)['vencio']);
            $this->assertSame([$siguiente], array_column($this->mesa()->pasosDesde($partida, $antes, $asiento), 'vencio'));
        }

        // El reparto siguiente ya no arrastra el vencimiento.
        $this->travelTo($partida->fresh()->plazo_vence_en);
        $this->mesa()->resolverPlazo($partida->id);
        $this->assertNull($this->mesa()->vista($partida->fresh(), $siguiente)['vencio']);
    }

    public function test_mientras_quien_abrio_la_sala_no_llego_a_la_mesa_su_turno_espera_mas(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$partida] = $this->partidaDondeEmpieza(0);

        // Los dos leen la misma espera: quien abrió la sala puede estar mandando el link desde otra aplicación.
        $this->assertSame(Mesa::SEGUNDOS_DE_LLEGADA, $this->mesa()->vista($partida, 0)['restan']);
        $this->assertSame(Mesa::SEGUNDOS_DE_LLEGADA, $this->mesa()->vista($partida, 1)['restan']);
        $this->assertTrue($this->mesa()->faltaLlegar($partida, 0));

        // A quien se sentó con el link nunca le falta llegar: entra directo a la mesa y su turno es el de siempre.
        [$otra] = $this->partidaDondeEmpieza(1);
        $this->assertSame(Mesa::SEGUNDOS_DE_TURNO, $this->mesa()->vista($otra, 1)['restan']);
        $this->assertFalse($this->mesa()->faltaLlegar($otra, 1));
    }

    public function test_al_llegar_su_turno_arranca_de_cero_con_el_plazo_de_siempre(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$partida] = $this->partidaDondeEmpieza(0);
        $antes = $partida->eventos()->count();
        $cartasDelRival = $this->mesa()->vista($partida, 1)['misCartas'];

        $this->travel(100)->seconds();
        $this->assertTrue($this->mesa()->llegar($partida, 0));

        $llegada = $partida->eventos()->get()->last();
        $this->assertSame([EventoDePartida::LLEGADA, 0, $antes + 1], [$llegada->tipo, $llegada->asiento, $llegada->numero]);
        $this->assertSame(now()->addSeconds(Mesa::SEGUNDOS_DE_TURNO)->getTimestamp(), $partida->fresh()->plazo_vence_en->getTimestamp());
        $this->assertFalse($this->mesa()->faltaLlegar($partida, 0));

        // El rival se entera como de una jugada, pero no hay nada que contar: la mesa quedó igual y cambió el plazo.
        $pasos = $this->mesa()->pasosDesde($partida->fresh(), $antes, 1);
        $this->assertCount(1, $pasos);
        $this->assertSame([], $pasos[0]['hechos']);
        $this->assertSame(Mesa::SEGUNDOS_DE_TURNO, $pasos[0]['restan']);
        $this->assertSame($cartasDelRival, $pasos[0]['misCartas']);
        $this->assertSame($pasos[0], $this->mesa()->vista($partida->fresh(), 1));

        // Llegar es una sola vez: repetirlo no anota nada ni vuelve a darle tiempo.
        $this->travel(20)->seconds();
        $this->assertFalse($this->mesa()->llegar($partida, 0));
        $this->assertSame($antes + 1, $partida->eventos()->count());
        $this->assertSame(Mesa::SEGUNDOS_DE_TURNO - 20, $this->mesa()->vista($partida->fresh(), 0)['restan']);
    }

    public function test_si_llega_cuando_le_toca_al_otro_ese_plazo_no_cambia_y_se_sigue_resolviendo(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$partida] = $this->partidaDondeEmpieza(1);
        $vence = $partida->fresh()->plazo_vence_en->getTimestamp();

        $this->travel(10)->seconds();
        $this->assertTrue($this->mesa()->llegar($partida, 0));

        // Al invitado no se le regala tiempo: su turno vence a la misma hora que antes.
        $this->assertSame($vence, $partida->fresh()->plazo_vence_en->getTimestamp());

        // El trabajo que estaba en la cola llevaba el evento anterior y ya no resuelve nada; queda otro, a la misma hora.
        Queue::assertPushed(ResolverPlazo::class, fn (ResolverPlazo $trabajo) => $trabajo->partidaId === $partida->id
            && $trabajo->evento === 2
            && $trabajo->delay->getTimestamp() === $vence);

        $this->travelTo($partida->fresh()->plazo_vence_en);
        $this->assertFalse($this->mesa()->resolverPlazo($partida->id, 1));
        $this->assertTrue($this->mesa()->resolverPlazo($partida->id, 2));
    }

    public function test_la_espera_larga_es_una_sola_y_jugar_tambien_cuenta_como_llegar(): void
    {
        // Nunca avisa que llegó: se le vence la espera larga, y desde ahí su turno es el de siempre.
        [$partida] = $this->partidaDondeEmpieza(0);
        $this->travelTo($partida->fresh()->plazo_vence_en);
        $this->assertTrue($this->mesa()->resolverPlazo($partida->id));
        $this->assertSame(EventoDePartida::VENCIMIENTO, $partida->eventos()->get()->last()->tipo);
        $this->assertFalse($this->mesa()->faltaLlegar($partida, 0));

        $this->esperarSuTurno(0, $partida);
        $this->assertSame(Mesa::SEGUNDOS_DE_TURNO, $this->mesa()->vista($partida->fresh(), 0)['restan']);

        // Tampoco avisa, pero juega: está en la mesa.
        [$otra] = $this->partidaDondeEmpieza(0);
        $carta = collect($this->mesa()->vista($otra, 0)['acciones'])->firstWhere('tipo', 'jugar');
        $this->mesa()->actuar($otra, Accion::desdeArray($carta), 0);
        $this->assertFalse($this->mesa()->faltaLlegar($otra, 0));
        $this->assertFalse($this->mesa()->llegar($otra, 0));

        $this->esperarSuTurno(0, $otra);
        $this->assertSame(Mesa::SEGUNDOS_DE_TURNO, $this->mesa()->vista($otra->fresh(), 0)['restan']);
    }

    public function test_contra_el_bot_nadie_tiene_que_llegar(): void
    {
        $partida = $this->mesa()->abrir(Jugador::factory()->invitado()->create());
        $eventos = $partida->eventos()->count();

        $this->assertFalse($this->mesa()->faltaLlegar($partida, 0));
        $this->assertFalse($this->mesa()->llegar($partida, 0));
        $this->assertSame($eventos, $partida->eventos()->count());
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
        $this->assertArrayNotHasKey('vencio', $this->mesa()->vista($partida));

        $this->travelTo(now()->addHour());
        $this->assertFalse($this->mesa()->resolverPlazo($partida->id));
        Queue::assertNotPushed(ResolverPlazo::class);
    }

    public function test_al_cerrarse_la_partida_no_queda_un_trabajo_nuevo_en_la_cola(): void
    {
        [$partida] = $this->partidaEnCurso();
        $antes = Queue::pushed(ResolverPlazo::class)->count();

        $this->mesa()->abandonar($partida, 1);

        // Los trabajos que había son los del primer turno (al sentarse y al llegar quien abrió la sala): cerrar no suma otro.
        Queue::assertPushed(ResolverPlazo::class, $antes);
        $this->assertNull($partida->fresh()->plazo_vence_en);
    }

    private function mesa(): Mesa
    {
        return $this->app->make(Mesa::class);
    }

    /**
     * Una partida entre dos personas ya en curso, con la primera mano repartida y los dos en la mesa:
     * quien abrió la sala ya avisó que llegó, así que su turno corre con el plazo de siempre.
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
     * La partida en el momento en que alguien se sentó con el link: quien abrió la sala todavía no llegó a la mesa.
     *
     * @return array{0: Partida, 1: Jugador, 2: Jugador}
     */
    private function partidaRecienSentada(): array
    {
        $uno = Jugador::factory()->invitado()->create();
        $dos = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($uno);

        return [$this->mesa()->sentarse($sala->codigo, $dos), $uno, $dos];
    }

    /**
     * Una partida recién sentada en la que el primer turno es de ese asiento. Quién es mano se sortea, así que
     * se abren partidas hasta que salga.
     *
     * @return array{0: Partida, 1: Jugador, 2: Jugador}
     */
    private function partidaDondeEmpieza(int $asiento): array
    {
        for ($intento = 0; $intento < 60; $intento++) {
            $sentada = $this->partidaRecienSentada();

            if ($this->quienTieneElTurno($sentada[0]) === $asiento) {
                return $sentada;
            }
        }

        $this->fail('En 60 partidas el primer turno nunca fue del asiento '.$asiento);
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
