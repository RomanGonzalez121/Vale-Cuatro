<?php

namespace Tests\Feature;

use App\Juego\Modos;
use App\View\Components\Carta;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * La pantalla donde se elige el modo de juego. Hoy es una maqueta: solo se juega contra el bot
 * y los demás modos se muestran boca abajo hasta que se termine su módulo.
 */
class ModosTest extends TestCase
{
    public function test_la_pantalla_muestra_todos_los_modos_con_su_nombre(): void
    {
        $respuesta = $this->get('/modos')->assertOk();

        foreach (Modos::todos() as $modo) {
            $respuesta->assertSee($modo['nombre'])->assertSee($modo['resumen']);
        }
    }

    public function test_solo_los_modos_disponibles_tienen_boton_para_jugar(): void
    {
        $html = $this->get('/modos')->assertOk()->getContent();
        $disponibles = array_filter(Modos::todos(), fn (array $modo) => $modo['disponible']);

        // Un formulario hacia la mesa por cada modo que se juega, y ninguno más: el menú ya no entra directo.
        $this->assertSame(count($disponibles), preg_match_all('/<form[^>]*action="[^"]*\/jugar"/', $html));

        foreach ($disponibles as $modo) {
            $this->assertStringContainsString($modo['boton'], $html);
        }
    }

    public function test_los_modos_que_faltan_estan_dichos_y_van_boca_abajo(): void
    {
        $html = $this->get('/modos')->assertOk()->getContent();
        $faltan = count(array_filter(Modos::todos(), fn (array $modo) => ! $modo['disponible']));

        $this->assertSame($faltan, substr_count($html, 'Todavía no se juega'));
        $this->assertSame(count(Modos::todos()) - $faltan, substr_count($html, 'Se juega ahora'));

        // En la mano se dibuja cara arriba solo la carta de cada modo que se juega.
        preg_match('/<div class="mano-modos.*?<ul/s', $html, $mano);
        $this->assertSame(count(Modos::todos()) - $faltan, substr_count($mano[0], 'data-carta='));
        $this->assertSame(count(Modos::todos()), substr_count($mano[0], 'href="#dorso"'));
    }

    public function test_entra_elegido_el_primer_modo_y_los_demas_esperan_cerrados(): void
    {
        $html = $this->get('/modos')->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/class="enlace-menu fila-modo[^>]*aria-expanded="true"/s', $html));
        $this->assertSame(count(Modos::todos()) - 1, preg_match_all('/class="enlace-menu fila-modo[^>]*aria-expanded="false"/s', $html));
    }

    public function test_la_mano_es_un_espejo_que_no_recibe_el_foco_del_teclado(): void
    {
        $html = $this->get('/modos')->assertOk()->getContent();

        preg_match('/<div class="mano-modos.*?<ul/s', $html, $mano);

        $this->assertStringContainsString('aria-hidden="true"', $mano[0]);
        $this->assertSame(substr_count($mano[0], '<button'), substr_count($mano[0], 'tabindex="-1"'));
    }

    public function test_jugar_del_menu_lleva_a_elegir_el_modo_desde_cualquier_pantalla(): void
    {
        foreach (['/', '/ranking', '/historial', '/como-se-juega', '/identidad', '/modos'] as $ruta) {
            $html = $this->get($ruta)->assertOk()->getContent();

            // Una vez en el menú de escritorio y otra en el del celular.
            $this->assertSame(2, preg_match_all('/<a href="[^"]*\/modos"[^>]*>\s*(?:<svg.*?<\/svg>)?\s*Jugar\s*<\/a>/s', $html), "Falta \"Jugar\" en el menú de {$ruta}.");
        }
    }

    public function test_la_portada_sigue_entrando_a_la_mesa_en_un_solo_paso(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<form[^>]*action="[^"]*\/jugar"[^>]*>.*?Jugar contra el bot.*?<\/form>/s', $html);
    }

    public function test_el_catalogo_de_modos_esta_bien_armado(): void
    {
        $modos = Modos::todos();

        $this->assertCount(count($modos), array_unique(array_column($modos, 'clave')));
        $this->assertTrue($modos[0]['disponible'], 'El primer modo es el que entra elegido: tiene que poder jugarse.');

        foreach ($modos as $modo) {
            // La carta existe en el mazo y el ícono existe entre los propios: si no, cualquiera de los dos tira un error.
            new Carta(...$modo['carta']);
            Blade::render('<x-icono :nombre="$nombre" />', ['nombre' => $modo['icono']]);

            $this->assertSame($modo['disponible'], $modo['boton'] !== null, "{$modo['nombre']}: tiene botón si y solo si se juega.");
        }

        $this->assertCount(count($modos), array_unique(array_map(fn (array $modo) => implode('-', $modo['carta']), $modos)), 'Cada modo lleva una carta distinta.');
    }

    public function test_la_identidad_muestra_los_iconos_de_los_modos(): void
    {
        $respuesta = $this->get('/identidad')->assertOk();

        foreach (['De a cuatro', 'Torneo', 'Desafío', 'Escalera'] as $icono) {
            $respuesta->assertSee($icono);
        }
    }
}
