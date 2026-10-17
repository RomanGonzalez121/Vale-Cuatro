<?php

namespace Tests\Feature;

use App\Juego\Mesa;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Progreso;
use App\Models\Torneo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * La gira de los clubes por las rutas del sitio: el plano de cada barrio, el candado (tampoco se salta
 * escribiendo la dirección a mano) y la mesa de una parada, que sabe adónde volver.
 */
class GiraPorHttpTest extends TestCase
{
    use RefreshDatabase;

    private function pasar(Jugador $jugador, string $clave, int $fosforos = 1): void
    {
        Progreso::query()->create(['jugador_id' => $jugador->id, 'clave' => $clave])->forceFill(['superado_en' => now(), 'fosforos' => $fosforos])->save();
    }

    public function test_la_gira_lleva_al_barrio_donde_vas_y_muestra_su_plano(): void
    {
        $this->get('/gira')->assertRedirect(route('gira.barrio', 'boedo'));

        $this->get('/gira/boedo')->assertOk()
            ->assertSee('La gira de los clubes')
            ->assertSee('Llevás 0 fósforos de 114.')
            ->assertSee('Pichón Ibarra')
            ->assertSee('Club Social Boedo')
            ->assertSee('Parada 1 de 8');

        $this->get('/gira/palermo')->assertNotFound();

        // Con la escalera pasada, la gira lleva a San Telmo.
        $jugador = Jugador::factory()->create();
        $this->pasar($jugador, 'escalon:8', 2);
        $this->actingAs($jugador)->get('/gira')->assertRedirect(route('gira.barrio', 'san-telmo'));
        $this->get('/gira/san-telmo')->assertOk()->assertSee('Don Ceferino')->assertSee('Bar El Gramófono');
    }

    public function test_una_parada_cerrada_no_se_abre_ni_escribiendo_la_direccion(): void
    {
        Queue::fake();
        $jugador = Jugador::factory()->create();

        $this->actingAs($jugador)->post('/gira/san-telmo/1')
            ->assertRedirect(route('gira.barrio', 'san-telmo'))
            ->assertSessionHas('aviso', 'Esa parada todavía está cerrada: primero pasá la anterior.');
        $this->post('/gira/boedo/2')->assertRedirect(route('gira.barrio', 'boedo'));
        $this->assertSame(0, Partida::query()->count());

        // Con el jefe de Boedo vencido, la primera de San Telmo se juega.
        $this->pasar($jugador, 'escalon:8');
        $this->post('/gira/san-telmo/1')->assertRedirect(route('mesa'));
        $this->assertSame('san-telmo:1', Partida::query()->sole()->gira);
    }

    public function test_los_niveles_especiales_se_juegan_cada_uno_en_su_mesa(): void
    {
        Queue::fake();
        $jugador = Jugador::factory()->create();
        $this->pasar($jugador, 'escalon:8');
        $this->pasar($jugador, 'gira:san-telmo:1');
        $this->pasar($jugador, 'gira:san-telmo:2');

        // De a cuatro: la mesa en cruz, que dice qué parada es y adónde se vuelve.
        $this->actingAs($jugador)->post('/gira/san-telmo/3')->assertRedirect(route('mesa'));
        $this->assertSame(4, Partida::query()->sole()->asientos);
        $this->get('/mesa')->assertOk()->assertSee('Parada 3')->assertSee('Abandonar la parada')->assertDontSee('Jugar la revancha');
        $this->get('/gira/san-telmo')->assertOk()->assertDontSee('Muy pronto');
    }

    public function test_la_copa_del_barrio_arma_un_torneo_que_vuelve_al_plano(): void
    {
        Queue::fake();
        $jugador = Jugador::factory()->create();
        $this->pasar($jugador, 'escalon:8');

        foreach (['san-telmo', 'la-boca'] as $barrio) {
            for ($n = 1; $n <= ($barrio === 'san-telmo' ? 6 : 4); $n++) {
                $this->pasar($jugador, "gira:{$barrio}:{$n}");
            }
        }

        $this->actingAs($jugador)->post('/gira/la-boca/5');
        $torneo = Torneo::query()->sole();
        $this->assertSame('la-boca:5', $torneo->gira);

        $this->get(route('torneo', $torneo))->assertOk()
            ->assertSee('Copa de La Boca, parada 5 de la gira')
            ->assertDontSee('Armar otro torneo');

        // Dejarla: se vuelve a jugar desde la gira, o se vuelve al plano.
        $this->post(route('torneo.abandonar', $torneo));
        $this->get(route('torneo', $torneo))
            ->assertSee('Jugar la copa de nuevo')
            ->assertSee(route('gira.barrio', 'la-boca'));
        $this->assertSame(1, Progreso::query()->where('clave', 'gira:la-boca:5')->sole()->intentos);
    }

    public function test_la_mesa_de_una_parada_dice_cual_es_y_adonde_se_vuelve(): void
    {
        Queue::fake();
        $jugador = Jugador::factory()->create();
        $this->pasar($jugador, 'escalon:8');

        $this->actingAs($jugador)->post('/gira/san-telmo/1');

        $this->get('/mesa')->assertOk()
            ->assertSee('Parada 1')
            ->assertSee('Tito Pereyra')
            ->assertSee(route('gira.barrio', 'san-telmo'))
            ->assertSee('Abandonar la parada');

        // Abandonada, la mesa vuelve al plano del barrio y dice cómo quedó.
        $partida = Partida::query()->sole();
        $this->post('/mesa/abandonar', ['partida' => $partida->id]);
        $this->assertSame(Partida::ABANDONADA, $partida->fresh()->estado);
        $this->get('/mesa')->assertRedirect(route('gira.barrio', 'san-telmo'));
        $this->assertNotSame(Mesa::JUGADOR, $partida->fresh()->ganador);
    }
}
