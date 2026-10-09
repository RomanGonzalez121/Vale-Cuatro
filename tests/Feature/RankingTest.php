<?php

namespace Tests\Feature;

use App\Juego\Ranking;
use App\Models\Jugador;
use App\Models\Resultado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La tabla del ranking: en qué orden quedan los jugadores, quién entra y qué puesto le toca a quien mira.
 */
class RankingTest extends TestCase
{
    use AnotandoResultados;
    use RefreshDatabase;

    public function test_se_ordena_por_ganadas_despues_por_porcentaje_y_despues_por_racha(): void
    {
        // Cuatro ganadas cada uno, salvo el último. Entre los que empatan decide el porcentaje, y después la racha.
        $conPeorPorcentaje = Jugador::factory()->create(['apodo' => 'Cuatro de ocho']);
        $conRachaCorta = Jugador::factory()->create(['apodo' => 'Cuatro de cinco, racha 1']);
        $conRachaLarga = Jugador::factory()->create(['apodo' => 'Cuatro de cinco, racha 4']);
        $conMasGanadas = Jugador::factory()->create(['apodo' => 'Cinco de nueve']);
        $elUltimo = Jugador::factory()->create(['apodo' => 'Una de una']);

        $this->anotarle($conPeorPorcentaje, 'GPGPGPGP');
        $this->anotarle($conRachaCorta, 'GGGPG');
        $this->anotarle($conRachaLarga, 'PGGGG');
        $this->anotarle($conMasGanadas, 'GPGPGPGPG');
        $this->anotarle($elUltimo, 'G');

        $filas = (new Ranking)->para()['filas'];

        $this->assertSame(
            ['Cinco de nueve', 'Cuatro de cinco, racha 4', 'Cuatro de cinco, racha 1', 'Cuatro de ocho', 'Una de una'],
            array_column($filas, 'apodo'),
        );
        $this->assertSame([1, 2, 3, 4, 5], array_column($filas, 'puesto'));
        $this->assertSame([5, 4, 4, 4, 1], array_column($filas, 'ganadas'));
        $this->assertSame([9, 5, 5, 8, 1], array_column($filas, 'jugadas'));
    }

    public function test_la_racha_son_las_ganadas_despues_de_la_ultima_perdida(): void
    {
        $series = ['GGPGG' => 2, 'GGG' => 3, 'GP' => 0, 'P' => 0, 'PPG' => 1];

        foreach (array_keys($series) as $serie) {
            $this->anotarle(Jugador::factory()->create(['apodo' => "Serie {$serie}"]), $serie);
        }

        $rachas = array_column((new Ranking)->para()['filas'], 'racha', 'apodo');

        foreach ($series as $serie => $racha) {
            $this->assertSame($racha, $rachas["Serie {$serie}"], "Serie {$serie}");
        }
    }

    public function test_la_racha_se_lee_por_la_fecha_de_las_partidas_y_no_por_el_orden_en_que_se_anotaron(): void
    {
        $jugador = Jugador::factory()->create();
        $this->anotarle($jugador, 'PGG');

        $this->assertSame(2, (new Ranking)->para()['filas'][0]['racha']);

        // Se vuelve a anotar la perdida vieja (queda con un número de renglón más alto, pero con su fecha de antes):
        // la racha de ahora no cambia.
        $perdida = Resultado::query()->where('gano', false)->sole();
        $copia = $perdida->replicate();
        $perdida->delete();
        $copia->save();

        $this->assertSame(2, (new Ranking)->para()['filas'][0]['racha']);
    }

    public function test_si_todo_empata_el_orden_no_cambia_de_una_visita_a_otra(): void
    {
        $primero = Jugador::factory()->create();
        $segundo = Jugador::factory()->create();

        // Anotados en el orden contrario al de llegada al sitio: va antes quien llegó antes.
        $this->anotarle($segundo, 'GP');
        $this->anotarle($primero, 'GP');

        foreach ([1, 2, 3] as $visita) {
            $this->assertSame([$primero->id, $segundo->id], array_column((new Ranking)->para()['filas'], 'id'), "Visita {$visita}");
        }
    }

    public function test_en_la_tabla_estan_las_cuentas_y_los_de_ejemplo_y_no_quien_juega_sin_cuenta(): void
    {
        $cuenta = Jugador::factory()->create();
        $deEjemplo = Jugador::factory()->deEjemplo()->create();
        $invitado = Jugador::factory()->invitado()->create();

        $this->anotarle($invitado, 'GGGG');
        $this->anotarle($deEjemplo, 'GG');
        $this->anotarle($cuenta, 'G');

        $filas = (new Ranking)->para()['filas'];

        $this->assertSame([$deEjemplo->id, $cuenta->id], array_column($filas, 'id'));
        // El de ejemplo va marcado como bot y la cuenta no. Los puestos no dejan un hueco por el invitado.
        $this->assertSame([true, false], array_column($filas, 'bot'));
        $this->assertSame([1, 2], array_column($filas, 'puesto'));
    }

    public function test_a_quien_tiene_cuenta_le_dice_su_puesto_y_a_quien_tiene_que_alcanzar(): void
    {
        [$arriba, $vos, $abajo] = [Jugador::factory()->create(['apodo' => 'La de arriba']), Jugador::factory()->create(), Jugador::factory()->create()];

        $this->anotarle($arriba, 'GGGGG');
        $this->anotarle($vos, 'GGP');
        $this->anotarle($abajo, 'G');

        $tabla = (new Ranking)->para($vos);

        $this->assertSame(2, $tabla['propia']['puesto']);
        $this->assertSame([2, 3], [$tabla['propia']['ganadas'], $tabla['propia']['jugadas']]);
        $this->assertSame('La de arriba', $tabla['deArriba']['apodo']);
        // Su fila de la tabla está marcada, y es la única.
        $this->assertSame([false, true, false], array_column($tabla['filas'], 'vos'));

        // Quien va primero no tiene a nadie arriba.
        $delPrimero = (new Ranking)->para($arriba);
        $this->assertSame(1, $delPrimero['propia']['puesto']);
        $this->assertNull($delPrimero['deArriba']);
    }

    public function test_a_quien_juega_sin_cuenta_le_dice_en_que_puesto_estaria_sin_ponerlo_en_la_tabla(): void
    {
        [$arriba, $abajo] = [Jugador::factory()->create(['apodo' => 'La de arriba']), Jugador::factory()->create()];
        $invitado = Jugador::factory()->invitado()->create();

        $this->anotarle($arriba, 'GGGGG');
        $this->anotarle($invitado, 'GGG');
        $this->anotarle($abajo, 'G');

        $tabla = (new Ranking)->para($invitado);

        $this->assertSame(2, $tabla['propia']['puesto'], 'Entre la de arriba y la de abajo.');
        $this->assertSame('La de arriba', $tabla['deArriba']['apodo']);
        // En la tabla siguen estando las dos cuentas, con sus puestos de siempre.
        $this->assertSame([$arriba->id, $abajo->id], array_column($tabla['filas'], 'id'));
        $this->assertSame([1, 2], array_column($tabla['filas'], 'puesto'));
        $this->assertNotContains(true, array_column($tabla['filas'], 'vos'));
    }

    public function test_sin_partidas_que_cuenten_no_hay_puesto(): void
    {
        $this->anotarle(Jugador::factory()->create(), 'G');

        $this->assertNull((new Ranking)->para(Jugador::factory()->create())['propia']);
        $this->assertNull((new Ranking)->para()['propia']);
        $this->assertSame(['filas' => [], 'propia' => null, 'deArriba' => null], $this->sinNadie());
    }

    public function test_el_porcentaje_de_envidos_sale_de_los_jugados_y_falta_si_no_jugo_ninguno(): void
    {
        [$conEnvidos, $sinEnvidos] = [Jugador::factory()->create(), Jugador::factory()->create()];

        $this->anotarle($conEnvidos, 'GG', envidosJugados: 3, envidosGanados: 2);
        $this->anotarle($sinEnvidos, 'G');

        $this->assertSame([67, null], array_column((new Ranking)->para()['filas'], 'envidos'));
    }

    public function test_la_pantalla_muestra_los_primeros_y_el_puesto_de_quien_esta_mas_abajo_se_sigue_sabiendo(): void
    {
        // Veintitrés cuentas: la primera con 23 ganadas, la última con una.
        $jugadores = Jugador::factory()->count(Ranking::A_LA_VISTA + 3)->create();

        foreach ($jugadores as $numero => $jugador) {
            $this->anotarle($jugador, str_repeat('G', $jugadores->count() - $numero));
        }

        $tabla = (new Ranking)->para($jugadores->last());

        $this->assertCount(Ranking::A_LA_VISTA, $tabla['filas']);
        $this->assertSame(Ranking::A_LA_VISTA + 3, $tabla['propia']['puesto']);
        $this->assertSame($jugadores[$jugadores->count() - 2]->id, $tabla['deArriba']['id']);
    }

    /**
     * La tabla de un sitio sin ninguna partida.
     *
     * @return array<string, mixed>
     */
    private function sinNadie(): array
    {
        Resultado::query()->delete();

        return (new Ranking)->para();
    }
}
