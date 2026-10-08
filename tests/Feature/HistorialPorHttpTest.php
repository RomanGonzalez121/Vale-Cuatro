<?php

namespace Tests\Feature;

use App\Juego\Historial;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Motor\Accion;
use App\Motor\Carta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las dos pantallas del historial: quién ve qué. La lista muestra las partidas propias (a quien juega sin
 * cuenta, las de su sesión) y la repetición solo se le abre a quien jugó esa partida, y nunca mientras se juega.
 */
class HistorialPorHttpTest extends TestCase
{
    use JugandoPartidas;
    use RefreshDatabase;

    public function test_sin_haber_jugado_nunca_la_lista_esta_vacia_y_ofrece_jugar(): void
    {
        $this->get('/historial')
            ->assertOk()
            ->assertSee('Todavía no terminaste ninguna partida.')
            ->assertSee('Jugar contra el bot')
            ->assertSee('action="'.route('jugar').'"', false)
            // Ni una partida de ejemplo: lo que no se jugó no se muestra.
            ->assertDontSee('Ver de nuevo')
            ->assertDontSee('Maru');
    }

    public function test_la_lista_muestra_las_partidas_cerradas_de_quien_entra_con_el_link_a_su_repeticion(): void
    {
        $jugador = Jugador::factory()->create();
        [$partida] = $this->partidaContraElBot($jugador);
        $resumen = $this->app->make(Historial::class)->resumen($partida, 0);

        // La de otra persona no aparece en esta lista.
        [$ajena] = $this->partidaContraElBot(Jugador::factory()->create());

        $this->actingAs($jugador)->get('/historial')
            ->assertOk()
            ->assertSee($resumen['gano'] ? 'Ganaste' : 'Perdiste')
            ->assertSee("{$resumen['vos']} a {$resumen['ellos']}")
            ->assertSee('Contra Bot fácil')
            ->assertSee('href="'.route('historial.ver', $partida->id).'"', false)
            ->assertDontSee('href="'.route('historial.ver', $ajena->id).'"', false)
            ->assertDontSee('Jugás sin cuenta');
    }

    public function test_una_partida_sin_terminar_no_esta_en_la_lista_y_se_avisa_aparte(): void
    {
        $jugador = Jugador::factory()->create();
        $enCurso = $this->laMesa()->abrir($jugador);

        $this->actingAs($jugador)->get('/historial')
            ->assertOk()
            ->assertSee('Tenés una partida sin terminar.')
            ->assertSee('Seguir la partida')
            ->assertDontSee('href="'.route('historial.ver', $enCurso->id).'"', false);
    }

    public function test_quien_juega_sin_cuenta_ve_las_partidas_de_su_sesion_y_al_vencer_la_sesion_arranca_vacia(): void
    {
        $invitado = Jugador::factory()->invitado()->create();
        $mesa = $this->laMesa();

        // Entra y la página anota cuándo empezó su sesión. Después juega y abandona una partida.
        $this->actingAs($invitado)->get('/modos')->assertSessionHas('invitado_desde');
        $marca = session('invitado_desde');

        $this->travel(10)->minutes();
        $partida = $mesa->abrir($invitado);
        $mesa->abandonar($partida);

        $this->actingAs($invitado)->withSession(['invitado_desde' => $marca])->get('/historial')
            ->assertOk()
            ->assertSee('Jugás sin cuenta')
            ->assertSee('href="'.route('registro').'"', false)
            ->assertSee('href="'.route('historial.ver', $partida->id).'"', false);

        $this->actingAs($invitado)->withSession(['invitado_desde' => $marca])->get(route('historial.ver', $partida->id))->assertOk();

        // Tres horas después la sesión venció. El navegador lo sigue reconociendo, pero la sesión es nueva.
        $this->travel(3)->hours();
        $this->flushSession();

        $this->actingAs($invitado)->get('/historial')
            ->assertOk()
            ->assertSee('Todavía no terminaste ninguna partida en esta sesión.')
            ->assertDontSee('href="'.route('historial.ver', $partida->id).'"', false);

        // Tampoco se abre escribiendo la dirección a mano. Y no se borró: sigue en la base.
        $this->actingAs($invitado)->get(route('historial.ver', $partida->id))->assertNotFound();
        $this->assertDatabaseHas('partidas', ['id' => $partida->id]);
    }

    public function test_al_crear_la_cuenta_recupera_todo_lo_que_jugo_como_invitado(): void
    {
        $invitado = Jugador::factory()->invitado()->create();
        $mesa = $this->laMesa();
        $vieja = $mesa->abrir($invitado);
        $mesa->abandonar($vieja);

        $this->travel(3)->hours();

        // Se registra: sigue siendo el mismo jugador, ahora con cuenta.
        $this->actingAs($invitado)->post(route('registro'), [
            'apodo' => 'Roman',
            'email' => 'roman@example.com',
            'password' => 'una-clave-larga-1',
            'password_confirmation' => 'una-clave-larga-1',
        ]);

        $conCuenta = $invitado->fresh();
        $this->assertFalse($conCuenta->esInvitado());

        $this->actingAs($conCuenta)->get('/historial')
            ->assertOk()
            ->assertDontSee('Jugás sin cuenta')
            ->assertSee('href="'.route('historial.ver', $vieja->id).'"', false);
    }

    public function test_la_repeticion_solo_se_le_abre_a_quien_jugo_la_partida(): void
    {
        [$uno, $dos, $tercero] = [Jugador::factory()->create(), Jugador::factory()->create(), Jugador::factory()->create()];
        $partida = $this->partidaEntrePersonas($uno, $dos);

        // Las dos puertas de la repetición: la página y los datos que pide el cartel de la lista.
        foreach ([route('historial.ver', $partida->id), route('historial.cuadros', $partida->id)] as $ruta) {
            $this->actingAs($uno)->get($ruta)->assertOk();
            $this->actingAs($dos)->get($ruta)->assertOk();

            // Para cualquier otro, esa partida no existe.
            $this->actingAs($tercero)->get($ruta)->assertNotFound();
        }

        $this->actingAs($uno)->get(route('historial.ver', 999999))->assertNotFound();
        $this->actingAs($uno)->getJson(route('historial.cuadros', 999999))->assertNotFound();

        // Sin sesión, la página vuelve a la portada, como la mesa, y los datos no se entregan.
        auth()->logout();
        $this->get(route('historial.ver', $partida->id))->assertRedirect(route('portada'));
        $this->getJson(route('historial.cuadros', $partida->id))->assertUnauthorized();
    }

    public function test_la_pagina_y_el_cartel_reciben_los_mismos_datos(): void
    {
        $jugador = Jugador::factory()->create();
        [$partida] = $this->partidaContraElBot($jugador);

        $delCartel = $this->actingAs($jugador)->getJson(route('historial.cuadros', $partida->id))->assertOk()->json();
        $deLaPagina = json_decode($this->datosDeLaPagina($this->actingAs($jugador)->get(route('historial.ver', $partida->id))->assertOk()->assertSee('Volver al historial')->getContent()), true);

        $this->assertSame($delCartel, $deLaPagina);
        $this->assertSame(['resumen', 'rival', 'cuadros', 'manos'], array_keys($delCartel));
        $this->assertSame($partida->id, $delCartel['resumen']['id']);
        $this->assertSame('el bot', $delCartel['rival']);
    }

    public function test_la_lista_trae_el_cartel_y_cada_link_sabe_donde_pedir_su_repeticion(): void
    {
        $jugador = Jugador::factory()->create();
        [$partida] = $this->partidaContraElBot($jugador);

        // El link sigue siendo un link a la página: sin JavaScript, o con Ctrl, se abre como cualquier otro.
        $this->actingAs($jugador)->get('/historial')
            ->assertOk()
            ->assertSee('<dialog', false)
            ->assertSee('href="'.route('historial.ver', $partida->id).'"', false)
            ->assertSee('data-cuadros="'.route('historial.cuadros', $partida->id).'"', false);

        // Sin partidas no hay nada que abrir: el cartel no se escribe.
        $this->actingAs(Jugador::factory()->create())->get('/historial')->assertOk()->assertDontSee('<dialog', false);
    }

    public function test_una_partida_que_todavia_se_juega_no_tiene_repeticion(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create()];
        $mesa = $this->laMesa();
        $partida = $mesa->sentarse($mesa->crearSala($uno)->codigo, $dos);
        $cartasDelOtro = $mesa->vista($partida, 1)['misCartas'];

        // A quien la está jugando se lo manda a la mesa; nadie recibe las cartas sin jugar, ni como página ni como datos.
        $pagina = $this->actingAs($uno)->get(route('historial.ver', $partida->id))->assertRedirect(route('mesa'));
        $datos = $this->actingAs($uno)->getJson(route('historial.cuadros', $partida->id))->assertNotFound();

        foreach ($cartasDelOtro as $carta) {
            $this->assertStringNotContainsString($carta, (string) $pagina->getContent());
            $this->assertStringNotContainsString($carta, (string) $datos->getContent());
        }

        $this->actingAs(Jugador::factory()->create())->get(route('historial.ver', $partida->id))->assertNotFound();
    }

    public function test_la_repeticion_no_trae_las_cartas_que_otra_persona_no_mostro_y_si_las_del_bot(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(['apodo' => 'Roman']), Jugador::factory()->create(['apodo' => 'La Tana'])];
        $partida = $this->partidaArmadaEntrePersonas($uno, $dos, [['7-oro', '6-oro', '1-espada'], ['5-copa', '4-copa', '3-basto']]);
        $mesa = $this->laMesa();

        $mesa->actuar($partida, Accion::jugar(Carta::de('6-oro')), 0);
        $mesa->actuar($partida, Accion::jugar(Carta::de('5-copa')), 1);
        $mesa->abandonar($partida->fresh(), 1);

        // Lo que recibe el navegador de quien jugó contra La Tana, por cualquiera de las dos puertas (los datos
        // que van escritos en la página y los que pide el cartel): su carta jugada sí, las otras dos no.
        // De la página se miran los datos de la partida y no el HTML entero, que trae dibujadas las 40 cartas del mazo.
        $respuestas = [
            $this->datosDeLaPagina($this->actingAs($uno)->get(route('historial.ver', $partida->id))->assertOk()->getContent()),
            $this->actingAs($uno)->getJson(route('historial.cuadros', $partida->id))->assertOk()->assertJsonPath('rival', 'La Tana')->getContent(),
        ];

        foreach ($respuestas as $respuesta) {
            $this->assertStringContainsString('5-copa', $respuesta);
            $this->assertStringNotContainsString('4-copa', $respuesta);
            $this->assertStringNotContainsString('3-basto', $respuesta);
        }

        // Contra el bot es una repetición completa: sus tres cartas de cada mano están desde el reparto.
        $jugador = Jugador::factory()->create();
        [$contraElBot] = $this->partidaContraElBot($jugador);
        $delBot = $contraElBot->eventos()->get()->firstWhere('tipo', EventoDePartida::REPARTO)->datos['manos'][1];
        $datos = $this->actingAs($jugador)->getJson(route('historial.cuadros', $contraElBot->id))->assertOk()->assertJsonPath('resumen.bot', true);

        $this->assertSame($delBot, $datos->json('cuadros.0.rival'));
    }

    public function test_despues_de_una_partida_que_cerro_el_bot_el_historial_la_muestra(): void
    {
        $jugador = Jugador::factory()->create();
        [$partida] = $this->partidaContraElBot($jugador);

        // Vuelve por "Jugar" y empieza otra sin pasar por la mesa vieja: la anterior no se perdió de vista.
        $this->actingAs($jugador)->post(route('jugar'));

        $this->actingAs($jugador)->get('/historial')
            ->assertOk()
            ->assertSee('href="'.route('historial.ver', $partida->id).'"', false)
            ->assertSee('Tenés una partida sin terminar.');

        $this->assertSame(2, Partida::count());
    }

    /**
     * Lo que la página le pasa a la repetición: los datos, tal como van escritos en el HTML.
     */
    private function datosDeLaPagina(string $pagina): string
    {
        $this->assertSame(1, preg_match('/<script type="application\/json" id="datos-de-la-repeticion">(.*?)<\/script>/s', $pagina, $encontrado));

        return $encontrado[1];
    }
}
