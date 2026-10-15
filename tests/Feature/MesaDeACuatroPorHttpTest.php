<?php

namespace Tests\Feature;

use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Juego\Senas;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Motor\Accion;
use App\Motor\TipoDeAccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Lo que el navegador le pide a la mesa de a cuatro: entrar, verla, hacerle una seña al compañero y la revancha.
 * Lo que pasa adentro de la partida (los bots, a quién le llega cada seña) está en MesaDeACuatroTest.
 */
class MesaDeACuatroPorHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_jugar_de_a_cuatro_abre_la_partida_y_la_mesa_en_cruz(): void
    {
        Queue::fake();

        $this->post('/jugar', ['asientos' => 4, 'nivel' => 3, 'serie' => 1])->assertRedirect('/mesa');

        $partida = Partida::sole();

        $this->assertTrue($partida->deACuatro());
        $this->assertSame(Nivel::Dificil, $partida->nivel_bot);
        $this->assertNull($partida->serie_id);

        $html = $this->get('/mesa')->assertOk()
            ->assertViewIs('paginas.mesa-cuatro')
            ->assertSee('Mesa de a cuatro contra la máquina: tu compañero y los dos rivales, de nivel difícil')
            ->assertSee('Nosotros')
            ->assertSee('Ellos')
            ->getContent();

        // Un lugar en la cruz para cada uno, y las cartas de los otros tres boca abajo.
        foreach (['vos', 'derecha', 'companero', 'izquierda'] as $lugar) {
            $this->assertSame(1, substr_count($html, 'data-hueco="'.$lugar.'"'), $lugar);
        }

        foreach (['derecha', 'companero', 'izquierda'] as $lugar) {
            $this->assertSame(1, substr_count($html, 'data-dorsos="'.$lugar.'"'), $lugar);
        }

        // Las 40 cartas y los cuatro dorsos, para dibujar cualquier jugada sin volver al servidor.
        // Las 40 cartas y un solo dorso: el de quien mira.
        $this->assertSame(41, substr_count($html, '<template data-plantilla='));
        // El botón del final pide la revancha de esta partida.
        $this->assertMatchesRegularExpression('/<form[^>]*action="[^"]*\/revancha\/pedir"[^>]*>.*?name="partida" value="'.$partida->id.'".*?Jugar la revancha/s', $html);
    }

    public function test_solo_se_juega_de_a_dos_o_de_a_cuatro(): void
    {
        foreach ([1, 3, 6, 'cuatro', ['4']] as $asientos) {
            $this->post('/jugar', ['asientos' => $asientos])->assertSessionHasErrors('asientos');
        }

        $this->assertSame(0, Partida::count());

        $this->post('/jugar', ['asientos' => 2])->assertRedirect('/mesa');
        $this->assertFalse(Partida::sole()->deACuatro());
    }

    public function test_la_pagina_de_la_mesa_no_trae_las_cartas_de_los_otros_tres(): void
    {
        $jugador = Jugador::factory()->invitado()->create();
        $this->partidaArmada($jugador);

        $html = $this->actingAs($jugador)->get('/mesa')->assertOk()->getContent();

        // Lo que la página le pasa a la mesa es la vista del asiento de la persona: está en el atributo x-data.
        preg_match('/x-data="mesaDeCuatro\((.*?)\)"/s', $html, $datos);
        $datos = html_entity_decode($datos[1]);

        $this->assertStringContainsString('3-oro', $datos);

        // Ni las de su compañero: de él le llegan las señas, no las cartas.
        foreach (['2-oro', '6-basto', '5-basto', '7-oro', '6-oro', '4-basto', '2-basto', '6-copa', '10-oro'] as $ajena) {
            $this->assertStringNotContainsString('"'.$ajena.'"', $datos, "La página trae el {$ajena}, que no es de la persona.");
        }

        $this->assertStringContainsString(Senas::SIETE_DE_ORO, $datos);
    }

    public function test_una_sena_se_hace_desde_la_mesa_y_no_mueve_la_partida(): void
    {
        Queue::fake();

        $jugador = Jugador::factory()->invitado()->create();
        $partida = $this->partidaArmada($jugador);

        $paso = $this->actingAs($jugador)->postJson('/mesa/sena', ['sena' => Senas::ANCHO_DE_ESPADA, 'partida' => $partida->id])
            ->assertOk()
            ->assertJsonCount(1, 'pasos')
            ->json('pasos.0');

        $this->assertSame([Senas::ANCHO_DE_ESPADA], $paso['senas']['hechas']);
        $this->assertSame([], $paso['hechos']);
        $this->assertSame(1, $partida->eventos()->where('tipo', EventoDePartida::SENA)->count());

        // La misma otra vez, una de algo que no tiene y una que no existe: se rechazan con el motivo y no queda nada guardado.
        foreach ([Senas::ANCHO_DE_ESPADA, Senas::UN_DOS, 'un_cuatro', ['nada'], null] as $sena) {
            $this->actingAs($jugador)->postJson('/mesa/sena', ['sena' => $sena, 'partida' => $partida->id])
                ->assertStatus(422)
                ->assertJsonStructure(['motivo']);
        }

        $this->assertSame(1, $partida->eventos()->where('tipo', EventoDePartida::SENA)->count());
        // La partida sigue donde estaba: le toca a la persona, que es mano.
        $this->assertSame(0, $partida->eventos()->where('tipo', EventoDePartida::ACCION)->count());
    }

    public function test_nadie_le_hace_senas_a_la_partida_de_otro_ni_sin_sesion_ni_en_el_mano_a_mano(): void
    {
        Queue::fake();

        $partida = $this->partidaArmada(Jugador::factory()->invitado()->create());

        $this->postJson('/mesa/sena', ['sena' => Senas::ANCHO_DE_ESPADA, 'partida' => $partida->id])->assertUnauthorized();

        $otro = Jugador::factory()->invitado()->create();

        $this->actingAs($otro)->postJson('/mesa/sena', ['sena' => Senas::ANCHO_DE_ESPADA, 'partida' => $partida->id])->assertStatus(409);

        // En el mano a mano no hay compañero.
        $deADos = $this->app->make(Mesa::class)->abrir($otro, Nivel::Facil);

        $this->actingAs($otro)->postJson('/mesa/sena', ['sena' => Senas::NADA, 'partida' => $deADos->id])->assertStatus(422);
        $this->assertSame(0, EventoDePartida::query()->where('tipo', EventoDePartida::SENA)->count());
    }

    public function test_la_revancha_de_a_cuatro_es_otra_partida_de_a_cuatro_y_el_mano_pasa_al_asiento_siguiente(): void
    {
        $jugador = Jugador::factory()->invitado()->create();
        $partida = $this->terminada($jugador);

        // La revancha va a un punto y en ella sale primero un bot: si los bots jugaran solos podrían terminarla
        // antes de que el test la mire (alcanza con que los dos rivales se vayan al mazo). Acá no juegan.
        Queue::fake();

        $this->actingAs($jugador)->post('/revancha/pedir', ['partida' => $partida->id])->assertRedirect('/mesa');

        $revancha = Partida::query()->where('anterior_id', $partida->id)->sole();

        $this->assertTrue($revancha->deACuatro());
        $this->assertTrue($revancha->enCurso());
        $this->assertSame([1, $partida->puntos, $partida->nivel_bot], [$revancha->primer_mano, $revancha->puntos, $revancha->nivel_bot]);
        $this->actingAs($jugador)->get('/mesa')->assertOk()->assertViewIs('paginas.mesa-cuatro');
    }

    public function test_si_la_partida_de_a_cuatro_termino_al_volver_a_la_mesa_se_cuenta_como_quedo(): void
    {
        $jugador = Jugador::factory()->invitado()->create();
        $this->terminada($jugador);

        $this->actingAs($jugador)->get('/mesa')
            ->assertRedirect('/modos')
            ->assertSessionHas('aviso', fn (string $aviso) => preg_match('/^Tu última partida de a cuatro terminó \d+ a \d+: ganaron (ustedes|los rivales)\.$/u', $aviso) === 1);
    }

    public function test_abandonar_de_a_cuatro_le_da_la_partida_a_la_otra_pareja(): void
    {
        Queue::fake();

        $jugador = Jugador::factory()->invitado()->create();
        $partida = $this->partidaArmada($jugador);

        $this->actingAs($jugador)->post('/mesa/abandonar', ['partida' => $partida->id])->assertRedirect('/modos');

        $partida->refresh();

        $this->assertSame([Partida::ABANDONADA, 1], [$partida->estado, $partida->ganador]);
    }

    /**
     * Una partida de a cuatro con la persona de mano y cartas conocidas, guardada como la guardaría la mesa.
     * La persona tiene el ancho de espada y un tres; su compañero, el siete de oro y buen envido.
     */
    private function partidaArmada(Jugador $jugador, int $puntos = 30): Partida
    {
        $partida = Partida::create([
            'jugador_id' => $jugador->id,
            'primer_mano' => Mesa::JUGADOR,
            'puntos' => $puntos,
            'asientos' => 4,
            'nivel_bot' => Nivel::Intermedio,
        ]);

        $partida->eventos()->create([
            'numero' => 1,
            'tipo' => EventoDePartida::REPARTO,
            'datos' => ['manos' => [
                ['1-espada', '3-oro', '4-copa'],
                ['2-oro', '6-basto', '5-basto'],
                ['7-oro', '6-oro', '4-basto'],
                ['2-basto', '6-copa', '10-oro'],
            ]],
            'creado_en' => now(),
        ]);

        return $partida;
    }

    /**
     * Una partida de a cuatro a un punto, jugada hasta el final: la persona se va al mazo y los bots terminan la mano.
     */
    private function terminada(Jugador $jugador): Partida
    {
        $partida = $this->partidaArmada($jugador, puntos: 1);

        $this->app->make(Mesa::class)->actuar($partida, Accion::de(TipoDeAccion::Mazo));
        $partida->refresh();

        $this->assertSame(Partida::TERMINADA, $partida->estado);

        return $partida;
    }
}
