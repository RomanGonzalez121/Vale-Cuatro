<?php

namespace Tests\Feature;

use App\Juego\Desafios;
use App\Juego\Escalera;
use App\Juego\Mesa;
use App\Juego\Premios;
use App\Juego\Progresos;
use App\Juego\Titulos;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Progreso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los títulos de la tanda 4: cada uno sale de una mano armada con cartas elegidas, jugada de punta a punta.
 * Y los premios nuevos (dorsos, porotos, distintivos) con su candado y la administración.
 */
class TitulosDeLaTandaCuatroTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_truco_que_no_se_quiere_sin_cartas_bravas_es_un_chamuyo(): void
    {
        $floja = $this->cuentas(['4-copa', '5-oro', '6-basto'], ['1-espada', '3-oro', '7-copa'], [[0, 'truco'], [1, 'no_quiero']]);
        $this->assertSame(1, $floja['chamuyos']);
        // No quererlo no es un truco querido ganado.
        $this->assertSame(0, $floja['trucos_ganados']);

        // Con un tres en la mano ya no es chamuyo: tenía con qué.
        $brava = $this->cuentas(['3-copa', '5-oro', '6-basto'], ['1-espada', '4-oro', '7-copa'], [[0, 'truco'], [1, 'no_quiero']]);
        $this->assertSame(0, $brava['chamuyos']);
    }

    public function test_un_vale_cuatro_querido_ganado_cuenta_como_truco_retruco_y_vale_cuatro(): void
    {
        $cuentas = $this->cuentas(['1-espada', '1-basto', '7-espada'], ['4-copa', '4-oro', '5-copa'], [
            [0, 'truco'], [1, 'retruco'], [0, 'vale_cuatro'], [1, 'quiero'],
            [0, 'jugar', '1-espada'], [1, 'jugar', '4-copa'],
            [0, 'jugar', '1-basto'], [1, 'jugar', '4-oro'],
        ]);

        $this->assertSame([1, 1, 1], [$cuentas['trucos_ganados'], $cuentas['retrucos_ganados'], $cuentas['vale_cuatros_ganados']]);
    }

    public function test_un_falta_envido_querido_ganado_cuenta_y_uno_no_querido_no(): void
    {
        $querido = $this->cuentas(['7-oro', '6-oro', '4-copa'], ['4-espada', '5-basto', '10-copa'], [[0, 'falta_envido'], [1, 'quiero']]);
        $this->assertSame([1, 1], [$querido['envidos_ganados'], $querido['faltas_ganadas']]);

        $noQuerido = $this->cuentas(['7-oro', '6-oro', '4-copa'], ['4-espada', '5-basto', '10-copa'], [[0, 'falta_envido'], [1, 'no_quiero'], [0, 'mazo']]);
        $this->assertSame(0, $noQuerido['faltas_ganadas']);
    }

    public function test_ganar_la_mano_despues_de_una_primera_parda_cuenta_para_pardero(): void
    {
        $cuentas = $this->cuentas(['3-oro', '1-espada', '5-basto'], ['3-copa', '4-copa', '6-espada'], [
            [0, 'jugar', '3-oro'], [1, 'jugar', '3-copa'],
            [0, 'jugar', '1-espada'], [1, 'jugar', '4-copa'],
        ]);

        $this->assertSame(1, $cuentas['manos_con_parda']);
    }

    public function test_la_escalera_entera_y_los_ocho_desafios_dan_sus_titulos(): void
    {
        $jugador = Jugador::factory()->create();
        $titulos = new Titulos;

        $this->assertSame(Escalera::cuantos(), Titulos::TODOS['del-club']['pide']);
        $this->assertSame(count(Desafios::sueltos()), Titulos::TODOS['desafiante']['pide']);

        foreach (Escalera::escalones() as $escalon) {
            Progreso::query()->forceCreate(['jugador_id' => $jugador->id, 'clave' => Progresos::claveDeEscalon($escalon['numero']), 'intentos' => 1, 'superado_en' => now()]);
        }

        foreach (array_slice(Desafios::sueltos(), 0, -1) as $ficha) {
            Progreso::query()->forceCreate(['jugador_id' => $jugador->id, 'clave' => Progresos::claveDeDesafio($ficha['clave']), 'intentos' => 1, 'superado_en' => now()]);
        }

        $titulos->ponerAlDia($jugador);
        $jugador->refresh();

        $this->assertTrue(Titulos::gano($jugador, 'del-club'));
        // Le falta un desafío.
        $this->assertFalse(Titulos::gano($jugador, 'desafiante'));

        // Recalcular llega a lo mismo.
        $titulos->recalcular($this->app->make(Mesa::class));
        $this->assertSame(Escalera::cuantos(), $jugador->fresh()->escalones_pasados);
    }

    public function test_los_premios_nuevos_tienen_candado_y_la_administracion_los_tiene_todos(): void
    {
        $jugador = Jugador::factory()->create(['fosforos' => 260]);   // Nivel 27.

        // Porotos y el dorso Tanteador son del 27; el dorso Estrella, del 30; los cuatro palos, del 38.
        $this->actingAs($jugador)->putJson('/perfil/anotar', ['anotar' => 'porotos'])->assertOk();
        $this->actingAs($jugador)->putJson('/perfil/dorso', ['dorso' => 'tanteador'])->assertOk();
        $this->actingAs($jugador)->putJson('/perfil/dorso', ['dorso' => 'estrella'])->assertUnprocessable();
        $this->actingAs($jugador)->putJson('/perfil/distintivo', ['distintivo' => 'palo-copa'])->assertUnprocessable();
        $this->actingAs($jugador)->putJson('/perfil/titulo', ['titulo' => 'chamuyero', 'femenino' => true])->assertUnprocessable();

        $administracion = Jugador::factory()->administrador()->create();
        $this->actingAs($administracion)->putJson('/perfil/dorso', ['dorso' => 'cuadros'])->assertOk();
        $this->actingAs($administracion)->putJson('/perfil/distintivo', ['distintivo' => 'palo-basto'])->assertOk();
        $this->actingAs($administracion)->putJson('/perfil/titulo', ['titulo' => 'chamuyero', 'femenino' => true])->assertOk();

        $this->assertSame('Chamuyera', (new Premios)->tituloDe($administracion->fresh()));
    }

    public function test_cada_pagina_trae_solo_los_dorsos_que_usa(): void
    {
        // La portada dibuja cartas boca abajo: solo con el dorso de quien mira, no los veinte.
        $portada = $this->get('/')->assertOk()->getContent();
        $this->assertSame(1, substr_count($portada, '<symbol id="dorso'));

        // Con otro dorso elegido, la página trae ese y no el de fábrica.
        $jugador = Jugador::factory()->create(['fosforos' => 400, 'dorso' => 'grabado']);
        $suya = $this->actingAs($jugador)->get('/ranking')->assertOk()->getContent();
        $this->assertStringContainsString('<symbol id="dorso-grabado"', $suya);
        $this->assertStringNotContainsString('<symbol id="dorso"', $suya);

        // La Colección del perfil sí los trae todos: se eligen ahí.
        $perfil = $this->actingAs($jugador)->get('/perfil')->assertOk()->getContent();
        $this->assertSame(count(Premios::DORSOS), substr_count($perfil, '<symbol id="dorso'));
    }

    /**
     * Juega una mano armada (el asiento 0 es mano) y devuelve lo que cuenta para los títulos del asiento 0.
     *
     * @param  list<string>  $mias
     * @param  list<string>  $suyas
     * @param  list<array{0: int, 1: string, 2?: string}>  $jugadas
     * @return array<string, int>
     */
    private function cuentas(array $mias, array $suyas, array $jugadas): array
    {
        $jugador = Jugador::factory()->create();
        $partida = Partida::create(['jugador_id' => $jugador->id, 'primer_mano' => Mesa::JUGADOR, 'puntos' => 30]);
        $partida->eventos()->create(['numero' => 1, 'tipo' => EventoDePartida::REPARTO, 'datos' => ['manos' => [$mias, $suyas]], 'creado_en' => now()]);

        foreach ($jugadas as $i => $jugada) {
            $partida->eventos()->create([
                'numero' => $i + 2,
                'tipo' => EventoDePartida::ACCION,
                'asiento' => $jugada[0],
                'datos' => isset($jugada[2]) ? ['tipo' => $jugada[1], 'carta' => $jugada[2]] : ['tipo' => $jugada[1]],
                'creado_en' => now(),
            ]);
        }

        $partida->forceFill(['estado' => Partida::ABANDONADA])->save();

        return (new Titulos)->en($partida, $this->app->make(Mesa::class))[0];
    }
}
