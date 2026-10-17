<?php

namespace Tests\Feature;

use App\Identidad\Distintivos;
use App\Identidad\Iconos;
use App\Juego\Camino;
use App\Juego\Nivel;
use App\Juego\Premios;
use App\Juego\Titulos;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Resultado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los premios de la tanda 3: los títulos (que salen de lo que pasó en la mesa), las mesas, las formas de anotar
 * y los distintivos. Ninguno se usa sin haberlo ganado, y la administración los tiene todos para probarlos.
 */
class TitulosTest extends TestCase
{
    use JugandoPartidas;
    use RefreshDatabase;

    public function test_las_cuentas_de_los_titulos_salen_de_las_partidas_y_se_rehacen_igual(): void
    {
        $jugador = Jugador::factory()->create();
        $titulos = new Titulos;

        foreach ([100, 101, 102] as $semilla) {
            [$partida] = $this->partidaContraElBot($jugador, Nivel::Intermedio, $semilla);
            $titulos->anotar($partida, $this->laMesa());
        }

        $jugador->refresh();

        // Envidos y flores cantados: los mismos que las jugadas guardadas de su asiento.
        $cantos = EventoDePartida::query()
            ->where('tipo', EventoDePartida::ACCION)->where('asiento', 0)
            ->whereIn('partida_id', Partida::query()->where('jugador_id', $jugador->id)->pluck('id'))
            ->get()->countBy(fn (EventoDePartida $evento) => $evento->datos['tipo']);

        $this->assertSame(($cantos['envido'] ?? 0) + ($cantos['real_envido'] ?? 0) + ($cantos['falta_envido'] ?? 0), $jugador->envidos_cantados);
        $this->assertSame($cantos['flor'] ?? 0, $jugador->flores_cantadas);
        // Envidos ganados: los mismos que cuenta el ranking.
        $this->assertSame((int) Resultado::query()->where('jugador_id', $jugador->id)->sum('envidos_ganados'), $jugador->envidos_ganados);
        $this->assertGreaterThan(0, $jugador->envidos_cantados + $jugador->trucos_ganados);

        $antes = $jugador->only(Titulos::CUENTAS);
        $titulos->recalcular($this->laMesa());

        $this->assertSame($antes, $jugador->fresh()->only(Titulos::CUENTAS));
    }

    public function test_un_titulo_se_gana_al_llegar_a_lo_que_pide_y_se_lee_como_se_eligio(): void
    {
        $jugador = Jugador::factory()->create(['envidos_cantados' => 24]);
        $premios = new Premios;

        $this->assertTrue(Titulos::gano($jugador, 'pichon'));
        $this->assertFalse(Titulos::gano($jugador, 'cantor'));

        $jugador->envidos_cantados = 25;
        $this->assertTrue(Titulos::gano($jugador, 'cantor'));

        $jugador->forceFill(['titulo' => 'cantor', 'titulo_femenino' => true]);
        $this->assertSame('Cantora', $premios->tituloDe($jugador));

        // Un título elegido que ya no tiene (las cuentas se rehicieron) vuelve a Pichón.
        $jugador->envidos_cantados = 3;
        $this->assertSame('Pichón', $premios->tituloDe($jugador));
    }

    public function test_titulo_mesa_anotar_y_distintivo_se_eligen_solo_si_se_ganaron_tambien_mandando_el_pedido_a_mano(): void
    {
        $jugador = Jugador::factory()->create(['fosforos' => 90]);   // Nivel 10.

        // La mesa de mármol es del 10; el pizarrón, del 14.
        $this->actingAs($jugador)->putJson('/perfil/mesa', ['mesa' => 'pizarron'])->assertUnprocessable();
        $this->actingAs($jugador)->putJson('/perfil/mesa', ['mesa' => 'marmol'])->assertOk()->assertExactJson(['mensaje' => 'Mesa elegida.']);
        // Palotes es del 7; tiza, del 13.
        $this->actingAs($jugador)->putJson('/perfil/anotar', ['anotar' => 'tiza'])->assertUnprocessable();
        $this->actingAs($jugador)->putJson('/perfil/anotar', ['anotar' => 'palotes'])->assertOk();
        // El cuadrado es del 6; el grupo de cinco, del 11. Sacarlo siempre se puede.
        $this->actingAs($jugador)->putJson('/perfil/distintivo', ['distintivo' => 'grupo-de-cinco'])->assertUnprocessable();
        $this->actingAs($jugador)->putJson('/perfil/distintivo', ['distintivo' => 'cuadrado'])->assertOk();
        // Cantor pide 25 envidos cantados.
        $this->actingAs($jugador)->putJson('/perfil/titulo', ['titulo' => 'cantor', 'femenino' => true])->assertUnprocessable();
        $this->actingAs($jugador)->putJson('/perfil/titulo', ['titulo' => 'inventado', 'femenino' => false])->assertUnprocessable();

        $jugador->forceFill(['envidos_cantados' => 25])->save();
        $this->actingAs($jugador)->putJson('/perfil/titulo', ['titulo' => 'cantor', 'femenino' => true])->assertOk();

        $jugador->refresh();
        $this->assertSame(['marmol', 'palotes', 'cuadrado', 'cantor', true], [$jugador->mesa, $jugador->anotar, $jugador->distintivo, $jugador->titulo, $jugador->titulo_femenino]);

        $this->actingAs($jugador)->putJson('/perfil/distintivo', ['distintivo' => 'ninguno'])->assertOk()->assertExactJson(['mensaje' => 'Sin distintivo.']);
        $this->assertNull($jugador->fresh()->distintivo);
    }

    public function test_la_administracion_tiene_todas_las_piezas_y_titulos_para_probarlos(): void
    {
        $administracion = Jugador::factory()->administrador()->create();

        $this->actingAs($administracion)->putJson('/perfil/mesa', ['mesa' => 'madera'])->assertOk();
        $this->actingAs($administracion)->putJson('/perfil/anotar', ['anotar' => 'tiza'])->assertOk();
        $this->actingAs($administracion)->putJson('/perfil/distintivo', ['distintivo' => 'cruzados'])->assertOk();
        $this->actingAs($administracion)->putJson('/perfil/titulo', ['titulo' => 'florido', 'femenino' => false])->assertOk();

        $this->assertSame('Florido', (new Premios)->tituloDe($administracion->fresh()));
    }

    public function test_quien_juega_sin_cuenta_no_elige_nada(): void
    {
        $invitado = Jugador::factory()->invitado()->create();

        $this->actingAs($invitado)->put('/perfil/mesa', ['mesa' => 'fabrica'])->assertRedirect('/registro');
        $this->actingAs($invitado)->put('/perfil/titulo', ['titulo' => 'pichon', 'femenino' => 0])->assertRedirect('/registro');
    }

    public function test_la_mesa_y_la_forma_de_anotar_se_ven_en_tu_mesa_de_juego(): void
    {
        $jugador = Jugador::factory()->create(['fosforos' => 200, 'mesa' => 'pizarron', 'anotar' => 'tiza']);
        $this->actingAs($jugador)->post('/jugar')->assertRedirect('/mesa');

        $this->actingAs($jugador)->get('/mesa')->assertOk()
            ->assertSee('mesa-de-pizarron')
            ->assertSee('anotador-tiza');
    }

    public function test_las_mesas_formas_de_anotar_y_distintivos_dibujados_coinciden_con_el_camino(): void
    {
        $listas = [];

        foreach (Camino::fichas() as $ficha) {
            if (isset(Premios::POR_NIVEL[$ficha['tipo']]) && $ficha['listo'] && $ficha['clave'] !== 'fabrica') {
                $listas[$ficha['tipo']][$ficha['clave']] = $ficha['nivel'];
            }
        }

        foreach (Premios::POR_NIVEL as $tipo => $piezas) {
            // Varias piezas pueden salir de una sola ficha (los cuatro palos): se cuentan por su ficha.
            $niveles = [];

            foreach ($piezas as $clave => $pieza) {
                // Lo de fábrica, lo que es de todos desde el principio (las variantes del mazo de fábrica) y lo que se
                // gana en la gira de los clubes no va en el camino.
                if ($clave !== 'fabrica' && ! isset($pieza['gira']) && $pieza['nivel'] > 1) {
                    $niveles[$pieza['ficha'] ?? $clave] = $pieza['nivel'];
                }
            }

            $this->assertEquals($niveles, $listas[$tipo] ?? [], "Las piezas de {$tipo} no coinciden con las fichas listas del camino.");
        }
    }

    public function test_cada_distintivo_se_dibuja_como_un_icono_solo_con_rectas_y_sus_cabezas(): void
    {
        $this->assertSame(array_keys(Premios::POR_NIVEL['distintivo']), array_keys(Distintivos::TODOS));

        foreach (Distintivos::TODOS as $clave => [$trazos, $cabezas]) {
            // Solo movimientos, líneas rectas y números: nada de curvas (C, Q, A, S, T).
            $this->assertMatchesRegularExpression('/^[MLHVZ0-9 .\-]+$/', $trazos, "El distintivo {$clave} tiene algo que no es una recta.");
            $this->assertNotEmpty($cabezas, "El distintivo {$clave} no tiene cabeza.");

            foreach ($cabezas as [$cx, $cy, $radio]) {
                $this->assertTrue($cx >= 0 && $cx <= 24 && $cy >= 0 && $cy <= 24, "Una cabeza del distintivo {$clave} se sale de la grilla de 24.");
                $this->assertLessThanOrEqual(2.6, $radio);
            }
        }

        // Y ninguno repite el dibujo de un ícono de la interfaz, salvo los cuatro palos, que son sus íconos a propósito.
        foreach (Distintivos::TODOS as $clave => [$trazos]) {
            foreach (Iconos::TODOS as $icono => [$deIcono]) {
                if ($clave !== 'palo-'.$icono) {
                    $this->assertNotSame($deIcono, $trazos, "El distintivo {$clave} es igual al ícono {$icono}.");
                }
            }
        }
    }
}
