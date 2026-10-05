<?php

namespace Tests\Feature;

use App\Juego\Mesa;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lo que el navegador puede pedirle a la mesa, y lo que no.
 */
class MesaPorHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_jugar_abre_una_partida_ya_repartida(): void
    {
        $this->post('/jugar')->assertRedirect('/mesa');

        $partida = Partida::sole();

        $this->assertTrue($partida->enCurso());
        $this->assertSame(Jugador::sole()->id, $partida->jugador_id);
        $this->assertSame(EventoDePartida::REPARTO, $partida->eventos()->first()->tipo);
    }

    public function test_jugar_de_nuevo_retoma_la_partida_sin_terminar(): void
    {
        $this->post('/jugar');
        $this->post('/jugar')->assertRedirect('/mesa');

        $this->assertSame(1, Partida::count());
    }

    public function test_la_mesa_muestra_la_partida_en_curso_con_las_plantillas_de_las_cartas(): void
    {
        $jugador = $this->sentado();

        $html = $this->actingAs($jugador)->get('/mesa')->assertOk()->assertSee('Vale Cuatro')->getContent();

        // Las 40 cartas y el dorso, para dibujar cualquier jugada sin volver al servidor.
        $this->assertSame(41, substr_count($html, '<template data-plantilla='));
    }

    public function test_recargar_la_mesa_vuelve_a_la_misma_partida_en_el_mismo_punto(): void
    {
        $jugador = $this->sentado();
        $partida = Partida::sole();

        $this->actingAs($jugador)->postJson('/mesa/accion', $this->unaAccionValida($partida))->assertOk();

        $vista = $this->app->make(Mesa::class)->vista($partida);
        $primera = $this->actingAs($jugador)->get('/mesa')->assertOk()->viewData('vista');
        $segunda = $this->actingAs($jugador)->get('/mesa')->assertOk()->viewData('vista');

        $this->assertSame($vista, $primera);
        $this->assertSame($primera, $segunda);
        $this->assertSame(1, Partida::count());
    }

    public function test_la_pagina_de_la_mesa_no_trae_las_cartas_del_bot(): void
    {
        $jugador = Jugador::factory()->invitado()->create();
        $this->partidaArmada($jugador, [['4-copa', '5-copa', '6-basto'], ['1-espada', '3-oro', '10-basto']]);

        $html = $this->actingAs($jugador)->get('/mesa')->assertOk()->getContent();

        // Lo que la página le pasa a la mesa es la vista del jugador: está en el atributo x-data.
        preg_match('/x-data="mesa\((.*?)\)"/s', $html, $datos);
        $datos = html_entity_decode($datos[1]);

        $this->assertStringContainsString('4-copa', $datos);

        foreach (['1-espada', '3-oro', '10-basto'] as $delBot) {
            $this->assertStringNotContainsString($delBot, $datos, "La página trae el {$delBot} del bot.");
        }
    }

    public function test_sin_partida_en_curso_la_mesa_manda_a_elegir_el_modo(): void
    {
        $this->actingAs(Jugador::factory()->invitado()->create())->get('/mesa')->assertRedirect('/modos');
    }

    public function test_una_accion_valida_devuelve_los_pasos_y_queda_guardada(): void
    {
        $jugador = $this->sentado();
        $partida = Partida::sole();
        $antes = $partida->eventos()->count();

        $pasos = $this->actingAs($jugador)->postJson('/mesa/accion', $this->unaAccionValida($partida))
            ->assertOk()
            ->assertJsonStructure(['pasos' => [['fase', 'tanteo', 'misCartas', 'bazas', 'acciones', 'hechos']]])
            ->json('pasos');

        // Un paso por la jugada propia y uno por cada jugada del bot: tantos como eventos nuevos.
        $this->assertCount($partida->eventos()->count() - $antes, $pasos);
        $this->assertSame(Mesa::JUGADOR, $pasos[0]['asiento']);
    }

    public function test_una_accion_invalida_mandada_a_mano_se_rechaza_con_el_motivo_y_no_genera_evento(): void
    {
        // El jugador es mano y tiene estas tres cartas: así el motivo del rechazo no depende del azar del reparto.
        $jugador = Jugador::factory()->invitado()->create();
        $partida = $this->partidaArmada($jugador, [['4-copa', '5-copa', '6-basto'], ['1-espada', '3-oro', '10-basto']]);

        // Un retruco sin truco: ningún botón lo ofrece, pero se puede mandar igual.
        $this->actingAs($jugador)->postJson('/mesa/accion', ['tipo' => 'retruco'])
            ->assertStatus(422)
            ->assertJsonPath('motivo', 'El truco sube de a un paso: truco, retruco y vale cuatro.');

        // Una carta que no tiene: justo una del bot.
        $this->actingAs($jugador)->postJson('/mesa/accion', ['tipo' => 'jugar', 'carta' => '1-espada'])
            ->assertStatus(422)
            ->assertJsonPath('motivo', 'Esa carta no está en tu mano.');

        $this->assertSame(1, $partida->eventos()->count());
    }

    public function test_una_accion_mal_escrita_se_rechaza(): void
    {
        $jugador = $this->sentado();
        // Si al bot le tocó ser mano, ya jugó al repartir: se compara contra lo que había, no contra un número fijo.
        $antes = Partida::sole()->eventos()->count();

        foreach ([[], ['tipo' => 'bailar'], ['tipo' => 'jugar'], ['tipo' => 'jugar', 'carta' => '9-oro'], ['tipo' => ['truco']]] as $datos) {
            $this->actingAs($jugador)->postJson('/mesa/accion', $datos)->assertStatus(422)->assertJsonPath('motivo', 'Esa acción no existe.');
        }

        $this->assertSame($antes, Partida::sole()->eventos()->count());
    }

    public function test_sin_sesion_no_se_puede_jugar(): void
    {
        $this->postJson('/mesa/accion', ['tipo' => 'mazo'])->assertUnauthorized();
        $this->postJson('/mesa/repartir')->assertUnauthorized();
        $this->post('/mesa/abandonar')->assertRedirect('/');
    }

    public function test_sin_partida_en_curso_la_mesa_lo_dice(): void
    {
        $jugador = Jugador::factory()->invitado()->create();

        $this->actingAs($jugador)->postJson('/mesa/accion', ['tipo' => 'mazo'])
            ->assertStatus(409)
            ->assertJsonPath('motivo', 'No tenés una partida en curso.');
    }

    public function test_nadie_puede_tocar_la_partida_de_otro(): void
    {
        $this->sentado();
        $ajena = Partida::sole();
        $antes = $ajena->eventos()->count();

        // Otro jugador, sin partida propia, intenta jugar: no hay forma de nombrar la partida ajena.
        $this->actingAs(Jugador::factory()->invitado()->create())
            ->postJson('/mesa/accion', ['tipo' => 'mazo', 'partida' => $ajena->id, 'partida_id' => $ajena->id])
            ->assertStatus(409);

        $this->assertSame($antes, $ajena->eventos()->count());
        $this->assertTrue($ajena->fresh()->enCurso());
    }

    public function test_repartir_solo_vale_entre_dos_manos(): void
    {
        $jugador = $this->sentado();
        $partida = Partida::sole();

        $this->actingAs($jugador)->postJson('/mesa/repartir')
            ->assertStatus(422)
            ->assertJsonPath('motivo', 'La mano todavía se está jugando: no se puede repartir.');

        // Se va al mazo cuando le toque: la mano se cierra y ahí sí se reparte.
        $this->irseAlMazo($jugador, $partida);

        $pasos = $this->actingAs($jugador)->postJson('/mesa/repartir')->assertOk()->json('pasos');

        $this->assertSame(2, $pasos[0]['numeroDeMano']);
        $this->assertSame('reparto', $pasos[0]['hechos'][0]['tipo']);
        $this->assertCount(3, $pasos[0]['misCartas']);
    }

    public function test_ninguna_respuesta_trae_las_cartas_del_bot(): void
    {
        $jugador = $this->sentado();
        $partida = Partida::sole();
        $delBot = $partida->eventos()->first()->datos['manos'][Mesa::BOT];

        $respuesta = $this->actingAs($jugador)->postJson('/mesa/accion', $this->unaAccionValida($partida))->assertOk();

        foreach ($respuesta->json('pasos') as $paso) {
            $jugadas = array_merge(...array_map(fn (array $baza) => array_column($baza['jugadas'], 1), $paso['bazas']));
            $mostradas = array_merge([], ...array_values($paso['cierre']['mostradas'] ?? []));

            // Se comparan cartas enteras: "1-oro" no es lo mismo que el final de "11-oro".
            preg_match_all('/\b(?:1[0-2]|[1-7])-(?:espada|basto|oro|copa)\b/', json_encode($paso), $nombradas);

            foreach (array_diff($delBot, $jugadas, $mostradas) as $oculta) {
                $this->assertNotContains($oculta, $nombradas[0], "La respuesta trae el {$oculta} del bot, que no se jugó.");
            }
        }
    }

    public function test_la_mesa_puede_pedir_como_esta_la_partida_para_ponerse_al_dia(): void
    {
        $jugador = Jugador::factory()->invitado()->create();
        $partida = $this->partidaArmada($jugador, [['4-copa', '5-copa', '6-basto'], ['1-espada', '3-oro', '10-basto']]);

        $respuesta = $this->actingAs($jugador)->getJson('/mesa/estado')->assertOk();

        $this->assertSame($this->app->make(Mesa::class)->vista($partida), $respuesta->json('vista'));

        foreach (['1-espada', '3-oro', '10-basto'] as $delBot) {
            $this->assertStringNotContainsString($delBot, $respuesta->getContent());
        }

        $this->actingAs(Jugador::factory()->invitado()->create())->getJson('/mesa/estado')->assertStatus(409);
    }

    public function test_abandonar_dos_veces_seguidas_no_falla(): void
    {
        $jugador = $this->sentado();
        $partida = Partida::sole();

        // El segundo pedido llega con la partida ya cerrada por el primero.
        $this->app->make(Mesa::class)->abandonar($partida);
        $this->actingAs($jugador)->post('/mesa/abandonar')->assertRedirect('/modos');

        $this->assertSame(Partida::ABANDONADA, $partida->fresh()->estado);
        $this->assertSame(1, $partida->eventos()->where('tipo', EventoDePartida::ABANDONO)->count());
    }

    public function test_abandonar_cierra_la_partida_y_vuelve_a_los_modos(): void
    {
        $jugador = $this->sentado();

        $this->actingAs($jugador)->post('/mesa/abandonar')
            ->assertRedirect('/modos')
            ->assertSessionHas('aviso', 'Abandonaste la partida.');

        $this->assertSame(Partida::ABANDONADA, Partida::sole()->estado);

        // La próxima vez que aprieta "Jugar" arranca otra.
        $this->actingAs($jugador)->post('/jugar');

        $this->assertSame(2, Partida::count());
    }

    /**
     * Un invitado con su partida abierta.
     */
    private function sentado(): Jugador
    {
        $jugador = Jugador::factory()->invitado()->create();
        $this->app->make(Mesa::class)->abrir($jugador);

        return $jugador;
    }

    /**
     * Una partida con el jugador de mano y las cartas que se indiquen, guardada como la guardaría la mesa.
     *
     * @param  array{0: list<string>, 1: list<string>}  $manos  Las del jugador y las del bot.
     */
    private function partidaArmada(Jugador $jugador, array $manos): Partida
    {
        $partida = Partida::create(['jugador_id' => $jugador->id, 'primer_mano' => Mesa::JUGADOR, 'puntos' => 30]);

        $partida->eventos()->create([
            'numero' => 1,
            'tipo' => EventoDePartida::REPARTO,
            'datos' => ['manos' => $manos],
            'creado_en' => now(),
        ]);

        return $partida;
    }

    /**
     * La primera acción que la mesa le ofrece al jugador. Si el bot es mano, ya jugó al repartir: siempre le toca al jugador.
     *
     * @return array{tipo: string, carta?: string}
     */
    private function unaAccionValida(Partida $partida): array
    {
        return $this->app->make(Mesa::class)->vista($partida)['acciones'][0];
    }

    private function irseAlMazo(Jugador $jugador, Partida $partida): void
    {
        $this->actingAs($jugador)->postJson('/mesa/accion', ['tipo' => 'mazo'])->assertOk();

        $this->assertSame('por_repartir', $this->app->make(Mesa::class)->vista($partida->fresh())['fase']);
    }
}
