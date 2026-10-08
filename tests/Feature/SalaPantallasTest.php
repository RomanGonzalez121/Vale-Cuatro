<?php

namespace Tests\Feature;

use App\Juego\Mesa;
use App\Models\Jugador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lo que se ve en la sala de espera, en la invitación, en la mesa con otra persona y en los modos.
 */
class SalaPantallasTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_sala_muestra_el_link_para_copiar_y_lo_que_se_puede_hacer(): void
    {
        $creador = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($creador);

        $this->actingAs($creador)->get(route('sala', $sala->codigo))
            ->assertOk()
            ->assertSee('Invitá a alguien')
            ->assertSee('Copiar link')
            ->assertSee('Cancelar sala')
            ->assertSee('Esperando que alguien se siente.')
            ->assertSee('value="'.route('invitacion', $sala->codigo).'"', false)
            ->assertSee('Si no se sienta nadie en 30 minutos, la sala se cierra sola.');
    }

    public function test_la_sala_pide_si_ya_se_sento_alguien_a_su_propia_ruta(): void
    {
        $creador = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($creador);

        // La ruta va dentro del JavaScript de la página, donde las barras salen escapadas.
        $this->actingAs($creador)->get(route('sala', $sala->codigo))
            ->assertSee(trim(json_encode(route('sala.estado', $sala->codigo)), '"'), false);
    }

    public function test_la_invitacion_dice_quien_invita_y_ofrece_sentarse_sin_crear_ningun_jugador(): void
    {
        $creador = Jugador::factory()->invitado()->create(['apodo' => 'Tana']);
        $sala = $this->mesa()->crearSala($creador);

        $this->get(route('invitacion', $sala->codigo))
            ->assertOk()
            ->assertSee('Tana te invita a jugar')
            ->assertSee('Sentarme')
            ->assertSee('Entrás sin registrarte');

        // Mirar el link no fabrica nada: el jugador se crea recién al sentarse.
        $this->assertGuest();
        $this->assertSame(1, Jugador::count());
    }

    public function test_la_invitacion_no_regala_datos_del_creador_ni_deja_pasar_html_en_su_apodo(): void
    {
        $creador = Jugador::factory()->create(['apodo' => '<b>Zurdo</b>', 'email' => 'secreto@valecuatro.test']);
        $sala = $this->mesa()->crearSala($creador);

        $respuesta = $this->get(route('invitacion', $sala->codigo))->assertOk();

        $respuesta->assertDontSee('<b>Zurdo</b>', false);
        $respuesta->assertSee('&lt;b&gt;Zurdo&lt;/b&gt; te invita a jugar', false);
        $respuesta->assertDontSee('secreto@valecuatro.test');
        $respuesta->assertDontSee($creador->email);
    }

    public function test_con_la_sala_llena_o_cerrada_la_invitacion_lo_dice_y_no_ofrece_sentarse(): void
    {
        $llena = $this->mesa()->crearSala(Jugador::factory()->invitado()->create());
        $this->mesa()->sentarse($llena->codigo, Jugador::factory()->invitado()->create());

        $this->get(route('invitacion', $llena->codigo))
            ->assertOk()
            ->assertSee('Esa partida ya tiene sus dos jugadores.')
            ->assertDontSee('Sentarme')
            ->assertSee('Jugar contra el bot')
            ->assertSee('Ver otros modos');

        $cerrada = $this->mesa()->crearSala(Jugador::factory()->invitado()->create());
        $this->mesa()->cancelarSala($cerrada);

        $this->get(route('invitacion', $cerrada->codigo))
            ->assertOk()
            ->assertSee('Esa partida ya terminó.')
            ->assertDontSee('Sentarme');
    }

    public function test_si_no_pudo_sentarse_por_otra_partida_se_lo_dice_y_puede_volver_a_intentar(): void
    {
        $ocupado = Jugador::factory()->invitado()->create();
        $this->mesa()->abrir($ocupado);
        $sala = $this->mesa()->crearSala(Jugador::factory()->invitado()->create());

        $this->actingAs($ocupado)->followingRedirects()->post(route('invitacion.entrar', $sala->codigo))
            ->assertOk()
            ->assertSee('Tenés otra partida sin terminar.')
            ->assertSee('Sentarme');
    }

    public function test_la_mesa_con_otra_persona_muestra_su_apodo_y_no_un_bot(): void
    {
        $creador = Jugador::factory()->invitado()->create(['apodo' => 'Tana']);
        $rival = Jugador::factory()->invitado()->create(['apodo' => 'Zurdo']);
        $sala = $this->mesa()->crearSala($creador);
        $this->mesa()->sentarse($sala->codigo, $rival);

        // Cada uno ve el nombre del otro.
        $this->actingAs($creador)->get('/mesa')->assertOk()->assertSee('Mesa contra Zurdo')->assertDontSee('Bot ');
        $this->actingAs($rival)->get('/mesa')->assertOk()->assertSee('Mesa contra Tana')->assertDontSee('Bot ');
    }

    public function test_un_tercero_no_ve_la_mesa_de_otros_dos(): void
    {
        $sala = $this->mesa()->crearSala(Jugador::factory()->invitado()->create());
        $this->mesa()->sentarse($sala->codigo, Jugador::factory()->invitado()->create());

        $this->actingAs(Jugador::factory()->invitado()->create())->get('/mesa')->assertRedirect(route('modos'));
    }

    public function test_los_modos_ofrecen_invitar_a_jugar_y_ese_boton_va_a_abrir_una_sala(): void
    {
        $this->get('/modos')
            ->assertOk()
            ->assertSee('Invitar a jugar')
            ->assertSee('action="'.route('invitar').'"', false)
            ->assertSee('action="'.route('jugar').'"', false);
    }

    public function test_con_una_sala_esperando_los_modos_ofrecen_volver_a_ella_o_cancelarla(): void
    {
        $creador = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($creador);

        // Los niveles quedan apagados: la pantalla dice por qué y deja cancelar la sala ahí mismo.
        $this->actingAs($creador)->get('/modos')
            ->assertOk()
            ->assertSee('Tenés una sala abierta esperando rival. Para jugar contra el bot o en otro modo, primero cancelala.')
            ->assertSee('Volver a la sala')
            ->assertSee('Cancelar sala')
            ->assertSee('action="'.route('sala.cancelar', $sala->codigo).'"', false);
    }

    public function test_cancelar_la_sala_desde_los_modos_deja_elegir_nivel_otra_vez(): void
    {
        $creador = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($creador);

        $this->actingAs($creador)->post(route('sala.cancelar', $sala->codigo))->assertRedirect(route('modos'));

        $this->actingAs($creador)->get('/modos')
            ->assertOk()
            ->assertDontSee('Cancelar sala')
            ->assertDontSee('Volver a la sala')
            ->assertSee('Jugar contra el bot');
    }

    public function test_con_una_partida_en_curso_con_otra_persona_los_modos_ofrecen_seguirla(): void
    {
        $creador = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($creador);
        $this->mesa()->sentarse($sala->codigo, Jugador::factory()->invitado()->create());

        $this->actingAs($creador)->get('/modos')
            ->assertOk()
            ->assertSee('Tenés una partida sin terminar con otra persona.')
            ->assertSee('Seguir la partida');
    }

    private function mesa(): Mesa
    {
        return $this->app->make(Mesa::class);
    }
}
