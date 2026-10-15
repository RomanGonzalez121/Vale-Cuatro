<?php

namespace Tests\Feature;

use App\Juego\Camino;
use App\Juego\Mesa;
use App\Juego\Premios;
use App\Models\Jugador;
use App\Models\Partida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los niveles: cuántos fósforos anota cada partida, cómo se suman al cerrarla, cómo se rehacen desde las
 * partidas y qué ve el final de la partida y el menú cuando se sube.
 */
class CaminoTest extends TestCase
{
    use JugandoPartidas, PartidasCortas, RefreshDatabase;

    public function test_cada_nivel_son_diez_fosforos_y_el_ultimo_es_el_cuarenta(): void
    {
        $this->assertSame(1, Camino::nivelCon(0));
        $this->assertSame(1, Camino::nivelCon(9));
        $this->assertSame(2, Camino::nivelCon(10));
        $this->assertSame(40, Camino::nivelCon(390));
        $this->assertSame(40, Camino::nivelCon(5000));

        $this->assertSame(7, Camino::enLaTira(27));
        $this->assertSame(0, Camino::enLaTira(30));
        // En el último nivel la tira queda llena.
        $this->assertSame(10, Camino::enLaTira(5000));
    }

    public function test_ganar_anota_tres_y_perder_uno_y_se_rehace_desde_las_partidas(): void
    {
        $uno = Jugador::factory()->create();
        $dos = Jugador::factory()->create();

        $partidas = array_map(fn () => $this->partidaEntrePersonas($uno, $dos), range(1, 4));
        $ganoUno = count(array_filter($partidas, fn (Partida $partida) => $partida->ganador === Mesa::JUGADOR));

        $this->artisan('fosforos:recalcular')->assertSuccessful();

        $this->assertSame(3 * $ganoUno + (4 - $ganoUno), $uno->fresh()->fosforos);
        $this->assertSame(3 * (4 - $ganoUno) + $ganoUno, $dos->fresh()->fosforos);

        // Recalcular dos veces da lo mismo: no se suma encima.
        $this->artisan('fosforos:recalcular')->assertSuccessful();
        $this->assertSame(3 * $ganoUno + (4 - $ganoUno), $uno->fresh()->fosforos);
    }

    public function test_al_cerrar_la_partida_se_suman_y_el_paso_final_cuenta_cuanto_anoto(): void
    {
        $uno = Jugador::factory()->create();
        $dos = Jugador::factory()->create();
        $uno->forceFill(['fosforos' => 28])->save();

        $partida = $this->partidaCorta($uno, $dos);
        $this->ganar($partida, Mesa::JUGADOR);

        $this->assertSame(31, $uno->fresh()->fosforos);
        $this->assertSame(1, $dos->fresh()->fosforos);

        // El paso que la termina, visto por quien ganó: anotó tres y pasó del nivel 3 al 4, donde está el 5 de espada.
        $paso = collect($this->laMesaDeVerdad()->pasosDesde($partida->fresh(), 0, Mesa::JUGADOR))->last();

        $this->assertSame(3, $paso['experiencia']['ganados']);
        $this->assertSame([28, 31, 3, 4], [$paso['experiencia']['antes'], $paso['experiencia']['ahora'], $paso['experiencia']['nivelAntes'], $paso['experiencia']['nivelAhora']]);
        $this->assertContains('El 5 de espada', array_column($paso['experiencia']['premios'], 'nombre'));
    }

    public function test_abandonar_no_anota_y_ganar_por_abandono_antes_de_las_buenas_tampoco(): void
    {
        $uno = Jugador::factory()->create();
        $dos = Jugador::factory()->create();

        $partida = $this->partidaCorta($uno, $dos);
        $partida->forceFill(['puntos' => 30])->save();
        $this->laMesaDeVerdad()->abandonar($partida->fresh(), Mesa::BOT);

        // Se fue el invitado con el tanteo en cero: ninguno de los dos anota.
        $this->assertSame(0, $uno->fresh()->fosforos);
        $this->assertSame(0, $dos->fresh()->fosforos);
    }

    public function test_un_desafio_anota_la_primera_vez_que_sale_y_repetirlo_no(): void
    {
        $jugador = Jugador::factory()->create();
        $camino = new Camino;
        $mesa = $this->laMesaDeVerdad();

        $fallado = $this->desafioCerrado($jugador, ganador: 1, segundos: 1);
        $primero = $this->desafioCerrado($jugador, ganador: Mesa::JUGADOR, segundos: 2);
        $repetido = $this->desafioCerrado($jugador, ganador: Mesa::JUGADOR, segundos: 3);

        $this->assertSame(0, $camino->fosforosEn($fallado, Mesa::JUGADOR, $mesa));
        $this->assertSame(Camino::POR_DESAFIO, $camino->fosforosEn($primero, Mesa::JUGADOR, $mesa));
        $this->assertSame(0, $camino->fosforosEn($repetido, Mesa::JUGADOR, $mesa));
    }

    public function test_el_camino_tiene_algo_en_cada_nivel_y_coincide_con_los_premios_dibujados(): void
    {
        $niveles = array_unique(array_column(array_filter(Camino::fichas(), fn (array $ficha) => isset($ficha['nivel'])), 'nivel'));

        for ($nivel = 1; $nivel <= Camino::NIVELES; $nivel++) {
            $this->assertContains($nivel, $niveles, "El nivel {$nivel} no destraba nada.");
        }

        // Las cartas del camino son las mismas que las de Premios, en el mismo nivel.
        $cartas = array_column(array_filter(Camino::fichas(), fn (array $ficha) => $ficha['tipo'] === 'carta'), 'nivel', 'clave');
        $this->assertSame(Premios::CAMINO, $cartas);

        // Cada dorso dibujado está en el camino, en el mismo lugar.
        foreach (Premios::DORSOS as $clave => $dorso) {
            $ficha = collect(Camino::fichas())->first(fn (array $ficha) => $ficha['tipo'] === 'dorso' && $ficha['clave'] === $clave);

            $this->assertNotNull($ficha, "Al dorso {$clave} le falta su ficha.");
            $this->assertTrue($ficha['listo']);
            $this->assertSame($dorso['nivel'] ?? null, $ficha['nivel'] ?? null);
            $this->assertSame($dorso['escalon'] ?? null, $ficha['escalon'] ?? null);
        }
    }

    public function test_el_apodo_se_marca_con_un_premio_nuevo_hasta_que_se_mira_el_perfil(): void
    {
        $jugador = Jugador::factory()->create(['apodo' => 'El Tano']);
        $premios = new Premios;

        $this->assertFalse($premios->hayNovedad($jugador->fresh()));

        // Sube al nivel 4, donde está el 5 de espada.
        $jugador->forceFill(['fosforos' => 30])->save();
        $this->assertTrue($premios->hayNovedad($jugador->fresh()));
        $this->actingAs($jugador->fresh())->get('/ranking')->assertSee('Tenés un premio nuevo');

        // Mirar el perfil la apaga.
        $this->actingAs($jugador->fresh())->get('/perfil')->assertOk();
        $this->assertFalse($premios->hayNovedad($jugador->fresh()));
        $this->actingAs($jugador->fresh())->get('/ranking')->assertDontSee('Tenés un premio nuevo');
    }

    public function test_jugar_se_marca_y_lleva_a_la_mesa_con_una_partida_sin_terminar(): void
    {
        $uno = Jugador::factory()->create();
        $dos = Jugador::factory()->create();

        $this->actingAs($uno)->get('/ranking')->assertDontSee('Tenés una partida sin terminar');

        $this->partidaCorta($uno, $dos);

        $this->actingAs($uno)->get('/ranking')
            ->assertSee('Tenés una partida sin terminar')
            ->assertSee('href="'.route('mesa').'"', false);
    }

    private function desafioCerrado(Jugador $jugador, int $ganador, int $segundos): Partida
    {
        $partida = new Partida(['jugador_id' => $jugador->id, 'primer_mano' => 0, 'puntos' => 30]);
        $partida->forceFill([
            'desafio' => 'el-envido-esta-primero',
            'estado' => Partida::TERMINADA,
            'ganador' => $ganador,
            'terminada_en' => now()->addSeconds($segundos),
        ])->save();

        return $partida;
    }
}
