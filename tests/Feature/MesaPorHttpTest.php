<?php

namespace Tests\Feature;

use App\Jobs\TurnoDelBot;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Motor\Accion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

    public function test_la_partida_guarda_el_nivel_del_bot_que_se_eligio(): void
    {
        $this->post('/jugar', ['nivel' => 3])->assertRedirect('/mesa');

        $this->assertSame(Nivel::Dificil, Partida::sole()->nivel_bot);
    }

    public function test_sin_elegir_nivel_se_juega_contra_intermedio(): void
    {
        $this->post('/jugar')->assertRedirect('/mesa');

        $this->assertSame(Nivel::Intermedio, Partida::sole()->nivel_bot);
    }

    public function test_un_nivel_que_no_existe_se_rechaza_sin_crear_nada(): void
    {
        foreach ([0, 5, 'difícil', ['3']] as $nivel) {
            $this->post('/jugar', ['nivel' => $nivel])->assertSessionHasErrors('nivel');
        }

        $this->assertSame(0, Partida::count());
        $this->assertSame(0, Jugador::count());
    }

    public function test_con_una_partida_sin_terminar_se_retoma_esa_con_su_nivel_aunque_se_pida_otro(): void
    {
        $this->post('/jugar', ['nivel' => 1]);
        $this->post('/jugar', ['nivel' => 3])->assertRedirect('/mesa');

        $this->assertSame(Nivel::Facil, Partida::sole()->nivel_bot);
    }

    public function test_la_mesa_muestra_la_partida_en_curso_con_las_plantillas_de_las_cartas(): void
    {
        $jugador = $this->sentado();

        $html = $this->actingAs($jugador)->get('/mesa')->assertOk()->assertSee('Vale Cuatro')->getContent();

        // Las 40 cartas y el dorso, para dibujar cualquier jugada sin volver al servidor.
        $this->assertSame(41, substr_count($html, '<template data-plantilla='));
    }

    public function test_la_mesa_dice_contra_que_nivel_se_juega_y_la_partida_siguiente_es_contra_el_mismo(): void
    {
        $jugador = Jugador::factory()->invitado()->create();
        $this->app->make(Mesa::class)->abrir($jugador, Nivel::Dificil);

        $html = $this->actingAs($jugador)->get('/mesa')->assertOk()
            ->assertSee('Mesa contra el bot, nivel Difícil')
            ->assertSee('Bot difícil')
            ->getContent();

        $this->assertMatchesRegularExpression('/<form[^>]*action="[^"]*\/jugar"[^>]*>.*?name="nivel" value="3".*?Jugar otra partida/s', $html);

        // Junto al rival van los fósforos de su nivel: tres para Difícil.
        preg_match('/<section aria-label="Rival".*?<\/section>/s', $html, $rival);
        $this->assertSame(3, substr_count($rival[0], 'class="fosforo puesto"'));
    }

    public function test_los_fosforos_de_la_mesa_cuentan_el_nivel(): void
    {
        foreach (Nivel::cases() as $nivel) {
            $jugador = Jugador::factory()->invitado()->create();
            $this->app->make(Mesa::class)->abrir($jugador, $nivel);

            preg_match('/<section aria-label="Rival".*?<\/section>/s', $this->actingAs($jugador)->get('/mesa')->getContent(), $rival);

            $this->assertSame($nivel->value, substr_count($rival[0], 'class="fosforo puesto"'), $nivel->nombre());
        }
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

    public function test_una_accion_valida_devuelve_su_paso_y_queda_guardada(): void
    {
        $jugador = $this->sentado();
        $partida = Partida::sole();
        $antes = $partida->eventos()->count();

        $pasos = $this->actingAs($jugador)->postJson('/mesa/accion', $this->unaAccionValida($partida))
            ->assertOk()
            ->assertJsonStructure(['pasos' => [['fase', 'tanteo', 'misCartas', 'bazas', 'acciones', 'hechos', 'evento']]])
            ->json('pasos');

        // La respuesta trae la jugada propia y nada más: el bot juega aparte.
        $this->assertCount(1, $pasos);
        $this->assertSame(Mesa::JUGADOR, $pasos[0]['asiento']);
        $this->assertSame($antes + 1, $pasos[0]['evento']);
    }

    public function test_la_consulta_corta_trae_lo_que_jugo_el_bot_despues_de_un_evento(): void
    {
        $jugador = Jugador::factory()->invitado()->create();
        $partida = $this->partidaArmada($jugador, [['4-copa', '5-copa', '6-basto'], ['1-espada', '6-oro', '10-basto']]);

        // En los tests la cola corre en el momento: cuando vuelve la respuesta, el bot ya jugó.
        $propio = $this->actingAs($jugador)->postJson('/mesa/accion', ['tipo' => 'jugar', 'carta' => '4-copa'])->json('pasos.0.evento');
        $pasos = $this->actingAs($jugador)->getJson("/mesa/estado?partida={$partida->id}&desde={$propio}")->assertOk()->json('pasos');

        $this->assertNotEmpty($pasos);
        $this->assertSame(range($propio + 1, $partida->eventos()->count()), array_column($pasos, 'evento'));
        $this->assertSame('6-oro', $pasos[0]['hechos'][0]['carta'], 'El primer paso es la carta con la que el bot ganó la baza.');

        // Lo que no se jugó no viaja: ni el ancho ni el 10 del bot.
        foreach (['1-espada', '10-basto'] as $oculta) {
            $this->assertStringNotContainsString($oculta, json_encode($pasos));
        }

        // Al día, no hay nada nuevo.
        $ultimo = $partida->eventos()->count();
        $this->actingAs($jugador)->getJson("/mesa/estado?partida={$partida->id}&desde={$ultimo}")->assertOk()->assertExactJson(['pasos' => []]);
    }

    public function test_la_consulta_corta_sigue_contestando_si_la_partida_ya_se_cerro(): void
    {
        $jugador = $this->sentado();
        $partida = Partida::sole();
        $this->app->make(Mesa::class)->abandonar($partida);

        // La última jugada del bot puede ser la que cierra la partida: la mesa tiene que poder enterarse.
        $this->actingAs($jugador)->getJson("/mesa/estado?partida={$partida->id}&desde=0")
            ->assertOk()
            ->assertJsonStructure(['pasos' => [['fase', 'evento', 'partida']]]);

        // Cerrada y sin nada nuevo que contar: ya no hay bot al que esperar, y la mesa se recarga.
        $ultimo = $partida->eventos()->count();
        $this->actingAs($jugador)->getJson("/mesa/estado?partida={$partida->id}&desde={$ultimo}")->assertStatus(409);

        $this->actingAs($jugador)->getJson('/mesa/estado')->assertStatus(409);
        $this->actingAs($jugador)->getJson("/mesa/estado?partida={$partida->id}&desde=ayer")->assertStatus(422);
        $this->actingAs($jugador)->getJson('/mesa/estado?desde=0')->assertStatus(422);
    }

    public function test_una_pestana_que_quedo_con_una_partida_vieja_no_recibe_pasos_de_la_nueva(): void
    {
        $jugador = $this->sentado();
        $vieja = Partida::sole();

        // Desde otra pestaña abandona y empieza otra partida.
        $this->app->make(Mesa::class)->abandonar($vieja);
        $nueva = $this->app->make(Mesa::class)->abrir($jugador);

        $this->actingAs($jugador)->getJson("/mesa/estado?partida={$vieja->id}&desde=0")->assertStatus(409);
        $this->actingAs($jugador)->getJson("/mesa/estado?partida={$nueva->id}&desde=0")->assertOk();
    }

    public function test_si_la_partida_termino_mientras_no_miraba_al_volver_a_la_mesa_se_le_cuenta_como_termino(): void
    {
        $jugador = $this->sentado();
        $partida = Partida::sole();
        $mesa = $this->app->make(Mesa::class);

        // Se juega hasta el final, irse al mazo en cada mano: gana el bot.
        for ($pedidos = 0; $partida->fresh()->enCurso(); $pedidos++) {
            $this->assertLessThan(400, $pedidos, 'La partida no termina.');

            $vista = $mesa->vista($partida);
            $vista['fase'] === 'por_repartir' ? $mesa->repartir($partida) : $mesa->actuar($partida, Accion::desdeArray($this->salida($vista['acciones'])));
        }

        $this->actingAs($jugador)->get('/mesa')
            ->assertRedirect('/modos')
            ->assertSessionHas('aviso', fn (string $aviso) => preg_match('/^Tu última partida terminó \d+ a 30: ganó el bot\.$/u', $aviso) === 1);
    }

    public function test_sin_partida_o_con_la_ultima_abandonada_la_mesa_no_avisa_nada(): void
    {
        $jugador = $this->sentado();
        $this->app->make(Mesa::class)->abandonar(Partida::sole());

        $this->actingAs($jugador)->get('/mesa')->assertRedirect('/modos')->assertSessionMissing('aviso');
    }

    public function test_el_turno_del_bot_va_a_la_cola_y_la_red_de_seguridad_lo_hace_jugar_si_la_cola_no_corre(): void
    {
        Queue::fake();

        $jugador = Jugador::factory()->invitado()->create();
        $partida = $this->partidaArmada($jugador, [['4-copa', '5-copa', '6-basto'], ['1-espada', '6-oro', '10-basto']]);

        $propio = $this->actingAs($jugador)->postJson('/mesa/accion', ['tipo' => 'jugar', 'carta' => '4-copa'])->json('pasos.0.evento');

        Queue::assertPushed(TurnoDelBot::class, fn (TurnoDelBot $turno) => $turno->partidaId === $partida->id);

        // Nadie atiende la cola: el bot no jugó.
        $this->actingAs($jugador)->getJson("/mesa/estado?partida={$partida->id}&desde={$propio}")->assertExactJson(['pasos' => []]);

        $this->actingAs($jugador)->postJson('/mesa/bot')->assertOk();

        $pasos = $this->actingAs($jugador)->getJson("/mesa/estado?partida={$partida->id}&desde={$propio}")->json('pasos');

        $this->assertNotEmpty($pasos);
        $this->assertNotSame([], $pasos[count($pasos) - 1]['acciones'], 'El bot jugó lo suyo y le toca al jugador.');

        // Sin partida en curso no hay a quién despertar.
        $this->actingAs(Jugador::factory()->invitado()->create())->postJson('/mesa/bot')->assertStatus(409);
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
        $this->postJson('/mesa/bot')->assertUnauthorized();
        $this->getJson('/mesa/estado?partida=1&desde=0')->assertUnauthorized();
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

        // La respuesta a la jugada propia y todo lo que devuelve la consulta corta, con las jugadas del bot.
        $pasos = [...$respuesta->json('pasos'), ...$this->actingAs($jugador)->getJson("/mesa/estado?partida={$partida->id}&desde=0")->assertOk()->json('pasos')];

        foreach ($pasos as $paso) {
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

    /**
     * La forma más corta de perder una mano: no querer lo que le canten y, si le toca, irse al mazo.
     *
     * @param  list<array{tipo: string, carta?: string}>  $acciones
     * @return array{tipo: string}
     */
    private function salida(array $acciones): array
    {
        $tipos = array_column($acciones, 'tipo');

        return ['tipo' => in_array('no_quiero', $tipos, true) ? 'no_quiero' : 'mazo'];
    }

    private function irseAlMazo(Jugador $jugador, Partida $partida): void
    {
        $this->actingAs($jugador)->postJson('/mesa/accion', ['tipo' => 'mazo'])->assertOk();

        $this->assertSame('por_repartir', $this->app->make(Mesa::class)->vista($partida->fresh())['fase']);
    }
}
