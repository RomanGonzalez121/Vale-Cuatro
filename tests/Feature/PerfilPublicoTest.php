<?php

namespace Tests\Feature;

use App\Juego\Numeros;
use App\Models\Jugador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El perfil rehecho (tanda 2): los números de la planilla y el perfil de otro, que se abre tocando su
 * apodo en el ranking y no muestra nada de su cuenta.
 */
class PerfilPublicoTest extends TestCase
{
    use AnotandoResultados, RefreshDatabase;

    public function test_los_numeros_salen_de_lo_que_anoto_el_ranking(): void
    {
        $jugador = Jugador::factory()->create();
        // Dos ganadas, una perdida, tres ganadas: racha de tres, la mejor también.
        $this->anotarle($jugador, 'GGPGGG', envidosJugados: 4, envidosGanados: 3);

        $numeros = (new Numeros)->de($jugador);

        $this->assertSame([6, 5, 83, 3, 3, 75], [$numeros['partidas'], $numeros['ganadas'], $numeros['porcentaje'], $numeros['racha'], $numeros['mejorRacha'], $numeros['envidos']]);

        $this->anotarle($jugador, 'P');
        $numeros = (new Numeros)->de($jugador);

        $this->assertSame([0, 3], [$numeros['racha'], $numeros['mejorRacha']]);
    }

    public function test_el_perfil_de_otro_muestra_su_mesa_y_sus_numeros_pero_nada_de_su_cuenta(): void
    {
        $tana = Jugador::factory()->create(['apodo' => 'La Tana', 'email' => 'tana@example.com']);
        $tana->forceFill(['fosforos' => 125, 'carta' => '10-basto'])->save();
        $this->anotarle($tana, 'GGP');

        $quienMira = Jugador::factory()->create();

        $this->actingAs($quienMira)->get(route('jugador', $tana))
            ->assertOk()
            ->assertSee('La Tana')
            ->assertSeeInOrder(['Medio', 'Nivel', '13'])
            ->assertSee('Últimas partidas')
            ->assertDontSee('tana@example.com')
            ->assertDontSee('Colección')
            ->assertDontSee('Cerrar sesión');
    }

    public function test_tambien_lo_ve_quien_no_entro_y_los_de_ejemplo_dicen_que_son_la_maquina(): void
    {
        $zurdo = Jugador::factory()->deEjemplo()->create(['apodo' => 'El Zurdo Medina']);

        $this->get(route('jugador', $zurdo))->assertOk()->assertSee('El Zurdo Medina')->assertSee('máquina');
    }

    public function test_quien_juega_sin_cuenta_y_la_administracion_no_tienen_perfil_publico(): void
    {
        $this->get(route('jugador', Jugador::factory()->invitado()->create()))->assertNotFound();
        $this->get(route('jugador', Jugador::factory()->administrador()->create()))->assertNotFound();
    }

    public function test_el_propio_perfil_publico_lleva_al_perfil(): void
    {
        $jugador = Jugador::factory()->create();

        $this->actingAs($jugador)->get(route('jugador', $jugador))->assertRedirect(route('perfil'));
    }

    public function test_el_ranking_lleva_al_perfil_de_cada_uno(): void
    {
        $tana = Jugador::factory()->create(['apodo' => 'La Tana']);
        $this->anotarle($tana, 'G');

        $this->get('/ranking')->assertSee('href="'.route('jugador', $tana).'"', false);
    }
}
