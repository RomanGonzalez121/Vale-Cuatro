<?php

namespace Tests\Feature;

use App\Juego\Nivel;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Resultado;
use App\Motor\Accion;
use App\Motor\Carta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Lo que cada partida le cuenta al ranking sale de sus eventos. Acá se juegan partidas de resultado
 * conocido y se comprueba qué quedó anotado: quién ganó, qué envidos cuentan y qué partidas no entran.
 */
class EstadisticasTest extends TestCase
{
    use JugandoPartidas;
    use RefreshDatabase;

    public function test_entre_personas_anota_al_que_se_va_y_el_envido_que_se_quiso(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        // El asiento 0 es mano y tiene 33 de envido; el 1 tiene 29.
        $partida = $this->partidaArmadaEntrePersonas($uno, $dos, [['7-oro', '6-oro', '1-espada'], ['5-copa', '4-copa', '3-basto']]);
        $mesa = $this->laMesa();

        $mesa->actuar($partida, Accion::desdeArray(['tipo' => 'envido']), 0);
        $mesa->actuar($partida, Accion::desdeArray(['tipo' => 'quiero']), 1);

        // Justo después del envido querido queda un evento que no pasa por el motor (alguien llegó a la mesa).
        // El motor sigue contando los hechos de la jugada anterior: ese envido no puede contarse dos veces.
        $partida->eventos()->create(['numero' => 4, 'tipo' => EventoDePartida::LLEGADA, 'asiento' => 0, 'datos' => [], 'creado_en' => now()]);

        $mesa->actuar($partida, Accion::jugar(Carta::de('6-oro')), 0);

        // Mientras se juega no hay nada anotado.
        $this->assertSame(0, Resultado::count());

        // Quien se va pierde, con lo jugado hasta ahí: un envido jugado, que no ganó.
        $mesa->abandonar($partida->fresh(), 1);

        $this->assertSame(
            ['gano' => false, 'envidos_jugados' => 1, 'envidos_ganados' => 0],
            $this->anotadoA($dos, $partida),
        );

        // Al otro, que iba 2 a 0, no se le anota nada: ganar porque el rival se fue cuenta desde las buenas.
        $this->assertSame(0, $partida->fresh()->ganador);
        $this->assertSame(1, Resultado::count());
    }

    public function test_un_envido_que_no_se_quiso_no_cuenta_como_jugado(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        $partida = $this->partidaArmadaEntrePersonas($uno, $dos, [['7-oro', '6-oro', '1-espada'], ['5-copa', '4-copa', '3-basto']]);
        $mesa = $this->laMesa();

        $mesa->actuar($partida, Accion::desdeArray(['tipo' => 'envido']), 0);
        $mesa->actuar($partida, Accion::desdeArray(['tipo' => 'no_quiero']), 1);
        $mesa->abandonar($partida->fresh(), 0);

        // Esta vez se fue el asiento 0. Nadie jugó un envido: el que se cantó no se quiso.
        $this->assertSame(['gano' => false, 'envidos_jugados' => 0, 'envidos_ganados' => 0], $this->anotadoA($uno, $partida));
        $this->assertSame(1, Resultado::count());
    }

    public function test_contra_el_bot_cuenta_desde_intermedio_y_abandonar_es_perder(): void
    {
        $jugador = Jugador::factory()->create();
        $mesa = $this->laMesa();

        $contraElFacil = $mesa->abrir($jugador, Nivel::Facil);
        $mesa->abandonar($contraElFacil);

        $this->assertSame(0, Resultado::count(), 'Contra el Fácil no se anota nada, ni ganando ni perdiendo.');

        foreach ([Nivel::Intermedio, Nivel::Dificil, Nivel::UltraDificil] as $nivel) {
            $partida = $mesa->abrir($jugador, $nivel);
            $mesa->abandonar($partida);

            // Un solo renglón: el del jugador. El asiento del bot no es de nadie.
            $this->assertSame(1, Resultado::query()->where('partida_id', $partida->id)->count(), $nivel->nombre());
            $this->assertFalse($this->anotadoA($jugador, $partida)['gano'], $nivel->nombre());
        }

        $this->assertSame(3, Resultado::count());
    }

    public function test_una_partida_entera_contra_el_bot_anota_al_que_gano(): void
    {
        $jugador = Jugador::factory()->create();
        [$partida] = $this->partidaContraElBot($jugador, Nivel::Intermedio);

        $anotado = $this->anotadoA($jugador, $partida);

        $this->assertSame($partida->ganador === 0, $anotado['gano']);
        $this->assertLessThanOrEqual($anotado['envidos_jugados'], $anotado['envidos_ganados']);
        $this->assertTrue(Resultado::query()->where('partida_id', $partida->id)->sole()->terminada_en->equalTo($partida->terminada_en));
    }

    public function test_una_partida_entera_entre_personas_reparte_los_envidos_entre_los_dos(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->invitado()->create()];
        $partida = $this->partidaEntrePersonas($uno, $dos);

        [$deUno, $deDos] = [$this->anotadoA($uno, $partida), $this->anotadoA($dos, $partida)];

        // Uno gana y el otro pierde; cada envido jugado lo ganó uno de los dos. Quien juega sin cuenta también queda anotado.
        $this->assertNotSame($deUno['gano'], $deDos['gano']);
        $this->assertSame($partida->ganador === 0, $deUno['gano']);
        $this->assertSame($deUno['envidos_jugados'], $deDos['envidos_jugados']);
        $this->assertSame($deUno['envidos_jugados'], $deUno['envidos_ganados'] + $deDos['envidos_ganados']);
    }

    public function test_si_el_rival_se_va_cuando_ya_ibas_en_las_buenas_la_partida_ganada_cuenta(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        [$partida, $puntero] = $this->partidaEntrePersonasHasta($uno, $dos, 15);
        $jugadores = [$uno, $dos];

        // Se va el que va perdiendo.
        $this->laMesa()->abandonar($partida, 1 - $puntero);

        $this->assertTrue($this->anotadoA($jugadores[$puntero], $partida)['gano']);
        $this->assertFalse($this->anotadoA($jugadores[1 - $puntero], $partida)['gano']);
        $this->assertSame(2, Resultado::query()->where('partida_id', $partida->id)->count());
    }

    public function test_si_se_va_el_que_iba_ganando_el_otro_no_suma_una_victoria_que_no_jugo(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        [$partida, $puntero] = $this->partidaEntrePersonasHasta($uno, $dos, 15);
        $jugadores = [$uno, $dos];
        $delOtro = $this->laMesa()->reconstruir($partida)->tanteo()[1 - $puntero];

        // Se va el puntero. Pierde igual; el otro la gana solo si también había llegado a las buenas.
        $this->laMesa()->abandonar($partida, $puntero);

        $this->assertFalse($this->anotadoA($jugadores[$puntero], $partida)['gano']);
        $this->assertSame($delOtro >= 15 ? 1 : 0, Resultado::query()->where('partida_id', $partida->id)->where('gano', true)->count());
    }

    public function test_una_sala_que_se_cancela_no_anota_nada(): void
    {
        $mesa = $this->laMesa();
        $sala = $mesa->crearSala(Jugador::factory()->create());

        $this->assertTrue($mesa->cancelarSala($sala));
        $this->assertSame(0, Resultado::count());
    }

    public function test_recalcular_vuelve_a_escribir_lo_mismo_leyendo_solo_los_eventos(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        $mesa = $this->laMesa();

        $this->partidaEntrePersonas($uno, $dos);
        $this->partidaContraElBot($uno, Nivel::Intermedio);
        $this->partidaContraElBot($dos, Nivel::Facil);
        $mesa->abandonar($mesa->abrir($uno, Nivel::Dificil));

        $anotado = $this->todoLoAnotado();
        $this->assertCount(4, $anotado, 'Dos de la partida entre personas y una por cada partida contra un bot que cuenta.');

        // Se rompe a propósito lo guardado: se borra un renglón y se da vuelta otro.
        Resultado::query()->orderBy('id')->first()->delete();
        Resultado::query()->orderByDesc('id')->first()->update(['gano' => ! $anotado[3]['gano'], 'envidos_jugados' => 99]);

        $this->artisan('ranking:recalcular')->expectsOutputToContain('3')->assertSuccessful();

        $this->assertSame($anotado, $this->todoLoAnotado());
    }

    public function test_al_borrarse_un_jugador_se_van_sus_renglones_y_no_los_del_rival(): void
    {
        [$cuenta, $invitado] = [Jugador::factory()->create(), Jugador::factory()->invitado()->create()];
        $partida = $this->partidaEntrePersonas($cuenta, $invitado);

        $invitado->delete();

        $this->assertSame([$cuenta->id], Resultado::query()->where('partida_id', $partida->id)->pluck('jugador_id')->all());
    }

    public function test_la_limpieza_no_se_lleva_al_invitado_que_abrio_una_partida_que_jugo_otra_persona(): void
    {
        // Dos invitados que no volvieron. Uno abrió una sala donde se sentó alguien con cuenta; el otro solo jugó contra el bot.
        [$abrioLaSala, $soloContraElBot] = [Jugador::factory()->invitado()->create(), Jugador::factory()->invitado()->create()];
        $cuenta = Jugador::factory()->create();

        $compartida = $this->partidaEntrePersonas($abrioLaSala, $cuenta);
        [$contraElBot] = $this->partidaContraElBot($soloContraElBot, Nivel::Intermedio);

        $this->travel(Jugador::DIAS_DE_INVITADO + 5)->days();
        $this->artisan('model:prune', ['--model' => [Jugador::class]]);

        // La partida compartida sigue entera, con el renglón de la cuenta: borrar al invitado se la habría llevado.
        $this->assertNotNull($abrioLaSala->fresh());
        $this->assertNotNull($compartida->fresh());
        $this->assertSame(1, Resultado::query()->where('partida_id', $compartida->id)->where('jugador_id', $cuenta->id)->count());

        // El que jugó solo se va, con lo suyo.
        $this->assertNull($soloContraElBot->fresh());
        $this->assertNull($contraElBot->fresh());
        $this->assertSame(0, Resultado::query()->where('jugador_id', $soloContraElBot->id)->count());
    }

    public function test_si_anotar_fallara_la_partida_se_cierra_igual_y_el_ranking_se_rehace_despues(): void
    {
        $jugador = Jugador::factory()->create();
        $mesa = $this->laMesa();
        $partida = $mesa->abrir($jugador, Nivel::Intermedio);

        // La tabla del ranking desaparece: anotar va a fallar.
        Schema::rename('resultados', 'resultados_rota');

        $mesa->abandonar($partida);

        $this->assertSame(Partida::ABANDONADA, $partida->fresh()->estado);

        // Con la tabla de vuelta, recalcular anota lo que faltó.
        Schema::rename('resultados_rota', 'resultados');
        $this->artisan('ranking:recalcular')->assertSuccessful();

        $this->assertFalse($this->anotadoA($jugador, $partida)['gano']);
    }

    /**
     * Lo que quedó anotado de un jugador en una partida.
     *
     * @return array{gano: bool, envidos_jugados: int, envidos_ganados: int}
     */
    private function anotadoA(Jugador $jugador, Partida $partida): array
    {
        return Resultado::query()
            ->where('partida_id', $partida->id)
            ->where('jugador_id', $jugador->id)
            ->sole()
            ->only(['gano', 'envidos_jugados', 'envidos_ganados']);
    }

    /**
     * Todos los renglones, sin su número: ordenados por partida y jugador para poder compararlos.
     *
     * @return list<array<string, mixed>>
     */
    private function todoLoAnotado(): array
    {
        return Resultado::query()
            ->orderBy('partida_id')
            ->orderBy('jugador_id')
            ->get()
            ->map(fn (Resultado $resultado) => [
                ...$resultado->only(['partida_id', 'jugador_id', 'gano', 'envidos_jugados', 'envidos_ganados']),
                'terminada_en' => $resultado->terminada_en->toDateTimeString(),
            ])
            ->all();
    }
}
