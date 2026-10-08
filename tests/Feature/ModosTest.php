<?php

namespace Tests\Feature;

use App\Identidad\Iconos;
use App\Juego\Mesa;
use App\Juego\Modos;
use App\Juego\Nivel;
use App\Models\Jugador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La pantalla donde se elige el modo de juego. Los juegos que ya se juegan están en la mano,
 * cada uno con su carta; los que faltan, en el mazo. Dentro de cada juego se elige el rival.
 */
class ModosTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pantalla_nombra_todos_los_juegos(): void
    {
        $respuesta = $this->get('/modos')->assertOk();

        foreach (Modos::juegos() as $juego) {
            $respuesta->assertSee($juego['nombre'])->assertSee($juego['resumen']);
        }
    }

    public function test_en_la_mano_hay_una_carta_por_cada_juego_que_se_juega(): void
    {
        $html = $this->get('/modos')->assertOk()->getContent();
        $seJuegan = array_values(array_filter(Modos::juegos(), Modos::seJuega(...)));

        $this->assertSame(count($seJuegan), substr_count($html, 'aria-label="Carta del modo '));

        foreach ($seJuegan as $juego) {
            $this->assertStringContainsString("aria-label=\"Carta del modo {$juego['nombre']}\"", $html);
        }
    }

    public function test_solo_los_rivales_que_se_juegan_tienen_boton(): void
    {
        $html = $this->get('/modos')->assertOk()->getContent();
        $botones = array_filter(array_merge(...array_map(fn (array $juego) => array_column($juego['rivales'], 'boton'), Modos::juegos())));

        // Un formulario por cada rival contra el que se puede jugar, y ninguno más: al bot va a /jugar y a una persona, a /invitar.
        $this->assertSame(count($botones), preg_match_all('/<form[^>]*action="[^"]*\/(?:jugar|invitar)"/', $html));
        $this->assertSame(1, preg_match_all('/<form[^>]*action="[^"]*\/invitar"/', $html), 'Con otra persona se juega por /invitar.');

        foreach ($botones as $boton) {
            $this->assertStringContainsString($boton, $html);
        }
    }

    public function test_contra_el_bot_se_elige_el_nivel_y_entra_elegido_intermedio(): void
    {
        $html = $this->get('/modos')->assertOk()->getContent();

        $botones = $this->botonesDeNivel($html);

        $this->assertCount(count(Nivel::cases()), $botones);

        foreach (Nivel::cases() as $i => $nivel) {
            $elegido = $nivel === Nivel::Intermedio;

            $this->assertStringContainsString('aria-pressed="'.($elegido ? 'true' : 'false').'"', $botones[$i]);
            $this->assertStringEndsWith("{$nivel->corto()}</button>", $botones[$i]);
            $this->assertStringStartsNotWith('<button type="button" disabled', $botones[$i]);

            // La dificultad se cuenta con fósforos: uno, dos, tres y el cuadrado de cuatro. Solo los del nivel elegido están puestos.
            $this->assertSame($nivel->value, substr_count($botones[$i], 'class="fosforo-cabeza"'), "{$nivel->nombre()} tiene que mostrar {$nivel->value} fósforos.");
            $this->assertSame($elegido ? $nivel->value : 0, substr_count($botones[$i], 'class="fosforo puesto"'));

            // En la fila va el nombre corto; el completo va arriba de la frase, dicho como en la mesa.
            $this->assertStringContainsString('Bot '.mb_strtolower($nivel->nombre())."</span>{$nivel->detalle()}", $html);
        }

        // El nivel viaja en el formulario, y sin tocar nada es el de siempre.
        $this->assertMatchesRegularExpression('/<input type="hidden" name="nivel" value="2"/', $html);
        $this->assertStringNotContainsString('sin terminar', $html);
    }

    public function test_con_una_partida_sin_terminar_lo_avisa_apaga_los_niveles_y_ofrece_seguirla(): void
    {
        $jugador = Jugador::factory()->invitado()->create();
        $this->app->make(Mesa::class)->abrir($jugador, Nivel::Dificil);

        $html = $this->actingAs($jugador)->get('/modos')->assertOk()
            ->assertSee('Tenés una partida sin terminar contra Difícil.')
            ->assertSee('Seguir la partida')
            ->assertDontSee('Jugar contra el bot')
            ->getContent();

        $botones = $this->botonesDeNivel($html);

        // Todos los niveles están apagados, y el marcado es el de la partida.
        foreach ($botones as $boton) {
            $this->assertStringStartsWith('<button type="button" disabled', $boton);
        }

        $this->assertSame(['false', 'false', 'true', 'false'], array_map(fn (string $boton) => preg_match('/aria-pressed="(\w+)"/', $boton, $marca) ? $marca[1] : null, $botones));
        $this->assertStringEndsWith('Difícil</button>', $botones[2]);
        $this->assertSame(3, substr_count($botones[2], 'class="fosforo puesto"'));
        $this->assertMatchesRegularExpression('/<input type="hidden" name="nivel" value="3"/', $html);
    }

    /**
     * Los botones del selector de nivel, en orden.
     *
     * @return list<string>
     */
    private function botonesDeNivel(string $html): array
    {
        preg_match('/<div[^>]*aria-label="Nivel del bot">(.*?)<\/div>/s', $html, $grupo);
        preg_match_all('/<button.*?<\/button>/s', $grupo[1] ?? '', $botones);

        return $botones[0];
    }

    public function test_la_partida_de_otro_no_cambia_lo_que_ve_un_jugador(): void
    {
        $this->app->make(Mesa::class)->abrir(Jugador::factory()->invitado()->create(), Nivel::Dificil);

        $this->actingAs(Jugador::factory()->invitado()->create())->get('/modos')
            ->assertOk()
            ->assertDontSee('sin terminar')
            ->assertSee('Jugar contra el bot');
    }

    public function test_lo_que_falta_esta_dicho_y_no_se_puede_jugar(): void
    {
        $html = $this->get('/modos')->assertOk()->getContent();
        $enElMazo = array_filter(Modos::juegos(), fn (array $juego) => ! Modos::seJuega($juego));

        $this->assertNotEmpty($enElMazo);
        $this->assertStringContainsString('Todavía en el mazo', $html);

        // La lista del mazo nombra cada juego que falta y no tiene ningún botón ni formulario.
        preg_match('/<section aria-labelledby="titulo-mazo".*?<\/section>/s', $html, $mazo);

        foreach ($enElMazo as $juego) {
            $this->assertStringContainsString($juego['nombre'], $mazo[0]);
        }

        $this->assertStringNotContainsString('<form', $mazo[0]);
        $this->assertStringNotContainsString('<button', $mazo[0]);
    }

    public function test_un_rival_que_todavia_no_se_juega_lo_dice_y_no_tiene_boton(): void
    {
        $html = $this->get('/modos')->assertOk()->getContent();

        $faltan = 0;

        foreach (array_filter(Modos::juegos(), Modos::seJuega(...)) as $juego) {
            foreach ($juego['rivales'] as $rival) {
                $this->assertStringContainsString($rival['nombre'], $html);
                $faltan += $rival['boton'] === null ? 1 : 0;
            }
        }

        $this->assertSame($faltan, substr_count($html, 'Todavía no se juega.'));
    }

    public function test_con_varios_juegos_en_la_mano_cada_carta_es_un_boton_y_entra_elegida_la_primera(): void
    {
        // Lo que va a pasar cuando se terminen de a cuatro y el torneo contra bots: se arma la pantalla con esos datos.
        $juegos = array_map(function (array $juego) {
            if (in_array($juego['clave'], ['de-a-cuatro', 'torneo'], true)) {
                $juego['rivales'][0]['boton'] = 'Jugar con bots';
            }

            return $juego;
        }, Modos::juegos());

        $html = (string) $this->view('paginas.modos', ['juegos' => $juegos]);

        $this->assertSame(3, substr_count($html, 'aria-label="Carta del modo '));
        $this->assertSame(1, preg_match_all('/class="naipe-juego"[^>]*aria-pressed="true"/s', $html));
        $this->assertSame(2, preg_match_all('/class="naipe-juego"[^>]*aria-pressed="false"/s', $html));
        $this->assertSame(3, preg_match_all('/<form[^>]*action="[^"]*\/jugar"/', $html));
    }

    public function test_jugar_del_menu_lleva_a_elegir_el_modo_desde_cualquier_pantalla(): void
    {
        foreach (['/', '/ranking', '/historial', '/como-se-juega', '/identidad', '/modos'] as $ruta) {
            $html = $this->get($ruta)->assertOk()->getContent();

            // Una vez en el menú de escritorio y otra en la barra de abajo del celular.
            $this->assertSame(2, preg_match_all('/<a href="[^"]*\/modos"[^>]*>\s*(?:<svg.*?<\/svg>)?\s*(?:<span class="barra-rotulo">)?Jugar(?:<\/span>)?\s*<\/a>/s', $html), "Falta \"Jugar\" en el menú de {$ruta}.");
        }
    }

    public function test_la_portada_sigue_entrando_a_la_mesa_en_un_solo_paso(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<form[^>]*action="[^"]*\/jugar"[^>]*>.*?Jugar contra el bot.*?<\/form>/s', $html);
    }

    public function test_el_catalogo_de_juegos_esta_bien_armado(): void
    {
        $juegos = Modos::juegos();

        $this->assertCount(count($juegos), array_unique(array_column($juegos, 'clave')));
        $this->assertTrue(Modos::seJuega($juegos[0]), 'El primer juego es el que entra elegido: tiene que poder jugarse.');

        foreach ($juegos as $juego) {
            // El ícono existe entre los propios: si no, tira un error.
            Iconos::de($juego['icono']);

            $this->assertNotEmpty($juego['rivales'], "{$juego['nombre']} no dice contra quién se juega.");
            $this->assertCount(count($juego['rivales']), array_unique(array_column($juego['rivales'], 'clave')));
            $this->assertLessThanOrEqual(2, count($juego['renglones']), "El nombre de {$juego['nombre']} no entra en la carta.");
        }
    }

    public function test_un_juego_se_juega_si_al_menos_un_rival_tiene_boton(): void
    {
        $this->assertTrue(Modos::seJuega(['rivales' => [['boton' => null], ['boton' => 'Jugar']]]));
        $this->assertFalse(Modos::seJuega(['rivales' => [['boton' => null], ['boton' => null]]]));
    }

    public function test_la_identidad_muestra_los_iconos_de_los_juegos(): void
    {
        $respuesta = $this->get('/identidad')->assertOk();

        foreach (['Mano a mano', 'De a cuatro', 'Torneo', 'Desafío', 'Escalera'] as $icono) {
            $respuesta->assertSee($icono);
        }
    }
}
