<?php

namespace Tests\Feature;

use App\Juego\Historial;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Juego\Repeticion;
use App\Juego\Senas;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Motor\Accion;
use App\Motor\Azar;
use App\Motor\Carta;
use App\Motor\Fase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Una partida de a cuatro en el historial: se cuenta como lo que fue (dos parejas, tres bots) y su
 * repetición muestra la mesa en cruz, con el mismo tanteo que tuvo la partida.
 */
class HistorialDeACuatroTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_repeticion_muestra_en_cada_mano_el_tanteo_que_tuvo_la_partida(): void
    {
        [$partida, $alRepartir] = $this->partidaJugada(Jugador::factory()->invitado()->create(), puntos: 12, semilla: 7);

        $final = $this->mesa()->reconstruir($partida)->tanteo();

        ['cuadros' => $cuadros, 'manos' => $manos] = $this->app->make(Repeticion::class)->de($partida, 0);

        // Una entrada por mano jugada, y cada una apunta al cuadro donde se reparte esa mano.
        $this->assertSame(array_keys($alRepartir), array_map(fn (int $cuadro) => $cuadros[$cuadro]['mano'], $manos));
        $this->assertGreaterThan(1, count($manos));

        // El tanteo es el de las parejas: "vos" es la de la persona.
        foreach (array_values($alRepartir) as $numero => $tanteo) {
            $this->assertSame(['vos' => $tanteo[0], 'rival' => $tanteo[1]], $cuadros[$manos[$numero]]['tanteo'], 'Mano '.($numero + 1));
        }

        // Al repartir hay doce cartas distintas a la vista: los otros tres son bots y se ven las de todos.
        foreach ($manos as $cuadro) {
            $repartidas = [...$cuadros[$cuadro]['propias'], ...array_merge(...array_values($cuadros[$cuadro]['otros']))];

            $this->assertCount(12, array_unique(array_filter($repartidas)));
            $this->assertSame(['vos' => null, 'derecha' => null, 'companero' => null, 'izquierda' => null, 'ganador' => null], $cuadros[$cuadro]['cruz']);
        }

        foreach ($cuadros as $cuadro) {
            // Una carta está en una mano o en la cruz, nunca en los dos lados.
            $enManos = array_filter([...$cuadro['propias'], ...array_merge(...array_values($cuadro['otros']))]);
            $enLaCruz = array_filter(array_diff_key($cuadro['cruz'], ['ganador' => null]));

            $this->assertSame([], array_values(array_intersect($enManos, $enLaCruz)));
            // Quien ganó la baza que está en la cruz tiene una carta en ella.
            if (! in_array($cuadro['cruz']['ganador'], [null, 'parda'], true)) {
                $this->assertNotNull($cuadro['cruz'][$cuadro['cruz']['ganador']]);
            }
        }

        $ultimo = $cuadros[count($cuadros) - 1];

        $this->assertSame(['vos' => $final[0], 'rival' => $final[1]], $ultimo['tanteo']);
        $this->assertSame($partida->ganador === 0 ? 'vos' : 'rival', $ultimo['fin']);
        $this->assertStringContainsString("la partida, {$final[0]} a {$final[1]}.", $ultimo['texto']);
        $this->assertNull($cuadros[count($cuadros) - 2]['fin']);
    }

    public function test_cada_carta_cae_en_la_cruz_delante_de_quien_la_tiro_y_una_sena_se_cuenta_sin_mover_nada(): void
    {
        Queue::fake();

        $jugador = Jugador::factory()->invitado()->create();
        $partida = Partida::create(['jugador_id' => $jugador->id, 'primer_mano' => Mesa::JUGADOR, 'puntos' => 30, 'asientos' => 4, 'nivel_bot' => Nivel::Facil]);
        $partida->eventos()->create(['numero' => 1, 'tipo' => EventoDePartida::REPARTO, 'creado_en' => now(), 'datos' => ['manos' => [
            ['1-espada', '3-oro', '4-copa'],
            ['2-oro', '6-basto', '5-basto'],
            ['7-oro', '6-oro', '4-basto'],
            ['2-basto', '6-copa', '10-oro'],
        ]]]);

        $mesa = $this->mesa();
        $mesa->sena($partida, Senas::ANCHO_DE_ESPADA);
        $mesa->actuar($partida, Accion::jugar(Carta::de('4-copa')));

        // Los bots no juegan solos (la cola está de mentira): se mueve a cada uno a mano, hasta cerrar la baza.
        foreach (['2-oro' => 1, '4-basto' => 2, '10-oro' => 3] as $carta => $asiento) {
            $partida->eventos()->create(['numero' => $partida->eventos()->max('numero') + 1, 'tipo' => EventoDePartida::ACCION, 'asiento' => $asiento, 'datos' => Accion::jugar(Carta::de($carta))->aArray(), 'creado_en' => now()]);
        }

        ['cuadros' => $cuadros] = $this->app->make(Repeticion::class)->de($partida->fresh(), 0);
        $textos = array_column($cuadros, 'texto');

        $this->assertSame([
            'Mano 1. Sos mano.',
            'Le hiciste una seña a tu compañero: tengo el ancho de espada.',
            'Jugaste el 4 de copa.',
            'El de la derecha jugó el 2 de oro.',
            'Tu compañero jugó el 4 de basto.',
            'El de la izquierda jugó el 10 de oro.',
            'El de la derecha ganó la baza.',
        ], $textos);

        // La seña no cambió nada de la mesa.
        $this->assertSame([...$cuadros[0], 'texto' => ''], [...$cuadros[1], 'texto' => '']);

        $ultimo = $cuadros[count($cuadros) - 1];

        $this->assertSame(['vos' => '4-copa', 'derecha' => '2-oro', 'companero' => '4-basto', 'izquierda' => '10-oro', 'ganador' => 'derecha'], $ultimo['cruz']);
        $this->assertSame(['rival', null, null], $ultimo['bazas']);
        // Cada uno se quedó sin la carta que tiró, y su lugar quedó vacío.
        $this->assertSame(['1-espada', '3-oro', null], $ultimo['propias']);
        $this->assertSame(['derecha' => [null, '6-basto', '5-basto'], 'companero' => ['7-oro', '6-oro', null], 'izquierda' => ['2-basto', '6-copa', null]], $ultimo['otros']);
    }

    public function test_la_lista_la_cuenta_como_una_partida_de_a_cuatro_y_abre_su_repeticion(): void
    {
        // Con cuenta: quien juega sin cuenta ve solo lo que cerró desde que empezó su sesión.
        $jugador = Jugador::factory()->create();
        [$partida] = $this->partidaJugada($jugador, puntos: 3, semilla: 3);

        $resumen = $this->app->make(Historial::class)->resumen($partida, 0);
        $final = $this->mesa()->reconstruir($partida)->tanteo();

        $this->assertSame(4, $resumen['asientos']);
        $this->assertSame('dos bots de nivel intermedio', $resumen['rival']);
        $this->assertSame([$partida->ganador === 0, $final[0], $final[1]], [$resumen['gano'], $resumen['vos'], $resumen['ellos']]);

        $this->actingAs($jugador)->get('/historial')->assertOk()
            ->assertSee($partida->ganador === 0 ? 'Ganaron' : 'Perdieron')
            ->assertSee('Contra dos bots de nivel intermedio')
            ->assertSee('De a cuatro, con un bot de compañero')
            ->assertSee('Nosotros')
            ->assertSee(route('historial.cuadros', $partida), false);

        $datos = $this->actingAs($jugador)->getJson(route('historial.cuadros', $partida))->assertOk()->json();

        $this->assertSame(4, $datos['resumen']['asientos']);
        $this->assertArrayHasKey('cruz', $datos['cuadros'][0]);
        $this->actingAs($jugador)->get(route('historial.ver', $partida))->assertOk()->assertSee('deACuatro', false);
    }

    private function mesa(): Mesa
    {
        return $this->app->make(Mesa::class);
    }

    /**
     * Una partida de a cuatro jugada hasta el final por la mesa de verdad: la persona toca cualquier botón
     * válido y los bots juegan apenas les toca (en los tests la cola corre en el momento). Devuelve la
     * partida y el tanteo que había al repartir cada mano.
     *
     * @return array{0: Partida, 1: array<int, array{0: int, 1: int}>}
     */
    private function partidaJugada(Jugador $jugador, int $puntos, int $semilla): array
    {
        $mesa = $this->mesa();
        $azar = Azar::deSemilla($semilla);
        $partida = Partida::create(['jugador_id' => $jugador->id, 'primer_mano' => Mesa::JUGADOR, 'puntos' => $puntos, 'asientos' => 4, 'nivel_bot' => Nivel::Intermedio]);

        $mesa->repartir($partida);

        for ($pedidos = 0; $partida->fresh()->enCurso(); $pedidos++) {
            $this->assertLessThan(2000, $pedidos, 'La partida no termina.');

            $vista = $mesa->vista($partida->fresh());

            if ($vista['fase'] === Fase::PorRepartir->value) {
                $mesa->repartir($partida);
            } elseif ($vista['acciones'] !== []) {
                $mesa->actuar($partida, Accion::desdeArray($vista['acciones'][$azar->entero(0, count($vista['acciones']) - 1)]));
            }
        }

        // El tanteo al repartir cada mano sale de volver a pasar los eventos: es lo que se vio jugando.
        $alRepartir = [];

        foreach ($mesa->pasoAPaso($partida->fresh()) as [$evento, $motor]) {
            if ($evento->tipo === EventoDePartida::REPARTO) {
                $alRepartir[$motor->vistaPara(0)['numeroDeMano']] = $motor->tanteo();
            }
        }

        return [$partida->fresh(), $alRepartir];
    }
}
