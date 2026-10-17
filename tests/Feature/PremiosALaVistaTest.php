<?php

namespace Tests\Feature;

use App\Juego\Mesa;
use App\Juego\Premios;
use App\Models\Jugador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los premios a la vista (M20c): qué piezas le da una partida a cada uno, venga del nivel, de la gira o de un logro;
 * cómo las muestra el final; y qué queda como nuevo en la Colección hasta que se ve.
 */
class PremiosALaVistaTest extends TestCase
{
    use JugandoPartidas, PartidasCortas, RefreshDatabase;

    public function test_una_partida_que_sube_de_nivel_guarda_lo_que_dio_y_el_final_lo_pide_aparte(): void
    {
        $uno = Jugador::factory()->create();
        $dos = Jugador::factory()->create();
        $uno->forceFill(['fosforos' => 28])->save();

        $partida = $this->partidaCorta($uno, $dos);
        $this->ganar($partida, Mesa::JUGADOR);

        // Del nivel 3 al 4: el 5 de espada y el Cajón del mercado.
        $this->assertSame(['carta:5-espada', 'mesa:cajon'], $partida->fresh()->premios_nuevos[$uno->id]);
        $this->assertArrayNotHasKey($dos->id, $partida->fresh()->premios_nuevos ?? []);

        $paso = collect($this->laMesaDeVerdad()->pasosDesde($partida->fresh(), 0, Mesa::JUGADOR))->last();
        $this->assertSame([
            ['tipo' => 'carta', 'clave' => '5-espada', 'nombre' => 'El 5 de espada'],
            ['tipo' => 'mesa', 'clave' => 'cajon', 'nombre' => 'Cajón del mercado'],
        ], $paso['experiencia']['premios']);
        $this->assertSame(route('mesa.premios', $partida), $paso['experiencia']['premiosEn']);

        // Las cartas llegan boca abajo y cada una lleva a su pieza en la Colección.
        $this->actingAs($uno)->get(route('mesa.premios', $partida))->assertOk()
            ->assertSee('data-vuelta="false"', false)
            ->assertSee(route('perfil', ['pieza' => 'mesa:cajon']), false)
            ->assertSee('Cajón del mercado');

        // Quien perdió no subió: no tiene premios que ver, ni los del otro.
        $paso = collect($this->laMesaDeVerdad()->pasosDesde($partida->fresh(), 0, 1))->last();
        $this->assertSame([], $paso['experiencia']['premios']);
        $this->assertNull($paso['experiencia']['premiosEn']);
        $this->actingAs($dos)->get(route('mesa.premios', $partida))->assertOk()->assertDontSee('data-premio', false);
        $this->actingAs(Jugador::factory()->create())->get(route('mesa.premios', $partida))->assertNotFound();
    }

    public function test_quien_juega_sin_cuenta_ve_sus_premios_y_el_link_lleva_a_crearla(): void
    {
        $invitado = Jugador::factory()->invitado()->create();
        $invitado->forceFill(['fosforos' => 28])->save();
        $otro = Jugador::factory()->create();

        $partida = $this->partidaCorta($invitado, $otro);
        $this->ganar($partida, Mesa::JUGADOR);

        $paso = collect($this->laMesaDeVerdad()->pasosDesde($partida->fresh(), 0, Mesa::JUGADOR))->last();
        $this->assertTrue($paso['experiencia']['sinCuenta']);
        $this->assertCount(2, $paso['experiencia']['premios']);

        $this->actingAs($invitado)->get(route('mesa.premios', $partida))->assertOk()
            ->assertSee('href="'.route('registro').'"', false)
            ->assertDontSee('pieza=', false);
    }

    public function test_quien_juega_sin_cuenta_junta_lo_nuevo_de_varias_partidas_para_cuando_la_cree(): void
    {
        $invitado = Jugador::factory()->invitado()->create();
        $premios = new Premios;

        $antes = $premios->propias($invitado);
        $invitado->forceFill(['fosforos' => 10])->save();
        $premios->anotarNuevas($invitado->fresh(), $antes);

        $antes = $premios->propias($invitado->fresh());
        $invitado->forceFill(['fosforos' => 20])->save();
        $premios->anotarNuevas($invitado->fresh(), $antes);

        // Sin cuenta no se le marca nada; lo juntado queda para cuando la cree.
        $this->assertSame([], $premios->nuevas($invitado->fresh()));
        $this->assertSame(['distintivo:un-fosforo', 'mesa:chapa', 'dorso:rombos', 'mesa:adoquines'], $invitado->fresh()->piezas_nuevas);
    }

    public function test_un_titulo_que_destraba_un_logro_tambien_es_un_premio_nuevo(): void
    {
        $jugador = Jugador::factory()->create();
        $premios = new Premios;
        $antes = $premios->propias($jugador);

        // El título Tantero pide 25 envidos ganados: con eso se gana, sin subir de nivel.
        $jugador->forceFill(['envidos_ganados' => 25])->save();

        $this->assertSame(['titulo:tantero'], $premios->anotarNuevas($jugador->fresh(), $antes));
        $this->assertSame(['titulo:tantero'], $premios->nuevas($jugador->fresh()));
        $this->assertSame([['tipo' => 'titulo', 'clave' => 'tantero', 'nombre' => 'Tantera']], Premios::fichasDe(['titulo:tantero'], femenino: true));
    }

    public function test_el_final_muestra_primero_los_titulos_y_los_cuatro_palos_van_juntos(): void
    {
        $fichas = Premios::fichasDe(['mesa:chapa', 'distintivo:palo-oro', 'distintivo:palo-copa', 'distintivo:palo-espada', 'distintivo:palo-basto', 'titulo:truquero', 'mesa:no-existe']);

        $this->assertSame(['titulo', 'mesa', 'distintivo'], array_column($fichas, 'tipo'));
        $this->assertSame(['tipo' => 'distintivo', 'clave' => 'palo-oro', 'nombre' => 'Los cuatro palos'], $fichas[2]);
    }

    public function test_una_cuenta_de_antes_arranca_con_lo_que_le_faltaba_ver_del_camino(): void
    {
        // Nivel 4 y miró su perfil por última vez en el 3: le falta ver lo del nivel 4.
        $jugador = Jugador::factory()->create();
        $jugador->forceFill(['fosforos' => 30, 'nivel_visto' => 3])->save();

        $this->assertSame(['carta:5-espada', 'mesa:cajon'], (new Premios)->nuevas($jugador->fresh()));
    }

    public function test_ver_una_pestana_apaga_solo_lo_nuevo_de_ese_tipo(): void
    {
        $jugador = Jugador::factory()->create();
        $jugador->forceFill(['piezas_nuevas' => ['carta:5-espada', 'mesa:cajon', 'mesa:chapa']])->save();
        $premios = new Premios;

        $premios->marcarVistas($jugador->fresh(), 'mesa');
        $this->assertSame(['carta:5-espada'], $premios->nuevas($jugador->fresh()));

        $this->actingAs($jugador->fresh())->post('/perfil/visto', ['tipo' => 'carta'])->assertNoContent();
        $this->assertFalse($premios->hayNovedad($jugador->fresh()));

        $this->actingAs($jugador->fresh())->postJson('/perfil/visto', ['tipo' => 'otra-cosa'])->assertUnprocessable();
    }

    public function test_el_link_de_un_premio_abre_la_coleccion_en_su_pestana_y_su_pieza(): void
    {
        $jugador = Jugador::factory()->create();
        $jugador->forceFill(['fosforos' => 30, 'piezas_nuevas' => ['carta:5-espada', 'mesa:cajon']])->save();

        $this->actingAs($jugador->fresh())->get('/perfil?pieza=mesa:cajon')->assertOk()
            ->assertSee("pestana: 'mesas'", false)
            ->assertSee("abrir: 'mesa:cajon'", false)
            ->assertSee('data-pieza="mesa:cajon"', false)
            ->assertSee('Nuevo');

        // Abrir la pestaña de las mesas las dio por vistas; la carta sigue nueva.
        $this->assertSame(['carta:5-espada'], (new Premios)->nuevas($jugador->fresh()));

        // Algo que no es una pieza no abre nada.
        $this->actingAs($jugador->fresh())->get('/perfil?pieza=<script>')->assertOk()->assertSee('abrir: null', false);
    }

    public function test_la_administracion_y_quien_juega_sin_cuenta_no_tienen_novedades(): void
    {
        $administracion = Jugador::factory()->create(['es_administrador' => true]);
        $administracion->forceFill(['fosforos' => 300])->save();

        $this->assertSame([], (new Premios)->nuevas($administracion->fresh()));
        $this->assertSame([], (new Premios)->nuevas(null));
    }
}
