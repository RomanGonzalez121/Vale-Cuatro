<?php

namespace Tests\Feature;

use App\Models\Jugador;
use App\View\Components\Carta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaginasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function rutas(): array
    {
        return [
            'portada' => ['/'],
            'ranking' => ['/ranking'],
            'historial' => ['/historial'],
            'cómo se juega' => ['/como-se-juega'],
            'identidad' => ['/identidad'],
            'modos de juego' => ['/modos'],
        ];
    }

    #[DataProvider('rutas')]
    public function test_cada_pantalla_responde(string $ruta): void
    {
        $this->get($ruta)->assertOk()->assertSee('Vale Cuatro');
    }

    public function test_la_identidad_muestra_las_cuarenta_cartas(): void
    {
        $html = $this->get('/identidad')->assertOk()->getContent();

        foreach (Carta::mazo() as [$palo, $numero]) {
            $this->assertStringContainsString("data-carta=\"{$numero}-{$palo}\"", $html, "Falta el {$numero} de {$palo}.");
        }
    }

    public function test_la_identidad_muestra_los_iconos_propios(): void
    {
        $respuesta = $this->get('/identidad')->assertOk();

        foreach (['Espada', 'Basto', 'Oro', 'Copa', 'Envido', 'Truco', 'Irse al mazo', 'Quién es mano', 'Repartir', 'Quiero', 'No quiero', 'Tiempo', 'Bot', 'Invitar', 'Ranking', 'Repetir partida', 'Sonido'] as $icono) {
            $respuesta->assertSee($icono);
        }
    }

    public function test_en_la_portada_un_solo_boton_entra_a_la_mesa_y_los_demas_modos_van_a_su_pantalla(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // El botón que decía "Invitar a alguien" entraba a la mesa contra el bot: ahora es un link a los modos.
        $this->assertSame(1, preg_match_all('/<button type="submit"/', $html));
        $this->assertStringNotContainsString('Invitar a alguien', $html);
        $this->assertStringContainsString('href="'.route('modos').'"', $html);
    }

    public function test_lo_unico_que_acompana_el_scroll_es_la_barra_de_abajo(): void
    {
        foreach (['/', '/ranking', '/historial', '/como-se-juega', '/modos'] as $ruta) {
            $html = $this->get($ruta)->assertOk()->getContent();

            $this->assertSame(0, preg_match('/class="[^"]*\b(sticky|fixed)\b/', $html), "En {$ruta} hay algo pegado o fijo.");
            $this->assertSame(1, substr_count($html, 'class="barra-inferior'), "En {$ruta} falta la barra de abajo.");
        }
    }

    public function test_la_barra_de_abajo_marca_la_pagina_actual_y_no_lleva_la_cuenta(): void
    {
        $html = $this->get('/ranking')->assertOk()->getContent();
        $barra = substr($html, strpos($html, 'class="barra-inferior'));
        $barra = substr($barra, 0, strpos($barra, '</nav>'));

        $this->assertSame(4, substr_count($barra, 'class="barra-lugar"'));
        $this->assertSame(1, substr_count($barra, 'aria-current="page"'));
        $this->assertMatchesRegularExpression('/href="[^"]*\/ranking"[^>]*aria-current="page"/', $barra);
        $this->assertStringNotContainsString('Ingresar', $barra);
    }

    public function test_donde_se_escribe_o_se_juega_no_hay_barra_de_abajo(): void
    {
        foreach (['/ingresar', '/registro'] as $ruta) {
            $this->assertStringNotContainsString('barra-inferior', $this->get($ruta)->assertOk()->getContent());
        }

        $jugador = Jugador::factory()->create();
        $this->actingAs($jugador)->post('/jugar');

        $this->assertStringNotContainsString('barra-inferior', $this->actingAs($jugador)->get('/mesa')->assertOk()->getContent());
        $this->assertStringContainsString('barra-inferior', $this->actingAs($jugador)->get('/perfil')->assertOk()->getContent());
    }
}
