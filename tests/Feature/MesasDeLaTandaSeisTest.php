<?php

namespace Tests\Feature;

use App\Juego\Mesa;
use App\Juego\Premios;
use App\Models\Jugador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las mesas de la tanda 6 traen lo suyo a la mesa de juego: la de grafito, una placa con el apodo de su dueño; la
 * fileteada, su leyenda. Las ve solo quien las ganó y las eligió.
 */
class MesasDeLaTandaSeisTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_seis_mesas_estan_en_el_camino_y_dibujadas(): void
    {
        foreach (['cuero' => 32, 'pulperia' => 34, 'cobre' => 36, 'art-deco' => 38, 'grafito' => 39, 'fileteado' => 40] as $mesa => $nivel) {
            $this->assertSame($nivel, Premios::POR_NIVEL['mesa'][$mesa]['nivel'], $mesa);
            $this->assertStringContainsString(".mesa-de-{$mesa} .mesa-campo::before", file_get_contents(resource_path('css/app.css')), $mesa);
        }
    }

    public function test_la_mesa_fileteada_no_se_usa_antes_del_nivel_cuarenta(): void
    {
        $jugador = Jugador::factory()->create();
        $jugador->forceFill(['fosforos' => 38 * 10])->save();

        $this->actingAs($jugador)->put('/perfil/mesa', ['mesa' => 'fileteado'])->assertSessionHasErrors('mesa');
        $this->actingAs($jugador)->put('/perfil/mesa', ['mesa' => 'grafito'])->assertSessionHasNoErrors();
        $this->assertSame('grafito', $jugador->fresh()->mesa);

        // La administración las tiene todas.
        $administracion = Jugador::factory()->administrador()->create();
        $this->actingAs($administracion)->put('/perfil/mesa', ['mesa' => 'fileteado'])->assertSessionHasNoErrors();
    }

    public function test_la_mesa_de_grafito_lleva_la_placa_con_el_apodo_y_solo_la_ve_su_duenio(): void
    {
        $uno = Jugador::factory()->create(['apodo' => 'El Tano']);
        $dos = Jugador::factory()->create(['apodo' => 'La Tana']);
        $uno->forceFill(['fosforos' => 40 * 10, 'mesa' => 'grafito'])->save();

        $mesa = $this->app->make(Mesa::class);
        $sala = $mesa->crearSala($uno);
        $mesa->sentarse($sala->codigo, $dos);

        $this->actingAs($uno)->get('/mesa')->assertOk()
            ->assertSee('mesa-de-grafito')->assertSee('placa-de-laton')->assertSeeInOrder(['La mesa de', 'El Tano']);

        // La Tana juega en su paño de fábrica: ni la mesa ni la placa del Tano llegan a su pantalla.
        $this->actingAs($dos)->get('/mesa')->assertOk()
            ->assertDontSee('mesa-de-grafito')->assertDontSee('placa-de-laton');
    }

    public function test_la_mesa_fileteada_lleva_su_leyenda_y_las_otras_no(): void
    {
        $jugador = Jugador::factory()->create();
        $jugador->forceFill(['fosforos' => 40 * 10, 'mesa' => 'fileteado'])->save();

        $this->actingAs($jugador)->post('/jugar')->assertRedirect('/mesa');
        $this->actingAs($jugador)->get('/mesa')->assertOk()
            ->assertSee('leyenda-filete')->assertSee('Quiero vale cuatro')->assertDontSee('placa-de-laton');

        $jugador->forceFill(['mesa' => 'cuero'])->save();
        $this->actingAs($jugador)->get('/mesa')->assertOk()->assertSee('mesa-de-cuero')->assertDontSee('leyenda-filete');
    }
}
