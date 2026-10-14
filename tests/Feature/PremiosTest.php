<?php

namespace Tests\Feature;

use App\Juego\Categoria;
use App\Juego\Experiencia;
use App\Juego\JugadoresDeEjemplo;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Juego\Premios;
use App\Juego\Progresos;
use App\Juego\Ranking;
use App\Juego\Torneos;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Progreso;
use App\Motor\Carta;
use App\Motor\Tanto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * El perfil y las categorías: lo que se gana jugando (la categoría, "tu carta" y los dorsos), lo que
 * se declara (cuánto se sabe) y lo que de todo eso llega a ver el rival.
 */
class PremiosTest extends TestCase
{
    use AnotandoResultados, JugandoPartidas, RefreshDatabase;

    public function test_la_categoria_sale_de_las_partidas_jugadas_y_se_rehace_leyendo_solo_los_eventos(): void
    {
        $uno = Jugador::factory()->create();
        $dos = Jugador::factory()->create();
        $premios = new Premios;

        // Cinco partidas de verdad entre los dos, guardadas con sus eventos.
        $partidas = array_map(fn () => $this->partidaEntrePersonas($uno, $dos), range(1, 5));
        $ganoUno = count(array_filter($partidas, fn (Partida $partida) => $partida->ganador === Mesa::JUGADOR));

        $this->assertSame($ganoUno, $premios->ganadas($uno));
        $this->assertSame(5 - $ganoUno, $premios->ganadas($dos));
        $this->assertSame(Categoria::Novato, $premios->categoria($uno));

        // Lo anotado se puede tirar y rehacer desde los eventos: da lo mismo.
        $this->artisan('ranking:recalcular')->assertSuccessful();

        $this->assertSame($ganoUno, $premios->ganadas($uno));
        $this->assertSame(5 - $ganoUno, $premios->ganadas($dos));
    }

    public function test_se_sube_de_categoria_con_las_partidas_ganadas_y_las_perdidas_no_cuentan(): void
    {
        $jugador = Jugador::factory()->create();
        $premios = new Premios;

        $this->assertSame(Categoria::Novato, $premios->categoria($jugador));

        $this->anotarle($jugador, str_repeat('G', 9).str_repeat('P', 40));
        $this->assertSame(Categoria::Novato, $premios->categoria($jugador));

        $this->anotarle($jugador, 'G');
        $this->assertSame(Categoria::Medio, $premios->categoria($jugador));

        $this->anotarle($jugador, str_repeat('G', 29));
        $this->assertSame(Categoria::Medio, $premios->categoria($jugador));

        $this->anotarle($jugador, 'G');
        $this->assertSame(Categoria::Avanzado, $premios->categoria($jugador));
    }

    public function test_el_camino_va_en_el_orden_del_truco_y_cada_carta_pide_mas_que_la_anterior(): void
    {
        $cartas = array_keys(Premios::CAMINO);
        $pide = array_values(Premios::CAMINO);

        $this->assertSame(Premios::CARTA_DE_FABRICA, $cartas[0]);
        $this->assertSame(0, $pide[0]);
        $this->assertSame('1-espada', end($cartas));

        for ($i = 1; $i < count($cartas); $i++) {
            $this->assertTrue(Carta::de($cartas[$i])->leGanaA(Carta::de($cartas[$i - 1])), "{$cartas[$i]} tiene que ganarle a {$cartas[$i - 1]}.");
            $this->assertGreaterThan($pide[$i - 1], $pide[$i]);
        }

        // Cada categoría arranca con una carta nueva: el tramo de medio y el de avanzado empiezan justo en su número.
        $this->assertContains(Categoria::Medio->desde(), $pide);
        $this->assertContains(Categoria::Avanzado->desde(), $pide);
    }

    public function test_una_carta_se_elige_solo_si_ya_se_gano_tambien_mandando_el_pedido_a_mano(): void
    {
        $jugador = Jugador::factory()->create();
        $this->anotarle($jugador, 'GGG');

        // Con tres ganadas tiene el 4 y el 5; el 6 pide cuatro.
        $this->actingAs($jugador)->put('/perfil/carta', ['carta' => '6-oro'])->assertSessionHasErrors('carta');
        $this->assertNull($jugador->fresh()->carta);

        $this->actingAs($jugador)->put('/perfil/carta', ['carta' => '1-espada'])->assertSessionHasErrors('carta');
        // Una carta que no es del camino tampoco, aunque exista en el mazo.
        $this->actingAs($jugador)->put('/perfil/carta', ['carta' => '1-oro'])->assertSessionHasErrors('carta');
        $this->assertNull($jugador->fresh()->carta);

        $this->actingAs($jugador)->put('/perfil/carta', ['carta' => '5-espada'])->assertRedirect('/perfil')->assertSessionHasNoErrors();
        $this->assertSame('5-espada', $jugador->fresh()->carta);
        $this->assertSame('5-espada', (new Premios)->cartaDe($jugador->fresh()));
    }

    public function test_una_carta_guardada_que_no_esta_ganada_no_se_muestra(): void
    {
        $jugador = Jugador::factory()->create();
        // Como si alguien la hubiera escrito en la base: al leerla se vuelve a comprobar.
        $jugador->forceFill(['carta' => '1-espada', 'dorso' => 'ronda'])->save();

        $premios = new Premios;

        $this->assertSame(Premios::CARTA_DE_FABRICA, $premios->cartaDe($jugador));
        $this->assertSame(Premios::DORSO_DE_FABRICA, $premios->dorsoDe($jugador));
    }

    public function test_un_dorso_se_elige_solo_si_se_paso_su_escalon_de_la_escalera(): void
    {
        $jugador = Jugador::factory()->create();

        $this->actingAs($jugador)->put('/perfil/dorso', ['dorso' => 'fosforos'])->assertSessionHasErrors('dorso');
        $this->actingAs($jugador)->put('/perfil/dorso', ['dorso' => 'inventado'])->assertSessionHasErrors('dorso');
        $this->assertNull($jugador->fresh()->dorso);

        // Pasa el escalón 3: gana "Fósforos", y nada más.
        $this->pasoElEscalon($jugador, 3);
        // Un escalón intentado y no pasado no destraba nada.
        (new Progreso)->forceFill(['jugador_id' => $jugador->id, 'clave' => Progresos::claveDeEscalon(6), 'intentos' => 2])->save();

        $this->actingAs($jugador)->put('/perfil/dorso', ['dorso' => 'tinta'])->assertSessionHasErrors('dorso');
        $this->actingAs($jugador)->put('/perfil/dorso', ['dorso' => 'fosforos'])->assertRedirect('/perfil')->assertSessionHasNoErrors();

        $this->assertSame('fosforos', $jugador->fresh()->dorso);
        $this->assertSame('fosforos', (new Premios)->dorsoDe($jugador->fresh()));

        // Y puede volver al de fábrica, que es de todos.
        $this->actingAs($jugador)->put('/perfil/dorso', ['dorso' => 'fabrica'])->assertSessionHasNoErrors();
        $this->assertSame('fabrica', $jugador->fresh()->dorso);
    }

    public function test_la_administracion_tiene_todo_abierto_pero_su_categoria_sale_de_sus_partidas(): void
    {
        $administracion = Jugador::factory()->administrador()->create();

        // Sin una sola partida elige el ancho de espada y el último dorso.
        $this->actingAs($administracion)->put('/perfil/carta', ['carta' => '1-espada'])->assertSessionHasNoErrors();
        $this->actingAs($administracion)->put('/perfil/dorso', ['dorso' => 'ronda'])->assertSessionHasNoErrors();

        $premios = new Premios;
        $administracion->refresh();

        $this->assertSame('1-espada', $premios->cartaDe($administracion));
        $this->assertSame('ronda', $premios->dorsoDe($administracion));
        $this->assertTrue(collect($premios->camino($administracion))->every(fn (array $paso) => $paso['abierta']));
        $this->assertTrue(collect($premios->dorsos($administracion))->every(fn (array $dorso) => $dorso['abierto']));

        // Pero no inventa partidas: sigue siendo novato, y lo que se ve de afuera dice eso.
        $this->assertSame(Categoria::Novato, $premios->categoria($administracion));
        $this->assertSame('Novato', $premios->aLaVista($administracion)['categoria']);
        $this->actingAs($administracion)->get('/perfil')->assertOk()->assertSee('tenés todas las cartas y los dorsos abiertos');

        // Y una cuenta común sigue con lo suyo.
        $this->assertFalse($premios->tieneCarta(Jugador::factory()->create(), '1-espada'));
    }

    public function test_quien_juega_sin_cuenta_no_elige_carta_ni_dorso(): void
    {
        $invitado = Jugador::factory()->invitado()->create();

        $this->actingAs($invitado)->put('/perfil/carta', ['carta' => '4-copa'])->assertRedirect('/registro');
        $this->actingAs($invitado)->put('/perfil/dorso', ['dorso' => 'fabrica'])->assertRedirect('/registro');
        $this->actingAs($invitado)->put('/perfil/experiencia', ['experiencia' => 1])->assertRedirect('/registro');
    }

    public function test_el_perfil_muestra_la_categoria_las_ganadas_y_lo_que_falta_para_la_proxima_carta(): void
    {
        $jugador = Jugador::factory()->create(['apodo' => 'El Tano']);
        $this->anotarle($jugador, str_repeat('G', 11));

        $this->actingAs($jugador)->get('/perfil')
            ->assertOk()
            ->assertSee('El Tano')
            ->assertSee('Medio')
            ->assertSeeInOrder(['Te faltan', '3', 'para el 12 de oro'])
            ->assertSee('Con 40 ganadas pasás a avanzado')
            // Tiene seis cartas para elegir y ocho boca abajo.
            ->assertSee('value="11-espada"', false)
            ->assertDontSee('value="12-oro"', false);
    }

    public function test_de_la_otra_persona_la_mesa_recibe_solo_apodo_categoria_carta_y_dorso(): void
    {
        $uno = Jugador::factory()->create(['apodo' => 'El Tano', 'email' => 'tano@example.com']);
        $dos = Jugador::factory()->create(['apodo' => 'La Tana', 'email' => 'tana@example.com']);

        $mesa = $this->app->make(Mesa::class);
        $sala = $mesa->crearSala($uno);
        $mesa->sentarse($sala->codigo, $dos);

        // La Tana ganó doce partidas, eligió el 11 y el dorso de fósforos, y declaró que juega hace años.
        $this->anotarle($dos, str_repeat('G', 12));
        // Las partidas que deja anotarle quedan abiertas: se cierran, así la de la sala es la que está en curso.
        Partida::query()->where('jugador_id', $dos->id)->update(['estado' => Partida::TERMINADA]);
        $this->pasoElEscalon($dos, 3);
        $dos->forceFill(['carta' => '11-espada', 'dorso' => 'fosforos', 'experiencia' => Experiencia::DeAnios])->save();

        $respuesta = $this->actingAs($uno)->get('/mesa')->assertOk();

        $this->assertSame(
            ['apodo' => 'La Tana', 'categoria' => 'Medio', 'carta' => '11-espada', 'dorso' => 'fosforos'],
            $respuesta->viewData('socio'),
        );

        // Sus cartas se dibujan con el dorso que eligió, y su categoría va junto al apodo.
        $respuesta->assertSee('dorso-fosforos')->assertSee('Medio');
        // De su cuenta no viaja nada más.
        $respuesta->assertDontSee('tana@example.com')->assertDontSee('experiencia');

        // Y ella ve al Tano con lo de fábrica: no eligió nada.
        $this->assertSame(
            ['apodo' => 'El Tano', 'categoria' => 'Novato', 'carta' => Premios::CARTA_DE_FABRICA, 'dorso' => Premios::DORSO_DE_FABRICA],
            $this->actingAs($dos)->get('/mesa')->viewData('socio'),
        );
    }

    public function test_contra_el_bot_la_mesa_no_recibe_el_perfil_de_nadie(): void
    {
        Queue::fake();
        $jugador = Jugador::factory()->create();

        $this->actingAs($jugador)->post('/jugar');

        $this->assertNull($this->actingAs($jugador)->get('/mesa')->assertOk()->viewData('socio'));
    }

    public function test_la_ayuda_del_tanto_la_ve_solo_quien_la_pidio_y_no_cambia_lo_que_se_puede_jugar(): void
    {
        $uno = Jugador::factory()->create();
        $dos = Jugador::factory()->create();

        $mesa = $this->app->make(Mesa::class);
        $sala = $mesa->crearSala($uno);
        $partida = $mesa->sentarse($sala->codigo, $dos);

        $sinAyuda = [$mesa->vista($partida->fresh(), 0), $mesa->vista($partida->fresh(), 1)];
        $this->assertArrayNotHasKey('ayudas', $sinAyuda[0]);
        $this->assertArrayNotHasKey('ayudas', $sinAyuda[1]);

        // El que abrió la sala declara que está aprendiendo.
        $uno->forceFill(['experiencia' => Experiencia::Aprendiendo])->save();

        $suya = $mesa->vista($partida->fresh(), 0);
        $delRival = $mesa->vista($partida->fresh(), 1);

        // Ve su tanto, que es el de sus tres cartas.
        $this->assertSame(Tanto::deEnvido(array_map(Carta::de(...), $suya['misCartas'])), $suya['ayudas']['tanto']);

        // Fuera de eso su vista es la misma: las mismas cartas y las mismas jugadas permitidas.
        unset($suya['ayudas']);
        $this->assertEquals($this->sinElReloj($sinAyuda[0]), $this->sinElReloj($suya));

        // Y al rival no le llega nada distinto: ni la ayuda ni el tanto del otro.
        $this->assertArrayNotHasKey('ayudas', $delRival);
        $this->assertEquals($this->sinElReloj($sinAyuda[1]), $this->sinElReloj($delRival));
    }

    public function test_lo_que_se_declara_cambia_el_bot_contra_el_que_se_entra_sin_elegir(): void
    {
        Queue::fake();

        foreach ([[null, Nivel::Intermedio], [Experiencia::Aprendiendo, Nivel::Facil], [Experiencia::Seguido, Nivel::Intermedio], [Experiencia::DeAnios, Nivel::Dificil]] as [$experiencia, $nivel]) {
            $jugador = Jugador::factory()->create();

            if ($experiencia !== null) {
                $this->actingAs($jugador)->put('/perfil/experiencia', ['experiencia' => $experiencia->value])->assertRedirect('/perfil');
                $this->assertSame($experiencia, $jugador->fresh()->experiencia);
            }

            // El botón de la portada no manda nivel.
            $this->actingAs($jugador->fresh())->post('/jugar')->assertRedirect('/mesa');

            $this->assertSame($nivel, Partida::query()->where('jugador_id', $jugador->id)->sole()->nivel_bot);
        }
    }

    public function test_eligiendo_un_nivel_en_los_modos_lo_declarado_no_pesa(): void
    {
        Queue::fake();
        $jugador = Jugador::factory()->create();
        $jugador->forceFill(['experiencia' => Experiencia::Aprendiendo])->save();

        $this->actingAs($jugador)->post('/jugar', ['nivel' => Nivel::UltraDificil->value]);

        $this->assertSame(Nivel::UltraDificil, Partida::query()->where('jugador_id', $jugador->id)->sole()->nivel_bot);
    }

    public function test_no_se_declara_cualquier_cosa(): void
    {
        $jugador = Jugador::factory()->create();

        $this->actingAs($jugador)->put('/perfil/experiencia', ['experiencia' => 9])->assertSessionHasErrors('experiencia');
        $this->assertNull($jugador->fresh()->experiencia);
    }

    public function test_en_el_torneo_la_persona_juega_con_su_carta_y_ningun_bot_la_repite(): void
    {
        $jugador = Jugador::factory()->create();
        // Con cuarenta ganadas tiene el 7 de oro, que es también la carta de Doña Elvira.
        $this->anotarle($jugador, str_repeat('G', 40));
        $jugador->forceFill(['carta' => '7-oro'])->save();

        $torneo = $this->app->make(Torneos::class)->crear($jugador->fresh(), 8, 3);
        $cartas = array_column($torneo->inscriptos, 'carta');

        $this->assertSame('7-oro', $torneo->inscriptos[$torneo->lugarDeLaPersona()]['carta']);
        $this->assertCount(8, array_unique($cartas));
        $this->assertNotContains('Doña Elvira', array_column($torneo->inscriptos, 'apodo'));
        $this->assertSame('7-oro', JugadoresDeEjemplo::cartas()['Doña Elvira']);
    }

    public function test_el_ranking_dice_la_categoria_y_la_carta_de_cada_uno(): void
    {
        $novato = Jugador::factory()->create(['apodo' => 'El Nuevo']);
        $medio = Jugador::factory()->create(['apodo' => 'La Fija']);
        $bot = Jugador::factory()->deEjemplo()->create(['apodo' => 'Don Anselmo']);

        $this->anotarle($novato, 'GG');
        $this->anotarle($medio, str_repeat('G', 14));
        $this->anotarle($bot, str_repeat('G', 5));
        $medio->forceFill(['carta' => '12-oro'])->save();
        // Una carta que todavía no ganó no aparece: se ve la de fábrica.
        $novato->forceFill(['carta' => '1-espada'])->save();

        $filas = collect((new Ranking)->para()['filas'])->keyBy('apodo');

        $this->assertSame(['Medio', '12-oro'], [$filas['La Fija']['categoria'], $filas['La Fija']['carta']]);
        $this->assertSame(['Novato', Premios::CARTA_DE_FABRICA], [$filas['El Nuevo']['categoria'], $filas['El Nuevo']['carta']]);
        // Un jugador de ejemplo tiene su carta fija, la de su nivel.
        $this->assertSame('1-espada', $filas['Don Anselmo']['carta']);

        $this->get('/ranking')->assertOk()->assertSee('La Fija')->assertSee('medio');
    }

    private function pasoElEscalon(Jugador $jugador, int $escalon): void
    {
        (new Progreso)->forceFill(['jugador_id' => $jugador->id, 'clave' => Progresos::claveDeEscalon($escalon), 'intentos' => 1, 'superado_en' => now()])->save();
    }

    /**
     * Una vista sin lo que cambia con el paso de los segundos: lo que falta para que venza el turno.
     *
     * @param  array<string, mixed>  $vista
     * @return array<string, mixed>
     */
    private function sinElReloj(array $vista): array
    {
        unset($vista['restan']);

        return $vista;
    }
}
